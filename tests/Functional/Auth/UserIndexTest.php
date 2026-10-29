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

    public function test_user_listing_includes_assigned_roles(): void
    {
        $admin = User::factory()->create();
        $withRole = User::factory()->create();
        $withoutRole = User::factory()->create();

        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');
        $this->assignRole($withRole, 'ROL_CONSULTA_PERSONAS');

        $expectedRole = $this->db
            ->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->first(['id', 'code', 'name']);

        self::assertNotNull($expectedRole);

        $this->actingAs($admin);

        $response = $this->getJson('/api/usuarios');

        $response->assertOk();

        $users = collect($response->json('data'));

        $assigned = $users->firstWhere('id', $withRole->id);
        $unassigned = $users->firstWhere('id', $withoutRole->id);

        self::assertNotNull($assigned);
        self::assertNotNull($unassigned);

        self::assertSame(
            [
                [
                    'id' => (int) $expectedRole->id,
                    'code' => $expectedRole->code,
                    'name' => $expectedRole->name,
                ],
            ],
            $assigned['roles']
        );

        self::assertSame([], $unassigned['roles']);

        foreach ($users as $entry) {
            self::assertArrayHasKey('roles', $entry);

            foreach ($entry['roles'] as $role) {
                self::assertSame(
                    ['id', 'code', 'name'],
                    array_keys($role)
                );
            }
        }
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

    public function test_user_listing_can_be_filtered_by_role(): void
    {
        $admin = User::factory()->create();
        $withRole = User::factory()->create();
        $withoutRole = User::factory()->create();

        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');
        $this->assignRole($withRole, 'ROL_CONSULTA_PERSONAS');

        $roleId = (int) $this->db->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        $this->actingAs($admin);

        $response = $this->getJson("/api/usuarios?role_id={$roleId}");

        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        self::assertContains($withRole->id, $ids);
        self::assertNotContains($withoutRole->id, $ids);
        self::assertNotContains($admin->id, $ids);
        $expectedTotal = $this->db
            ->table('system.user_roles')
            ->where('role_id', $roleId)
            ->count();

        self::assertSame(
            $expectedTotal,
            $response->json('meta.total')
        );
    }

    public function test_role_filter_paginates_only_matching_users(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');

        $roleId = (int) $this->db->table('system.roles')
            ->where('code', 'ROL_CONSULTA_PERSONAS')
            ->value('id');

        for ($index = 0; $index < 21; $index++) {
            $user = User::factory()->create();
            $user->roles()->attach($roleId);
        }

        User::factory()->create();

        $this->actingAs($admin);

        $first = $this->getJson("/api/usuarios?role_id={$roleId}&page=1");
        $second = $this->getJson("/api/usuarios?role_id={$roleId}&page=2");

        $first->assertOk();
        $second->assertOk();

        $expectedTotal = $this->db
            ->table('system.user_roles')
            ->where('role_id', $roleId)
            ->count();

        $expectedPages = (int) ceil($expectedTotal / 20);

        self::assertGreaterThanOrEqual(21, $expectedTotal);
        self::assertCount(20, $first->json('data'));

        self::assertCount(
            min(20, $expectedTotal - 20),
            $second->json('data')
        );

        self::assertSame(
            $expectedTotal,
            $first->json('meta.total')
        );

        self::assertSame(
            $expectedPages,
            $first->json('meta.last_page')
        );

        self::assertSame(
            2,
            $second->json('meta.current_page')
        );

        foreach (array_merge($first->json('data'), $second->json('data')) as $entry) {
            self::assertContains(
                $roleId,
                array_column($entry['roles'], 'id')
            );
        }
    }

    public function test_role_filter_rejects_invalid_or_unknown_ids(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'ROL_ADMIN_SISTEMA');

        $this->actingAs($admin);

        $this->getJson('/api/usuarios?role_id=invalido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        $this->getJson('/api/usuarios?role_id=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        $this->getJson('/api/usuarios?role_id=2147483647')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
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
