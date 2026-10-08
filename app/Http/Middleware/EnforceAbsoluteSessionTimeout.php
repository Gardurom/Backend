<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionTimeout
{
    private const MAX_SESSION_AGE_SECONDS = 8 * 60 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $authenticatedAt = $request->session()->get(
            'siga_authenticated_at'
        );

        if (! is_int($authenticatedAt)) {
            $request->session()->put(
                'siga_authenticated_at',
                now()->timestamp
            );

            return $next($request);
        }

        if (
            now()->timestamp - $authenticatedAt
            >= self::MAX_SESSION_AGE_SECONDS
        ) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
