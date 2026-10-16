<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Collection;

class ListUserProfiles
{
    public function execute(User $user): Collection
    {
        return $user
            ->profiles()
            ->select([
                'system.profiles.id',
                'system.profiles.name',
                'system.profiles.code',
                'system.profiles.profile_group_id',
            ])
            ->with([
                'profileGroup:id,name,code,sort_order',
            ])
            ->orderByPivot('is_default', 'desc')
            ->orderBy('system.profiles.name')
            ->get();
    }
}
