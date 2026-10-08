<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function store(Request $request): Response
    {
        $request->merge([
            'email' => mb_strtolower(
                trim((string) $request->input('email'))
            ),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts(
            $throttleKey,
            self::LOGIN_MAX_ATTEMPTS
        )) {
            $retryAfter = RateLimiter::availableIn(
                $throttleKey
            );

            return response()->json(
                [
                    'message' => 'Demasiados intentos de inicio de sesión.',
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => (string) $retryAfter,
                ]
            );
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit(
                $throttleKey,
                self::LOGIN_DECAY_SECONDS
            );

            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function loginThrottleKey(Request $request): string
    {
        return 'login:'.hash(
            'sha256',
            $request->input('email').'|'.($request->ip() ?? 'unknown')
        );
    }
}
