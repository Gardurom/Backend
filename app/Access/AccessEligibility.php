<?php

namespace App\Access;

use App\Models\User;

final class AccessEligibility
{
    public function allows(User $user): bool
    {
        return $user->access_status === 'HABILITADO'
            && $user->id_persona !== null
            && $user->person()->where('estatus', 'ACTIVO')->exists();
    }
}
