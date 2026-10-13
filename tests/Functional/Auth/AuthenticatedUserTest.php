<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class AuthenticatedUserTest extends HttpFunctionalTestCase
{
    public function test_authenticated_user_can_access_user_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Funcional SIGA',
            'email' => 'auth.functional@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('email', $user->email);
    }

    public function test_authenticated_user_endpoint_includes_linked_person(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA AUTENTICADA SIGA',
                    'apellido_paterno' => 'PRUEBA',
                ],
                'id_persona'
            );

        $user = User::factory()->create([
            'name' => 'USUARIO CON PERSONA SIGA',
            'email' => 'auth.person@siga.test',
            'id_persona' => $personId,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('id_persona', $personId)
            ->assertJsonPath('person.id_persona', $personId)
            ->assertJsonPath(
                'person.nombres',
                'PERSONA AUTENTICADA SIGA'
            );

        $response
            ->assertJsonMissingPath('person.curp')
            ->assertJsonMissingPath('person.rfc')
            ->assertJsonMissingPath('person.correo_institucional')
            ->assertJsonMissingPath('person.correo_personal');
    }

    public function test_authenticated_user_endpoint_includes_effective_permissions(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CON PERMISOS SIGA',
            'email' => 'auth.permissions@siga.test',
        ]);

        $roleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_GESTOR_PERSONAS')
            ->value('id');

        self::assertNotNull(
            $roleId,
            'Debe existir el rol ROL_GESTOR_PERSONAS.'
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath(
                'permissions',
                [
                    'personas.actualizar',
                    'personas.baja',
                    'personas.crear',
                    'personas.reingreso',
                    'personas.ver',
                ]
            );
    }

    public function test_authenticated_user_endpoint_excludes_permission_denied_by_another_role(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CON DENEGACION SIGA',
            'email' => 'auth.permissions.deny@siga.test',
        ]);

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

            $this->db
                ->table('system.user_roles')
                ->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $consultRoleId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        $viewPermissionId = $this->db
            ->table('system.permissions')
            ->where('code', 'personas.ver')
            ->value('id');

        self::assertNotNull($consultRoleId);
        self::assertNotNull($viewPermissionId);

        $this->db
            ->table('system.role_permissions')
            ->where('role_id', $consultRoleId)
            ->where('permission_id', $viewPermissionId)
            ->delete();

        $this->db
            ->table('system.role_permissions')
            ->insert([
                'role_id' => $consultRoleId,
                'permission_id' => $viewPermissionId,
                'effect' => 'DENY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk();

        self::assertSame(
            [
                'personas.actualizar',
                'personas.baja',
                'personas.crear',
                'personas.reingreso',
            ],
            $response->json('permissions')
        );
    }

    public function test_authenticated_user_without_permission_rules_receives_empty_permissions(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN REGLAS SIGA',
            'email' => 'auth.permissions.empty@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath(
                'permissions',
                []
            );
    }
}
