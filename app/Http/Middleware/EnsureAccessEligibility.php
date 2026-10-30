<?php

namespace App\Http\Middleware;

use App\Access\AccessEligibility;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccessEligibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $fresh = $user instanceof User ? $user->fresh() : null;
        $valid = $fresh !== null && $request->hasSession()
            && app(AccessEligibility::class)->allows($fresh)
            && (string) $request->session()->get('siga_access_version', '')
                === (string) $fresh->access_version;
        if (!$valid) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            return response()->json([
                'message' => 'Debe iniciar sesión con una cuenta habilitada.',
                'code' => 'ACCESS_REVOKED',
            ], 401);
        }
        $request->setUserResolver(fn () => $fresh);
        try {
            return $next($request);
        } catch (\Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[0] ?? '') === '23514'
                && str_contains($error->getMessage(), 'SIGA_LAST_ADMIN')) {
                return response()->json([
                    'message' => 'La operación dejaría SIGA sin administrador habilitado.',
                    'code' => 'LAST_ENABLED_ADMIN',
                ], 409);
            }
            throw $error;
        }
    }
}
