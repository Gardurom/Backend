<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\DB;

class ListUserSessions
{
    /**
     * @return array<int, array{
     *     id: string,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     last_activity: int,
     *     current: bool
     * }>
     */
    public function execute(
        int $userId,
        string $currentSessionId
    ): array {
        $idleExpirationThreshold = now()->timestamp
            - ((int) config('session.lifetime') * 60);

        $sessions = DB::table('system.sessions')
            ->select([
                'id',
                'ip_address',
                'user_agent',
                'last_activity',
            ])
            ->where('user_id', $userId)
            ->where('last_activity', '>', $idleExpirationThreshold)
            ->orderByDesc('last_activity')
            ->get();

        return $sessions
            ->map(function (object $session) use ($currentSessionId): array {
                $sessionId = (string) $session->id;

                return [
                    'id' => $this->publicId($sessionId),
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => (int) $session->last_activity,
                    'current' => hash_equals(
                        $currentSessionId,
                        $sessionId
                    ),
                ];
            })
            ->all();
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
