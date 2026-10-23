<?php

namespace Tests\Functional\Persona;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class PersonApiShowTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_show_person(): void
    {
        $response = $this->getJson(
            '/api/personas/01900000-0000-7000-8000-000000000001'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_show_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO PERSONA',
            'email' => 'persona.show.forbidden@siga.test',
        ]);

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA PROTEGIDA API', 'PRUEBA']
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_show_existing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA',
            'email' => 'persona.show@siga.test',
        ]);

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA CONSULTADA API', 'PRUEBA']
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id_persona',
                $person->id_persona
            )
            ->assertJsonPath(
                'nombres',
                'PERSONA CONSULTADA API'
            )
            ->assertJsonPath(
                'informacion_registro.forma_registro',
                'NO_DETERMINADO'
            )
            ->assertJsonPath(
                'informacion_registro.archivo_origen',
                null
            )
            ->assertJsonPath(
                'informacion_registro.fila_archivo',
                null
            )
            ->assertJsonStructure([
                'informacion_registro' => [
                    'fecha_registro',
                    'forma_registro',
                    'registrado_por',
                    'archivo_origen',
                    'fila_archivo',
                    'lote_uuid',
                ],
            ]);
    }

    public function test_explicit_deny_overrides_allow_when_showing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO ALLOW Y DENY PERSONA',
            'email' => 'persona.show.deny-overrides@siga.test',
        ]);

        $this->assignRole(
            $user,
            'ROL_GESTOR_PERSONAS'
        );

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $this->replaceRolePermissionEffect(
            'ROL_CONSULTA_PERSONAS',
            'personas.ver',
            'DENY'
        );

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA DENEGADA POR REGLA EXPLICITA', 'PRUEBA']
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_receives_not_found_for_missing_person(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CONSULTA PERSONA INEXISTENTE',
            'email' => 'persona.show.missing@siga.test',
        ]);

        $this->assignRole(
            $user,
            'ROL_CONSULTA_PERSONAS'
        );

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/01900000-0000-7000-8000-000000000001'
        );

        $response->assertNotFound();
    }

    public function test_authorized_user_receives_individual_registration_origin(): void
    {
        $user = User::factory()->create([
            'name' => 'RESPONSABLE REGISTRO INDIVIDUAL',
            'email' => 'persona.origen.individual@siga.test',
        ]);

        $this->assignRole($user, 'ROL_CONSULTA_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA ORIGEN INDIVIDUAL', 'PRUEBA']
        );

        $this->db
            ->table('system.activities')
            ->insert([
                'id_usuario' => $user->id,
                'entidad' => 'PERSONA',
                'id_entidad' => $person->id_persona,
                'accion' => 'CREACION',
                'datos_anteriores' => null,
                'datos_nuevos' => json_encode(
                    ['origen_registro' => 'INDIVIDUAL'],
                    JSON_THROW_ON_ERROR
                ),
                'campos_modificados' => json_encode(
                    [],
                    JSON_THROW_ON_ERROR
                ),
            ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'informacion_registro.forma_registro',
                'INDIVIDUAL'
            )
            ->assertJsonPath(
                'informacion_registro.registrado_por',
                'RESPONSABLE REGISTRO INDIVIDUAL'
            )
            ->assertJsonPath(
                'informacion_registro.archivo_origen',
                null
            )
            ->assertJsonPath(
                'informacion_registro.fila_archivo',
                null
            )
            ->assertJsonPath(
                'informacion_registro.lote_uuid',
                null
            );
    }
    public function test_authorized_user_receives_mass_registration_origin(): void
    {
        $user = User::factory()->create([
            'name' => 'RESPONSABLE IMPORTACION PERSONAS',
            'email' => 'persona.origen.masivo@siga.test',
        ]);

        $this->assignRole($user, 'ROL_CONSULTA_PERSONAS');

        $person = $this->db->selectOne(
            'INSERT INTO institutional.persons
                (nombres, apellido_paterno)
             VALUES (?, ?)
             RETURNING id_persona',
            ['PERSONA ORIGEN MASIVO', 'PRUEBA']
        );

        $this->db->table('system.activities')->insert([
            'id_usuario' => $user->id,
            'entidad' => 'PERSONA',
            'id_entidad' => $person->id_persona,
            'accion' => 'CREACION',
            'datos_anteriores' => null,
            'datos_nuevos' => json_encode(
                ['origen_registro' => 'MASIVO'],
                JSON_THROW_ON_ERROR
            ),
            'campos_modificados' => json_encode(
                [],
                JSON_THROW_ON_ERROR
            ),
        ]);

        $batchUuid = (string) \Illuminate\Support\Str::uuid();

        $batchId = $this->db
            ->table('system.import_batches')
            ->insertGetId([
                'uuid' => $batchUuid,
                'import_type' => 'PERSONAS',
                'status' => 'COMPLETED',
                'original_filename' => 'personal_octubre.xlsx',
                'storage_disk' => 'local',
                'storage_path' => 'test/personal_octubre.xlsx',
                'file_sha256' => str_repeat('a', 64),
                'total_rows' => 1,
                'valid_rows' => 1,
                'invalid_rows' => 0,
                'processed_rows' => 1,
                'failed_rows' => 0,
                'created_by' => $user->id,
            ]);

        $this->db->table('system.import_rows')->insert([
            'import_batch_id' => $batchId,
            'row_number' => 25,
            'status' => 'PROCESSED',
            'raw_data' => json_encode(
                ['nombres' => 'PERSONA ORIGEN MASIVO'],
                JSON_THROW_ON_ERROR
            ),
            'normalized_data' => json_encode(
                ['nombres' => 'PERSONA ORIGEN MASIVO'],
                JSON_THROW_ON_ERROR
            ),
            'validation_errors' => json_encode(
                [],
                JSON_THROW_ON_ERROR
            ),
            'result_data' => json_encode(
                ['id_persona' => $person->id_persona],
                JSON_THROW_ON_ERROR
            ),
        ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/personas/'.$person->id_persona
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'informacion_registro.forma_registro',
                'MASIVO'
            )
            ->assertJsonPath(
                'informacion_registro.registrado_por',
                'RESPONSABLE IMPORTACION PERSONAS'
            )
            ->assertJsonPath(
                'informacion_registro.archivo_origen',
                'personal_octubre.xlsx'
            )
            ->assertJsonPath(
                'informacion_registro.fila_archivo',
                25
            )
            ->assertJsonPath(
                'informacion_registro.lote_uuid',
                $batchUuid
            )
            ->assertJsonMissingPath(
                'informacion_registro.storage_path'
            )
            ->assertJsonMissingPath(
                'informacion_registro.file_sha256'
            );
    }
    private function assignRole(
        User $user,
        string $roleCode
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function replaceRolePermissionEffect(
        string $roleCode,
        string $permissionCode,
        string $effect
    ): void {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $roleCode)
            ->value('id');

        $permissionId = $this->db
            ->table('system.permissions')
            ->where('code', $permissionCode)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$roleCode}."
        );

        self::assertNotNull(
            $permissionId,
            "Debe existir el permiso {$permissionCode}."
        );

        $deleted = $this->db
            ->table('system.role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();

        self::assertSame(
            1,
            $deleted,
            'Debe existir exactamente una relación rol-permiso.'
        );

        $this->db
            ->table('system.role_permissions')
            ->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'effect' => $effect,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
