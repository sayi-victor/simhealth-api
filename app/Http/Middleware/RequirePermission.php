<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $user->loadMissing('roles.permissions');

        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException("Missing required permission: {$permission}");
        }

        return $next($request);
    }
}
