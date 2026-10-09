<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\DB;

class RevokeUserSession
{
    public function execute(
        int $userId,
        string $currentSessionId,
        string $publicSessionId
    ): bool {
        $sessions = DB::table('system.sessions')
            ->select('id')
            ->where('user_id', $userId)
            ->where('id', '<>', $currentSessionId)
            ->get();

        foreach ($sessions as $session) {
            $sessionId = (string) $session->id;

            if (! hash_equals(
                $this->publicId($sessionId),
                $publicSessionId
            )) {
                continue;
            }

            return DB::table('system.sessions')
                ->where('user_id', $userId)
                ->where('id', $sessionId)
                ->where('id', '<>', $currentSessionId)
                ->delete() === 1;
        }

        return false;
    }

    private function publicId(string $sessionId): string
    {
        return hash_hmac(
            'sha256',
            $sessionId,
            (string) config('app.key')
        );
    }
}
