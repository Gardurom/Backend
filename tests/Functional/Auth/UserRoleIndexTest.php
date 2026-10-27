<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class UserRoleIndexTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_list_user_roles(): void
    {
        $this->getJson('/api/usuarios/1/roles')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_roles(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor);

        $this->getJson('/api/usuarios/'.$target->id.'/roles')
            ->assertForbidden();
    }

    public function test_admin_can_list_assigned_roles(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_GESTOR_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($admin);

        $response = $this->getJson(
            '/api/usuarios/'.$target->id.'/roles'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $roleId)
            ->assertJsonPath(
                'data.0.code',
                'ROL_GESTOR_PERSONAS'
            );

        self::assertSame(
            ['id', 'code', 'name'],
            array_keys($response->json('data.0'))
        );
    }

    public function test_does_not_expose_other_users_roles(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $other = User::factory()->create();

        $roleId = $this->roleId('ROL_GESTOR_PERSONAS');

        $other->roles()->attach($roleId);

        $this->actingAs($admin);

        $this->getJson(
            '/api/usuarios/'.$target->id.'/roles'
        )
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_user_without_roles_returns_empty_list(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->getJson(
            '/api/usuarios/'.$target->id.'/roles'
        )
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_missing_user_returns_not_found(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->getJson(
            '/api/usuarios/2147483647/roles'
        )->assertNotFound();
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->roles()->attach(
            $this->roleId('ROL_ADMIN_SISTEMA')
        );

        return $admin;
    }

    private function roleId(string $code): int
    {
        $id = $this->db
            ->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull($id, "No existe {$code}.");

        return (int) $id;
    }
}