<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignRole
{
    public function execute(int $userId, int $roleId, ?int $actorId = null): bool
    {
        return DB::transaction(function () use (
            $userId,
            $roleId,
            $actorId
        ): bool {
            $user = User::query()->findOrFail($userId);

            $role = Role::query()->findOrFail($roleId);

            if ($role->code === 'ROL_ADMIN_SISTEMA') {
                throw new \DomainException(
                    'El rol Administrador del Sistema requiere un procedimiento especial.'
                );
            }

            $inserted = DB::table('system.user_roles')
                ->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($inserted === 1) {
                app(RecordUserRoleActivity::class)->record(
                    $actorId,
                    (int) $user->id,
                    (int) $role->id,
                    (string) $role->code,
                    true
                );
            }

            return $inserted === 1;
        });
    }
}