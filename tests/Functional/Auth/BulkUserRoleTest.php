<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Tests\Functional\HttpFunctionalTestCase;

class BulkUserRoleTest extends HttpFunctionalTestCase
{
    private const ENDPOINT = '/api/usuarios/roles/masivo';

    public function test_guest_cannot_assign_roles(): void
    {
        $this->postJson(self::ENDPOINT, [
            'role_id' => 1,
            'user_ids' => [1],
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $actor = User::factory()->create();
        $target = $this->createLinkedUser();

        $this->actingAs($actor);

        $this->postAuthorized([
            'role_id' => $this->roleId('ROL_CONSULTA_PERSONAS'),
            'user_ids' => [$target->id],
        ])->assertForbidden();
    }

    public function test_reauthentication_is_required(): void
    {
        $this->actingAs($this->createAdmin());

        $token = 'csrf-bulk-no-reauth-siga';

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(self::ENDPOINT, [
                'role_id' => $this->roleId('ROL_CONSULTA_PERSONAS'),
                'user_ids' => [$this->createLinkedUser()->id],
            ])->assertStatus(423);
    }

    public function test_admin_assigns_role_to_multiple_linked_users(): void
    {
        $first = $this->createLinkedUser();
        $second = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('result.selected', 2)
            ->assertJsonPath('result.assigned', 2)
            ->assertJsonPath('result.already_assigned', 0);

        self::assertSame(
            2,
            $this->db->table('system.user_roles')
                ->where('role_id', $roleId)
                ->whereIn('user_id', [$first->id, $second->id])
                ->count()
        );
    }

    public function test_existing_assignments_are_not_duplicated(): void
    {
        $first = $this->createLinkedUser();
        $second = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $first->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('result.selected', 2)
            ->assertJsonPath('result.assigned', 1)
            ->assertJsonPath('result.already_assigned', 1);
    }

    public function test_system_admin_role_is_rejected(): void
    {
        $target = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$target->id],
        ])
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

    public function test_invalid_user_rejects_entire_batch(): void
    {
        $target = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$target->id, 2147483647],
        ])->assertUnprocessable();

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_unlinked_user_rejects_entire_batch(): void
    {
        $linked = $this->createLinkedUser();
        $unlinked = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$linked->id, $unlinked->id],
        ])->assertUnprocessable();

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->where('user_id', $linked->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->roles()->attach(
            $this->roleId('ROL_ADMIN_SISTEMA')
        );

        return $admin;
    }

    private function createLinkedUser(): User
    {
        $personId = $this->db
            ->table('institutional.persons')
            ->insertGetId([
                'nombres' => 'PERSONA PRUEBA MASIVA',
                'apellido_paterno' => 'SIGA',
            ], 'id_persona');

        return User::factory()->create([
            'id_persona' => $personId,
        ]);
    }

    private function roleId(string $code): int
    {
        $id = $this->db->table('system.roles')
            ->where('code', $code)
            ->value('id');

        self::assertNotNull($id, "No existe {$code}.");

        return (int) $id;
    }

    private function postAuthorized(array $data)
    {
        $token = 'csrf-bulk-role-siga';

        return $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(self::ENDPOINT, $data);
    }
}
