<?php

namespace App\Actions\User;

use App\Models\Profile;
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
            ])
            ->orderByPivot('is_default', 'desc')
            ->orderBy('system.profiles.name')
            ->get()
            ->map(
                static fn (Profile $profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'code' => $profile->code,
                    'is_default' => (bool) $profile->pivot->is_default,
                ]
            )
            ->values();
    }
}
