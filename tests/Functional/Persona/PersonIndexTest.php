<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonIndexTest extends HttpFunctionalTestCase
{
    public function test_usuario_con_permiso_puede_consultar_listado_paginado(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONAS',
            'email' => 'person.index@siga.test',
        ]);

        $roleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        self::assertNotNull(
            $roleId,
            'Debe existir el rol ROL_CONSULTA_PERSONAS.'
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $firstId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'ALFA PERSONA LISTADO',
                    'apellido_paterno' => 'PRUEBA',
                    'curp' => 'GARM900101HNTXXX01',
                    'rfc' => 'GARM900101ABC',
                    'correo_institucional' => 'persona.listado@siga.test',
                    'correo_personal' => 'persona.personal@siga.test',
                ],
                'id_persona'
            );

        $secondId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'BETA PERSONA LISTADO',
                    'apellido_paterno' => 'PRUEBA',
                ],
                'id_persona'
            );

        $this->actingAs($user);

        $response = $this->getJson('/api/personas');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_persona',
                        'num_expediente',
                        'nombres',
                        'apellido_paterno',
                        'apellido_materno',
                        'estatus',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'last_page',
                    'total',
                ],
            ]);

        $returnedIds = collect(
            $response->json('data')
        )
            ->pluck('id_persona')
            ->all();

        self::assertContains(
            $firstId,
            $returnedIds
        );

        self::assertContains(
            $secondId,
            $returnedIds
        );

        $firstPerson = collect(
            $response->json('data')
        )->firstWhere(
            'id_persona',
            $firstId
        );

        self::assertNotNull(
            $firstPerson,
            'La Persona de prueba debe estar presente en el listado.'
        );

        foreach (
            [
                'curp',
                'rfc',
                'correo_institucional',
                'correo_personal',
            ] as $sensitiveField
        ) {
            self::assertArrayNotHasKey(
                $sensitiveField,
                $firstPerson,
                "El listado no debe exponer {$sensitiveField}."
            );
        }

        self::assertSame(
            20,
            $response->json('meta.per_page')
        );
    }

    public function test_usuario_sin_permiso_no_puede_consultar_listado(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO PERSONAS',
            'email' => 'person.index.denied@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/personas');

        $response->assertForbidden();
    }

    public function test_listado_respeta_paginacion_de_veinte_registros(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PAGINACION PERSONAS',
            'email' => 'person.pagination@siga.test',
        ]);

        $roleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        self::assertNotNull(
            $roleId,
            'Debe existir el rol ROL_CONSULTA_PERSONAS.'
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $initialCount = $this->db
            ->table('institutional.persons')
            ->count();

        for ($index = 1; $index <= 21; $index++) {
            $this->db
                ->table('institutional.persons')
                ->insert([
                    'nombres' => sprintf(
                        'PERSONA PAGINACION %02d',
                        $index
                    ),
                    'apellido_paterno' => 'PRUEBA',
                ]);
        }

        $expectedTotal = $initialCount + 21;
        $expectedLastPage = (int) ceil(
            $expectedTotal / 20
        );

        $this->actingAs($user);

        $firstPage = $this->getJson(
            '/api/personas?page=1'
        );

        $firstPage
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                20
            )
            ->assertJsonPath(
                'meta.last_page',
                $expectedLastPage
            )
            ->assertJsonPath(
                'meta.total',
                $expectedTotal
            );

        self::assertCount(
            min(20, $expectedTotal),
            $firstPage->json('data')
        );

        $secondPage = $this->getJson(
            '/api/personas?page=2'
        );

        $secondPage
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                20
            )
            ->assertJsonPath(
                'meta.last_page',
                $expectedLastPage
            )
            ->assertJsonPath(
                'meta.total',
                $expectedTotal
            );

        self::assertCount(
            min(
                20,
                max(
                    0,
                    $expectedTotal - 20
                )
            ),
            $secondPage->json('data')
        );
    }

    public function test_denegacion_explicita_prevalece_sobre_permiso_de_consulta(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO DENY PERSONAS',
            'email' => 'person.index.deny@siga.test',
        ]);

        $roleIds = [];

        foreach (
            [
                'ROL_GESTOR_PERSONAS',
                'ROL_CONSULTA_PERSONAS',
            ] as $roleCode
        ) {
            $roleId = $this->db
                ->table('system.roles')
                ->where('code', $roleCode)
                ->value('id');

            self::assertNotNull(
                $roleId,
                "Debe existir el rol {$roleCode}."
            );

            $roleIds[$roleCode] = $roleId;

            $this->db
                ->table('system.user_roles')
                ->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $permissionId = $this->db
            ->table('system.permissions')
            ->where('code', 'personas.ver')
            ->value('id');

        self::assertNotNull(
            $permissionId,
            'Debe existir el permiso personas.ver.'
        );

        $consultRoleId =
            $roleIds['ROL_CONSULTA_PERSONAS'];

        $this->db
            ->table('system.role_permissions')
            ->where('role_id', $consultRoleId)
            ->where('permission_id', $permissionId)
            ->delete();

        $this->db
            ->table('system.role_permissions')
            ->insert([
                'role_id' => $consultRoleId,
                'permission_id' => $permissionId,
                'effect' => 'DENY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas'
        );

        $response->assertForbidden();
    }

    public function test_usuario_no_autenticado_no_puede_consultar_listado(): void
    {
        $response = $this->getJson(
            '/api/personas'
        );

        $response->assertUnauthorized();
    }

    public function test_listado_mantiene_orden_ascendente_por_num_expediente(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO ORDEN PERSONAS',
            'email' => 'person.order@siga.test',
        ]);

        $roleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        self::assertNotNull(
            $roleId,
            'Debe existir el rol ROL_CONSULTA_PERSONAS.'
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        for ($index = 1; $index <= 3; $index++) {
            $this->db
                ->table('institutional.persons')
                ->insert([
                    'nombres' => sprintf(
                        'PERSONA ORDEN %02d',
                        $index
                    ),
                    'apellido_paterno' => 'PRUEBA',
                ]);
        }

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas?page=1'
        );

        $response->assertOk();

        $expedientes = collect(
            $response->json('data')
        )
            ->pluck('num_expediente')
            ->all();

        self::assertNotEmpty(
            $expedientes,
            'El listado debe contener expedientes.'
        );

        $expected = $expedientes;

        sort(
            $expected,
            SORT_STRING
        );

        self::assertSame(
            $expected,
            $expedientes,
            'Las Personas deben ordenarse ascendentemente por num_expediente.'
        );
    }
}
