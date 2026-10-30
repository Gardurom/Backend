<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveRole
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
                    'El rol Administrador del Sistema no puede retirarse mediante esta operacion.'
                );
            }

            $deleted = DB::table('system.user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->delete();

            if ($deleted > 0) {
                app(RecordUserRoleActivity::class)->record(
                    $actorId,
                    (int) $user->id,
                    (int) $role->id,
                    (string) $role->code,
                    false
                );
            }

            return $deleted > 0;
        });
    }
}