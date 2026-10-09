<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentReauthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $this->reauthenticationRequired();
        }

        $reauthenticatedAt = $request->session()->get(
            'siga_reauthenticated_at'
        );

        $now = now()->timestamp;

        if (
            ! is_int($reauthenticatedAt)
            || $reauthenticatedAt > $now
            || $now - $reauthenticatedAt
                >= (int) config('auth.reauthentication_timeout')
        ) {
            return $this->reauthenticationRequired();
        }

        return $next($request);
    }

    private function reauthenticationRequired(): Response
    {
        return response()->json(
            ['message' => 'Reauthentication required.'],
            Response::HTTP_LOCKED
        );
    }
}
