<?php

namespace App\Actions\User;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnassignProfile
{
    public function execute(
        User $user,
        int $profileId
    ): void {
        Profile::query()
            ->findOrFail($profileId);

        DB::table('system.user_profiles')
            ->where('user_id', $user->getKey())
            ->where('profile_id', $profileId)
            ->delete();
    }
}
