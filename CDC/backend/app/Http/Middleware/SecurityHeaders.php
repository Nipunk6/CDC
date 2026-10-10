<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-016: hardening headers on every response Laravel sends. Responses to authenticated (bearer) and signed-link
 * requests carry personal data, so browsers and proxies must not store them. Static `/storage/*` files are served by
 * the web server, not Laravel, and need the same headers there (handover).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        if ($request->bearerToken() !== null || $request->query->has('signature')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        // PHP adds "X-Powered-By: PHP/x.y" when expose_php is On (production should set expose_php=Off as well).
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
