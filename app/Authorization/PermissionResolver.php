<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionResolver
{
    public function resolve(
        User $user,
        string $permission
    ): PermissionDecision {
        $effects = DB::table('system.user_roles as ur')
            ->join(
                'system.role_permissions as rp',
                'rp.role_id',
                '=',
                'ur.role_id'
            )
            ->join(
                'system.permissions as p',
                'p.id',
                '=',
                'rp.permission_id'
            )
            ->where(
                'ur.user_id',
                $user->getKey()
            )
            ->where(
                'p.code',
                $permission
            )
            ->pluck('rp.effect');

        if ($effects->contains('DENY')) {
            return PermissionDecision::DENY;
        }

        if ($effects->contains('ALLOW')) {
            return PermissionDecision::ALLOW;
        }

        return PermissionDecision::NO_RULE;
    }

    public function allows(
        User $user,
        string $permission
    ): bool {
        return $this
            ->resolve($user, $permission)
            ->allows();
    }
}
