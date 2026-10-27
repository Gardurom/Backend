<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveRole
{
    public function execute(int $userId, int $roleId): bool
    {
        return DB::transaction(function () use (
            $userId,
            $roleId
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

            return $deleted > 0;
        });
    }
}