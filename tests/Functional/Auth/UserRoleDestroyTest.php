<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;
use Tests\Functional\Auth\Concerns\CreatesEligibleRoleActor;

use Tests\Functional\Auth\Concerns\AssertsRoleActivity;

class UserRoleDestroyTest extends HttpFunctionalTestCase
{
    use AssertsRoleActivity;

    use CreatesEligibleRoleActor;
    public function test_guest_cannot_remove_role(): void
    {
        $this->deleteJson('/api/usuarios/1/roles/1')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_remove_role(): void
    {
        $actor = $this->createEligibleRoleActor();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($actor);

        $this->deleteWithSession($target->id, $roleId)
            ->assertForbidden();

        self::assertSame(
            1,
            $this->assignmentCount($target->id, $roleId)
        );
    }

    public function test_admin_can_remove_ordinary_role(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($admin);

        $this->deleteWithSession($target->id, $roleId)
            ->assertOk()
            ->assertJsonPath('removed', true)
            ->assertJsonPath('role_id', $roleId);

        self::assertSame(
            0,
            $this->assignmentCount($target->id, $roleId)
        );
    }

    public function test_repeated_removal_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($admin);

        $this->deleteWithSession($target->id, $roleId)
            ->assertOk()
            ->assertJsonPath('removed', true);

        $this->deleteWithSession($target->id, $roleId)
            ->assertOk()
            ->assertJsonPath('removed', false);
    }

    public function test_admin_role_cannot_be_removed(): void
    {
        $admin = $this->createAdmin();
        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $this->actingAs($admin);

        $this->deleteWithSession($admin->id, $roleId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        self::assertSame(
            1,
            $this->assignmentCount($admin->id, $roleId)
        );
    }

    public function test_recent_reauthentication_is_required(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($admin);

        $token = 'csrf-user-role-destroy-no-reauth-siga';

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_access_version' => $this->currentRoleActorAccessVersion(),
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->deleteJson(
                '/api/usuarios/'.$target->id.'/roles/'.$roleId
            )
            ->assertStatus(423);

        self::assertSame(
            1,
            $this->assignmentCount($target->id, $roleId)
        );
    }

    public function test_missing_user_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->deleteWithSession(
            2147483647,
            $this->roleId('ROL_CONSULTA_PERSONAS')
        )->assertNotFound();
    }

    public function test_missing_role_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->deleteWithSession(
            $target->id,
            2147483647
        )->assertNotFound();
    }

    public function test_auditoria_http_retiro_unico_con_actor(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');
        $target->roles()->attach($roleId);
        $this->actingAs($admin);

        $this->deleteWithSession((int) $target->id, $roleId)
            ->assertOk()->assertJsonPath('removed', true);
        $this->deleteWithSession((int) $target->id, $roleId)
            ->assertOk()->assertJsonPath('removed', false);

        $this->assertUserRoleActivity(
            (int) $admin->id, (int) $target->id, $roleId,
            'ROL_CONSULTA_PERSONAS', false
        );
    }

    private function createAdmin(): User
    {
        $user = $this->createEligibleRoleActor();

        $user->roles()->attach(
            $this->roleId('ROL_ADMIN_SISTEMA')
        );

        return $user;
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

    private function assignmentCount(int $userId, int $roleId): int
    {
        return $this->db
            ->table('system.user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->count();
    }

    private function deleteWithSession(int $userId, int $roleId)
    {
        $token = 'csrf-user-role-destroy-siga';

        return $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_access_version' => $this->currentRoleActorAccessVersion(),
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->deleteJson(
                '/api/usuarios/'.$userId.'/roles/'.$roleId
            );
    }
}