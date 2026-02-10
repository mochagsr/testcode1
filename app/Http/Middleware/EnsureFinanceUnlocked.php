<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceUnlocked
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Admin can always bypass finance lock.
        if ($user->role !== 'admin' && $user->finance_locked) {
            return back()->withErrors([
                'finance' => 'Finance actions are locked for your account.',
            ]);
        }

        return $next($request);
    }
}
