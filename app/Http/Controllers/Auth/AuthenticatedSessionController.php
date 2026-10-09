<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    private const REAUTHENTICATION_MAX_ATTEMPTS = 5;

    private const REAUTHENTICATION_DECAY_SECONDS = 60;

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

        $authenticatedAt = now()->timestamp;

        $request->session()->put(
            'siga_authenticated_at',
            $authenticatedAt
        );

        $request->session()->put(
            'siga_reauthenticated_at',
            $authenticatedAt
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function reauthenticate(Request $request): Response
    {
        $credentials = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        $throttleKey = $this->reauthenticationThrottleKey(
            $request
        );

        if (RateLimiter::tooManyAttempts(
            $throttleKey,
            self::REAUTHENTICATION_MAX_ATTEMPTS
        )) {
            $retryAfter = RateLimiter::availableIn(
                $throttleKey
            );

            return response()->json(
                [
                    'message' => 'Demasiados intentos de reautenticación.',
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => (string) $retryAfter,
                ]
            );
        }

        if (! Hash::check(
            $credentials['password'],
            $user->getAuthPassword()
        )) {
            RateLimiter::hit(
                $throttleKey,
                self::REAUTHENTICATION_DECAY_SECONDS
            );

            throw ValidationException::withMessages([
                'password' => ['La contraseña proporcionada no es válida.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->put(
            'siga_reauthenticated_at',
            now()->timestamp
        );

        return response()->noContent();
    }

    private function reauthenticationThrottleKey(Request $request): string
    {
        return 'reauthentication:'.hash(
            'sha256',
            $request->user()->getAuthIdentifier()
                .'|'.($request->ip() ?? 'unknown')
        );
    }

    private function loginThrottleKey(Request $request): string
    {
        return 'login:'.hash(
            'sha256',
            $request->input('email').'|'.($request->ip() ?? 'unknown')
        );
    }
}
