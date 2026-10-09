<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SEC-008: trust the client IP forwarded by the Next.js server only when its signature verifies. Global and
        // first, so rate limits, logs and audits all see the same IP.
        $middleware->prepend(\App\Http\Middleware\TrustSignedClientIp::class);

        // SEC-007: reject requests for any other Host. Read from config at request time; each name is matched exactly.
        $middleware->trustHosts(at: fn (): array => array_map(
            fn (string $host): string => '^'.preg_quote($host).'$',
            (array) config('app.trusted_hosts', [])
        ));

        // SEC-016: nosniff, X-Frame-Options, Referrer-Policy everywhere; no-store for authenticated and signed responses.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null;
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect('/');
        });
    })->create();
