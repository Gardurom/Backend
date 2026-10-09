<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\DB;

class RevokeOtherUserSessions
{
    public function execute(
        int $userId,
        string $currentSessionId
    ): int {
        return DB::table('system.sessions')
            ->where('user_id', $userId)
            ->where('id', '<>', $currentSessionId)
            ->delete();
    }
}
