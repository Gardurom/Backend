<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Functional\HttpFunctionalTestCase;

class UserIndexTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/usuarios')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/usuarios')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_users_without_sensitive_data(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.listado@siga.test',
        ]);

        $target = User::factory()->create([
            'name' => 'USUARIO CONSULTA',
            'email' => 'usuario.listado@siga.test',
            'password' => Hash::make('ClaveSegura123456!'),
        ]);

        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');

        $this->actingAs($admin);

        $response = $this->getJson('/api/usuarios');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'id_persona',
                        'name',
                        'email',
                        'created_at',
                        'person',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'last_page',
                    'total',
                ],
            ]);

        $users = collect($response->json('data'));

        $listed = $users->firstWhere('id', $target->id);

        self::assertNotNull($listed);
        self::assertSame(
            'usuario.listado@siga.test',
            $listed['email']
        );

        foreach ($users as $entry) {
            self::assertArrayNotHasKey('password', $entry);
            self::assertArrayNotHasKey('remember_token', $entry);
            self::assertArrayNotHasKey('email_verified_at', $entry);
        }

        self::assertSame(20, $response->json('meta.per_page'));
    }

    public function test_explicit_deny_overrides_allow_for_user_listing(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'ROL_ADMIN_SISTEMA');
        $this->assignRole($user, 'ROL_AUDITOR');

        $permissionId = $this->db
            ->table('system.permissions')
            ->where('code', 'usuarios.ver')
            ->value('id');

        $auditorId = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_AUDITOR')
            ->value('id');

        self::assertNotNull($permissionId);
        self::assertNotNull($auditorId);

        $this->db
            ->table('system.role_permissions')
            ->insert([
                'role_id' => $auditorId,
                'permission_id' => $permissionId,
                'effect' => 'DENY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $this->getJson('/api/usuarios')
            ->assertForbidden();
    }

    public function test_user_listing_is_paginated(): void
    {
        $admin = User::factory()->create();

        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');

        for ($index = 1; $index <= 21; $index++) {
            User::factory()->create([
                'email' => sprintf(
                    'usuario.pagina.%02d@siga.test',
                    $index
                ),
            ]);
        }

        $this->actingAs($admin);

        $first = $this->getJson('/api/usuarios?page=1');
        $second = $this->getJson('/api/usuarios?page=2');

        $first->assertOk();
        $second->assertOk();

        self::assertCount(20, $first->json('data'));
        self::assertGreaterThanOrEqual(
            2,
            count($second->json('data'))
        );

        self::assertSame(
            1,
            $first->json('meta.current_page')
        );

        self::assertSame(
            2,
            $second->json('meta.current_page')
        );
    }

    private function assignRole(User $user, string $code): void
    {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "No existe el rol {$code}."
        );

        $user->roles()->attach($roleId);
    }
}