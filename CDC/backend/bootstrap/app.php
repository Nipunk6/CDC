<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // SEC-022: a missing record looks exactly like another tenant's record (controllers answer those with
        // "JNF not found." / "INF not found."), and no 404 names a model class or echoes the id.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            $missing = $exception->getPrevious();
            if (! $request->is('api/*') || ! $missing instanceof ModelNotFoundException) {
                return null;
            }

            $message = match ($missing->getModel()) {
                \App\Models\Jnf::class => 'JNF not found.',
                \App\Models\Inf::class => 'INF not found.',
                default => 'Not found.',
            };

            return response()->json(['message' => $message], 404);
        });
    })->create();
