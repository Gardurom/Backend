<?php

namespace Tests\Functional\Auth;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Functional\HttpFunctionalTestCase;
use Tests\Functional\Auth\Concerns\CreatesEligibleRoleActor;

class BulkUserRoleRemovalTest extends HttpFunctionalTestCase
{
    use CreatesEligibleRoleActor;
    private const ENDPOINT = '/api/usuarios/roles/retiro-masivo';

    public function test_guest_cannot_remove_roles_in_bulk(): void
    {
        $this->postJson(self::ENDPOINT, [
            'role_id' => 1,
            'user_ids' => [1],
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_remove_roles_in_bulk(): void
    {
        $actor = $this->createEligibleRoleActor();

        $this->actingAs($actor);

        $this->postAuthorized([
            'role_id' => $this->roleId('ROL_CONSULTA_PERSONAS'),
            'user_ids' => [$actor->id],
        ])->assertForbidden();
    }

    public function test_authorized_admin_removes_only_requested_role(): void
    {
        $target = $this->createLinkedUser();
        $consultationRoleId = $this->roleId('ROL_CONSULTA_PERSONAS');
        $managementRoleId = $this->roleId('ROL_GESTOR_PERSONAS');

        $target->roles()->attach([
            $consultationRoleId,
            $managementRoleId,
        ]);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $consultationRoleId,
            'user_ids' => [$target->id],
        ])
            ->assertOk()
            ->assertJsonPath('result.selected', 1)
            ->assertJsonPath('result.removed', 1)
            ->assertJsonPath('result.already_absent', 0)
            ->assertJsonPath('result.removed_user_ids', [$target->id])
            ->assertJsonPath('result.already_absent_user_ids', []);

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $consultationRoleId)
                ->count()
        );

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $managementRoleId)
                ->count()
        );
    }

    public function test_reauthentication_is_required_for_bulk_removal(): void
    {
        $this->actingAs($this->createAdmin());

        $token = 'csrf-bulk-removal-no-reauth';

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_access_version' => $this->currentRoleActorAccessVersion(),
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(self::ENDPOINT, [
                'role_id' => $this->roleId('ROL_CONSULTA_PERSONAS'),
                'user_ids' => [$this->createLinkedUser()->id],
            ])
            ->assertStatus(423);
    }

    public function test_protected_system_admin_role_cannot_be_removed_in_bulk(): void
    {
        $target = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_ADMIN_SISTEMA');

        $target->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$target->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_absent_roles_are_reported_without_affecting_existing_roles(): void
    {
        $first = $this->createLinkedUser();
        $second = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');
        $otherRoleId = $this->roleId('ROL_GESTOR_PERSONAS');

        $first->roles()->attach([$roleId, $otherRoleId]);
        $second->roles()->attach($otherRoleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('result.selected', 2)
            ->assertJsonPath('result.removed', 1)
            ->assertJsonPath('result.already_absent', 1)
            ->assertJsonPath('result.removed_user_ids', [$first->id])
            ->assertJsonPath('result.already_absent_user_ids', [$second->id]);

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->where('role_id', $roleId)
                ->whereIn('user_id', [$first->id, $second->id])
                ->count()
        );

        self::assertSame(
            2,
            $this->db->table('system.user_roles')
                ->where('role_id', $otherRoleId)
                ->whereIn('user_id', [$first->id, $second->id])
                ->count()
        );
    }

    public function test_invalid_user_rejects_entire_removal_batch(): void
    {
        $target = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$target->id, 2147483647],
        ])->assertUnprocessable();

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_unlinked_user_rejects_entire_removal_batch(): void
    {
        $linked = $this->createLinkedUser();
        $unlinked = User::factory()->create();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $linked->roles()->attach($roleId);
        $unlinked->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$linked->id, $unlinked->id],
        ])->assertUnprocessable();

        self::assertSame(
            2,
            $this->db->table('system.user_roles')
                ->where('role_id', $roleId)
                ->whereIn('user_id', [$linked->id, $unlinked->id])
                ->count()
        );
    }

    public function test_duplicate_user_rejects_entire_removal_batch(): void
    {
        $target = $this->createLinkedUser();
        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $target->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$target->id, $target->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.1']);

        self::assertSame(
            1,
            $this->db->table('system.user_roles')
                ->where('user_id', $target->id)
                ->where('role_id', $roleId)
                ->count()
        );
    }

    public function test_bulk_removal_registers_one_audit_activity_per_removed_role(): void
    {
        $first = $this->createLinkedUser();
        $second = $this->createLinkedUser();
        $withoutRole = $this->createLinkedUser();

        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $first->roles()->attach($roleId);
        $second->roles()->attach($roleId);

        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [
                $first->id,
                $second->id,
                $withoutRole->id,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('result.selected', 3)
            ->assertJsonPath('result.removed', 2)
            ->assertJsonPath('result.already_absent', 1)
            ->assertJsonPath(
                'result.removed_user_ids',
                [$first->id, $second->id]
            )
            ->assertJsonPath(
                'result.already_absent_user_ids',
                [$withoutRole->id]
            );

        $activities = $this->db
            ->table('system.activities')
            ->where('entidad', 'USUARIO_ROL')
            ->whereIn('id_entidad', [
                (string) $first->id,
                (string) $second->id,
                (string) $withoutRole->id,
            ])
            ->orderBy('id_entidad')
            ->get();

        self::assertCount(2, $activities);

        foreach ($activities as $activity) {
            self::assertSame(
                (int) $admin->id,
                (int) $activity->id_usuario
            );

            self::assertSame(
                'ACTUALIZACION',
                $activity->accion
            );

            self::assertContains(
                $activity->id_entidad,
                [(string) $first->id, (string) $second->id]
            );

            self::assertSame(
                [
                    'role_id' => $roleId,
                    'role_code' => 'ROL_CONSULTA_PERSONAS',
                ],
                json_decode(
                    $activity->datos_anteriores,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );

            self::assertSame(
                [
                    'role_id' => null,
                    'role_code' => null,
                ],
                json_decode(
                    $activity->datos_nuevos,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );

            self::assertSame(
                ['role_id', 'role_code'],
                json_decode(
                    $activity->campos_modificados,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );

            self::assertNotNull($activity->fecha_actividad);
        }

        self::assertFalse(
            $activities->contains(
                fn ($activity): bool => $activity->id_entidad === (string) $withoutRole->id
            )
        );
    }

    public function test_audit_failure_rolls_back_entire_removal_batch(): void
    {
        $first = $this->createLinkedUser();
        $second = $this->createLinkedUser();

        $roleId = $this->roleId('ROL_CONSULTA_PERSONAS');

        $first->roles()->attach($roleId);
        $second->roles()->attach($roleId);

        $this->actingAs($this->createAdmin());

        // Simula un error tras ejecutar la inserción de auditoría.
        // La transacción debe revertir también las retiradas del lote.
        DB::connection()->listen(
            function (QueryExecuted $query): void {
                $sql = strtolower($query->sql);

                if (
                    str_contains($sql, 'insert')
                    && str_contains($sql, 'system')
                    && str_contains($sql, 'activities')
                ) {
                    throw new \RuntimeException(
                        'Fallo simulado de auditoria de retirada masiva.'
                    );
                }
            }
        );

        $this->postAuthorized([
            'role_id' => $roleId,
            'user_ids' => [$first->id, $second->id],
        ])->assertStatus(500);

        // Ambos usuarios deben conservar el rol.
        self::assertSame(
            2,
            $this->db->table('system.user_roles')
                ->where('role_id', $roleId)
                ->whereIn('user_id', [$first->id, $second->id])
                ->count()
        );

        // Tampoco debe persistir una actividad incompleta.
        self::assertSame(
            0,
            $this->db->table('system.activities')
                ->where('entidad', 'USUARIO_ROL')
                ->whereIn('id_entidad', [
                    (string) $first->id,
                    (string) $second->id,
                ])
                ->count()
        );
    }

    private function createAdmin(): User
    {
        $admin = $this->createEligibleRoleActor();

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
                'nombres' => 'PERSONA PRUEBA RETIRADA MASIVA',
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
        $token = 'csrf-bulk-role-removal-siga';

        return $this->withCredentials()
            ->withHeader('Origin', 'http://localhost')
            ->withSession([
                '_token' => $token,
                'siga_authenticated_at' => now()->timestamp,
                'siga_access_version' => $this->currentRoleActorAccessVersion(),
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(self::ENDPOINT, $data);
    }
}
