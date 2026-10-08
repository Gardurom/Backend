<?php

namespace App\Actions\User;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignProfile
{
    public function execute(
        User $user,
        int $profileId
    ): Profile {
        Profile::query()
            ->findOrFail($profileId);

        DB::connection()->transaction(
            function () use (
                $user,
                $profileId
            ): void {
                DB::table('system.user_profiles')
                    ->insertOrIgnore([
                        'user_id' => $user->getKey(),
                        'profile_id' => $profileId,
                        'is_default' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        );

        return $user
            ->profiles()
            ->whereKey($profileId)
            ->firstOrFail();
    }
}
