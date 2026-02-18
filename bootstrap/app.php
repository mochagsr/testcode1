<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ApplyUserPreferences;
use App\Http\Middleware\EnsureFinanceUnlocked;
use App\Http\Middleware\EnsureSemesterOpen;
use App\Http\Middleware\EnsureIdempotentRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'prefs' => ApplyUserPreferences::class,
            'finance.unlocked' => EnsureFinanceUnlocked::class,
            'semester.open' => EnsureSemesterOpen::class,
            'idempotent' => EnsureIdempotentRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $throwable): void {
            Log::channel('alerts')->error($throwable->getMessage(), [
                'exception' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        });
    })->create();
