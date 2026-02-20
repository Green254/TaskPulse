<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureUserIsNotSuspended;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php', // This will contain protected routes
    )

->withMiddleware(function (Middleware $middleware): void {
    // Enable Sanctum stateful middleware only when using cookie-based SPA auth.
    if ((bool) env('SANCTUM_USE_STATEFUL_MIDDLEWARE', false)) {
        $middleware->append(EnsureFrontendRequestsAreStateful::class);
    }

    $middleware->redirectGuestsTo(function (Request $request) {
        if ($request->is('api/*')) {
            return null;
        }

        return '/';
    });

    // Route middleware (like 'auth', 'role:admin', etc.)
    $middleware->alias([
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'active_user' => EnsureUserIsNotSuspended::class,
    ]);
})

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
