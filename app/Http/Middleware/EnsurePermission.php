<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $resolvedPermissions = method_exists($user, 'resolvedPermissions')
            ? (array) $user->resolvedPermissions()
            : (array) config('rbac.roles.'.(string) ($user->role ?? 'user'), []);
        $requiredPermission = strtolower(trim($permission));

        if (in_array('*', $resolvedPermissions, true) || in_array($requiredPermission, $resolvedPermissions, true)) {
            return $next($request);
        }

        abort(403, 'Permission denied for action: ' . $requiredPermission);
    }
}
