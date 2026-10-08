<?php

namespace App\Actions\User;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetDefaultProfile
{
    public function execute(
        User $user,
        int $profileId
    ): Profile {
        $profile = $user
            ->profiles()
            ->whereKey($profileId)
            ->firstOrFail();

        if ((bool) $profile->pivot->is_default) {
            return $profile;
        }

        DB::connection()->transaction(
            function () use (
                $user,
                $profileId
            ): void {
                DB::table('system.user_profiles')
                    ->where('user_id', $user->getKey())
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                        'updated_at' => now(),
                    ]);

                DB::table('system.user_profiles')
                    ->where('user_id', $user->getKey())
                    ->where('profile_id', $profileId)
                    ->update([
                        'is_default' => true,
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
