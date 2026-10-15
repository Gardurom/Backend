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
}
