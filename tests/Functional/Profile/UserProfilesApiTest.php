<?php

namespace Tests\Functional\Profile;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class UserProfilesApiTest extends HttpFunctionalTestCase
{
    public function test_usuario_autenticado_puede_consultar_solo_sus_perfiles_asignados(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PERFILES API',
            'email' => 'profiles.api@siga.test',
        ]);

        $administrationId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_ADMINISTRACION')
            ->value('id');

        $personasId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->value('id');

        $auditId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_AUDITORIA')
            ->value('id');

        self::assertNotNull($administrationId);
        self::assertNotNull($personasId);
        self::assertNotNull($auditId);

        $now = now();

        $this->db
            ->table('system.user_profiles')
            ->insert([
                [
                    'user_id' => $user->id,
                    'profile_id' => $administrationId,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $user->id,
                    'profile_id' => $personasId,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/user/profiles'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'is_default',
                    ],
                ],
            ]);

        $profiles = collect(
            $response->json('data')
        )->keyBy('code');

        self::assertTrue(
            $profiles->has('PERFIL_ADMINISTRACION')
        );

        self::assertTrue(
            $profiles->has('PERFIL_PERSONAS')
        );

        self::assertFalse(
            $profiles->has('PERFIL_AUDITORIA')
        );

        self::assertTrue(
            (bool) $profiles
                ->get('PERFIL_ADMINISTRACION')['is_default']
        );

        self::assertFalse(
            (bool) $profiles
                ->get('PERFIL_PERSONAS')['is_default']
        );
    }

    public function test_usuario_no_autenticado_no_puede_consultar_perfiles(): void
    {
        $response = $this->getJson(
            '/api/user/profiles'
        );

        $response->assertUnauthorized();
    }

    public function test_perfil_administracion_no_concede_permisos_ni_autoriza_operaciones(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PERFIL SIN PERMISOS',
            'email' => 'profile.security@siga.test',
        ]);

        $administrationId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_ADMINISTRACION')
            ->value('id');

        self::assertNotNull(
            $administrationId,
            'Debe existir el perfil PERFIL_ADMINISTRACION.'
        );

        $this->db
            ->table('system.user_profiles')
            ->insert([
                'user_id' => $user->id,
                'profile_id' => $administrationId,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $userResponse = $this->getJson(
            '/api/user'
        );

        $userResponse
            ->assertOk()
            ->assertJsonPath(
                'permissions',
                []
            );

        $personasResponse = $this->getJson(
            '/api/personas'
        );

        $personasResponse->assertForbidden();
    }

    public function test_usuario_autenticado_sin_perfiles_recibe_lista_vacia(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERFILES',
            'email' => 'profiles.empty@siga.test',
        ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/user/profiles'
        );

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }

    public function test_usuario_puede_establecer_como_predeterminado_un_perfil_asignado(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CAMBIO PERFIL',
            'email' => 'profiles.default@siga.test',
        ]);

        $personasId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->value('id');

        $auditId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_AUDITORIA')
            ->value('id');

        self::assertNotNull($personasId);
        self::assertNotNull($auditId);

        $now = now();

        $this->db
            ->table('system.user_profiles')
            ->insert([
                [
                    'user_id' => $user->id,
                    'profile_id' => $personasId,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $user->id,
                    'profile_id' => $auditId,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

        $this->actingAs($user);

        $response = $this->putJson(
            "/api/user/profiles/{$auditId}/default"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.code',
                'PERFIL_AUDITORIA'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $assignments = $this->db
            ->table('system.user_profiles')
            ->where('user_id', $user->id)
            ->pluck('is_default', 'profile_id');

        self::assertFalse(
            (bool) $assignments[$personasId]
        );

        self::assertTrue(
            (bool) $assignments[$auditId]
        );
    }

    public function test_usuario_no_puede_establecer_como_predeterminado_un_perfil_no_asignado(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PERFIL NO ASIGNADO',
            'email' => 'profiles.unassigned@siga.test',
        ]);

        $personasId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->value('id');

        $administrationId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_ADMINISTRACION')
            ->value('id');

        self::assertNotNull($personasId);
        self::assertNotNull($administrationId);

        $this->db
            ->table('system.user_profiles')
            ->insert([
                'user_id' => $user->id,
                'profile_id' => $personasId,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->putJson(
            "/api/user/profiles/{$administrationId}/default"
        );

        $response->assertNotFound();

        $assignments = $this->db
            ->table('system.user_profiles')
            ->where('user_id', $user->id)
            ->get();

        self::assertCount(1, $assignments);

        self::assertSame(
            (int) $personasId,
            (int) $assignments->first()->profile_id
        );

        self::assertTrue(
            (bool) $assignments->first()->is_default
        );
    }

    public function test_usuario_no_autenticado_no_puede_cambiar_perfil_predeterminado(): void
    {
        $profileId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->value('id');

        self::assertNotNull($profileId);

        $response = $this->putJson(
            "/api/user/profiles/{$profileId}/default"
        );

        $response->assertUnauthorized();
    }

    public function test_cambiar_a_perfil_administracion_no_modifica_permisos_efectivos(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CAMBIO PERFIL SIN PERMISOS',
            'email' => 'profiles.permissions@siga.test',
        ]);

        $personasId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->value('id');

        $administrationId = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_ADMINISTRACION')
            ->value('id');

        self::assertNotNull($personasId);
        self::assertNotNull($administrationId);

        $now = now();

        $this->db
            ->table('system.user_profiles')
            ->insert([
                [
                    'user_id' => $user->id,
                    'profile_id' => $personasId,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_id' => $user->id,
                    'profile_id' => $administrationId,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

        $this->actingAs($user);

        $before = $this->getJson('/api/user');

        $before
            ->assertOk()
            ->assertJsonPath('permissions', []);

        $change = $this->putJson(
            "/api/user/profiles/{$administrationId}/default"
        );

        $change
            ->assertOk()
            ->assertJsonPath(
                'data.code',
                'PERFIL_ADMINISTRACION'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $after = $this->getJson('/api/user');

        $after
            ->assertOk()
            ->assertJsonPath('permissions', []);

        $personasResponse = $this->getJson(
            '/api/personas'
        );

        $personasResponse->assertForbidden();
    }

    public function test_perfiles_del_usuario_incluyen_grupo_de_trabajo(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO PERFIL AGRUPADO',
            'email' => 'profiles.group@siga.test',
        ]);

        $personas = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_PERSONAS')
            ->first([
                'id',
                'profile_group_id',
            ]);

        self::assertNotNull($personas);

        $group = $this->db
            ->table('system.profile_groups')
            ->where('id', $personas->profile_group_id)
            ->first([
                'id',
                'name',
                'code',
                'sort_order',
            ]);

        self::assertNotNull($group);

        self::assertSame(
            'GRUPO_OPERACION',
            $group->code
        );

        $this->db
            ->table('system.user_profiles')
            ->insert([
                'user_id' => $user->id,
                'profile_id' => $personas->id,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->getJson(
            '/api/user/profiles'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'is_default',
                        'group' => [
                            'id',
                            'name',
                            'code',
                            'sort_order',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.0.code',
                'PERFIL_PERSONAS'
            )
            ->assertJsonPath(
                'data.0.group.id',
                (int) $group->id
            )
            ->assertJsonPath(
                'data.0.group.name',
                'Operación'
            )
            ->assertJsonPath(
                'data.0.group.code',
                'GRUPO_OPERACION'
            )
            ->assertJsonPath(
                'data.0.group.sort_order',
                10
            );
    }

    public function test_cambio_de_perfil_devuelve_grupo_de_trabajo(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO CAMBIO PERFIL AGRUPADO',
            'email' => 'profiles.change.group@siga.test',
        ]);

        $audit = $this->db
            ->table('system.profiles')
            ->where('code', 'PERFIL_AUDITORIA')
            ->first([
                'id',
                'profile_group_id',
            ]);

        self::assertNotNull($audit);

        $group = $this->db
            ->table('system.profile_groups')
            ->where('id', $audit->profile_group_id)
            ->first([
                'id',
                'name',
                'code',
                'sort_order',
            ]);

        self::assertNotNull($group);

        self::assertSame(
            'GRUPO_CONTROL_SUPERVISION',
            $group->code
        );

        $this->db
            ->table('system.user_profiles')
            ->insert([
                'user_id' => $user->id,
                'profile_id' => $audit->id,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $response = $this->putJson(
            "/api/user/profiles/{$audit->id}/default"
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'is_default',
                    'group' => [
                        'id',
                        'name',
                        'code',
                        'sort_order',
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.code',
                'PERFIL_AUDITORIA'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            )
            ->assertJsonPath(
                'data.group.id',
                (int) $group->id
            )
            ->assertJsonPath(
                'data.group.name',
                'Control y supervisión'
            )
            ->assertJsonPath(
                'data.group.code',
                'GRUPO_CONTROL_SUPERVISION'
            )
            ->assertJsonPath(
                'data.group.sort_order',
                20
            );
    }
}
