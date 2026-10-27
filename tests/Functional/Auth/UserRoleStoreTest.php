<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class UserRoleStoreTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_assign_role(): void
    {
        $this->postJson('/api/usuarios/1/roles', [
            'role_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_assign_role(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor);

        $this->postWithSession(
            $target->id,
            $this->roleId('ROL_CONSULTA_PERSONAS')
        )->assertForbidden();
    }

    public function test_admin_can_assign_ordinary_role(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->actingAs($admin);

        $this->postWithSession($target->id, $roleId)
            ->assertCreated()
            ->assertJsonPath('assigned', true)
            ->assertJsonPath('role_id', $roleId);

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_duplicate_assignment_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->actingAs($admin);

        $this->postWithSession($target->id, $roleId)
            ->assertCreated();

        $this->postWithSession($target->id, $roleId)
            ->assertOk()
            ->assertJsonPath('assigned', false);

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_admin_role_cannot_be_assigned_through_api(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $this->actingAs($admin);

        $this->postWithSession($target->id, $roleId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_recent_reauthentication_is_required(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $token = 'csrf-user-role-no-reauth-siga';

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/usuarios/'.$target->id.'/roles', [
                'role_id' => $this->roleId('ROL_CONSULTA_PERSONAS'),
            ])
            ->assertStatus(423);
    }

    public function test_missing_target_user_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->postWithSession(
            2147483647,
            $this->roleId('ROL_CONSULTA_PERSONAS')
        )->assertNotFound();
    }

    public function test_missing_role_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->postWithSession(
            $target->id,
            2147483647
        )->assertNotFound();
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();

        $user->roles()->attach(
            $this->roleId('ROL_ADMIN_SISTEMA')
        );

        return $user;
    }

    private function roleId(string $code): int
    {
        $id = $this->db->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull($id, "No existe {$code}.");

        return (int) $id;
    }

    private function postWithSession(int $userId, int $roleId)
    {
        $token = 'csrf-user-role-store-siga';

        return $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/usuarios/'.$userId.'/roles', [
                'role_id' => $roleId,
            ]);
    }
}