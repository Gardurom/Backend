<?php

namespace App\Access;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AccessEligibility
{
    public function allows(User $user): bool
    {
        if ($user->access_status !== 'HABILITADO'
            || $user->id_persona === null
            || !$user->person()->where('estatus', 'ACTIVO')->exists()) {
            return false;
        }

        $restriction = DB::table('system.users')
            ->where('id', $user->getKey())
            ->first([
                'access_security_blocked',
                'access_suspended_until',
                'access_valid_from',
                'access_valid_until',
            ]);

        if ($restriction === null) {
            return false;
        }

        return $this->allowsRestrictions(
            (bool) $restriction->access_security_blocked,
            $restriction->access_suspended_until,
            $restriction->access_valid_from,
            $restriction->access_valid_until,
            CarbonImmutable::now('UTC')
        );
    }

    public function allowsRestrictions(
        bool $securityBlocked,
        ?string $suspendedUntil,
        ?string $validFrom,
        ?string $validUntil,
        CarbonImmutable $now
    ): bool {
        if ($securityBlocked) {
            return false;
        }

        if ($suspendedUntil !== null
            && $now->lessThan(CarbonImmutable::parse($suspendedUntil))) {
            return false;
        }

        if ($validFrom !== null
            && $now->lessThan(CarbonImmutable::parse($validFrom))) {
            return false;
        }

        if ($validUntil !== null
            && !$now->lessThan(CarbonImmutable::parse($validUntil))) {
            return false;
        }

        return true;
    }
}
