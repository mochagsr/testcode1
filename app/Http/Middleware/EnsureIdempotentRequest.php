<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $route = optional($request->route())->getName() ?: $request->path();
        $userId = (int) ($request->user()?->id ?? 0);
        $customKey = trim((string) ($request->header('X-Idempotency-Key') ?? $request->input('_idempotency_key', '')));
        $payload = $request->except(['_token', '_method', '_idempotency_key']);
        $fingerprint = $customKey !== ''
            ? $customKey
            : sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $cacheKey = "idempotency:{$userId}:{$route}:{$fingerprint}";

        if (!Cache::add($cacheKey, now()->timestamp, now()->addSeconds(30))) {
            return back()
                ->withErrors(['submit' => __('ui.duplicate_submit_blocked')])
                ->withInput()
                ->withHeaders(['Retry-After' => '3']);
        }

        $response = $next($request);

        // Allow re-submit when request fails (validation/error), keep lock for successful processing.
        if ((int) $response->getStatusCode() >= 400) {
            Cache::forget($cacheKey);
        }

        return $response;
    }
}
