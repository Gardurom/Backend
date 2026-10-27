<?php

namespace Tests\Functional\Auth;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class RoleIndexTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_list_roles(): void
    {
        $this->getJson('/api/roles')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_admin_can_list_roles(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'ROL_ADMIN_SISTEMA');

        $this->actingAs($user);

        $response = $this->getJson('/api/roles');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                    ],
                ],
            ]);

        $codes = collect($response->json('data'))
            ->pluck('code')
            ->all();

        self::assertContains('ROL_ADMIN_SISTEMA', $codes);

        foreach ($response->json('data') as $role) {
            self::assertSame(
                ['id', 'code', 'name'],
                array_keys($role)
            );
        }
    }

    public function test_explicit_deny_overrides_allow(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'ROL_ADMIN_SISTEMA');

        $resolver = \Mockery::mock(PermissionResolver::class);

        $resolver
            ->shouldReceive('allows')
            ->once()
            ->with(
                \Mockery::on(
                    fn ($received): bool =>
                        $received instanceof User
                        && $received->id === $user->id
                ),
                'roles.ver'
            )
            ->andReturn(false);

        $this->app->instance(
            PermissionResolver::class,
            $resolver
        );

        $this->actingAs($user);

        $this->getJson('/api/roles')
            ->assertForbidden();
    }
    public function test_roles_are_ordered_by_code(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'ROL_ADMIN_SISTEMA');

        $this->actingAs($user);

        $response = $this->getJson('/api/roles');

        $response->assertOk();

        $codes = collect($response->json('data'))
            ->pluck('code')
            ->all();

        $expected = $codes;
        sort($expected, SORT_STRING);

        self::assertSame($expected, $codes);
    }

    private function assignRole(User $user, string $code): int
    {
        $roleId = $this->db
            ->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull(
            $roleId,
            "Debe existir el rol {$code}."
        );

        $user->roles()->attach($roleId);

        return (int) $roleId;
    }
}