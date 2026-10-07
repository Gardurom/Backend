<?php

namespace App\Http\Middleware;

use App\Authorization\PermissionResolver;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private PermissionResolver $resolver
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! $this->resolver->allows($user, $permission)
        ) {
            abort(403);
        }

        return $next($request);
    }
}
