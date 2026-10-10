<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-008 / QA N-1. NextAuth logs users in from the Next.js server, so every login reaches Laravel from that one
 * server IP. The Next server therefore forwards the real client IP in `X-CDC-Client-IP`, with a timestamp and an
 * HMAC-SHA256 signature over "ip|timestamp" made with the shared INTERNAL_PROXY_SECRET. Only a valid, fresh signature
 * makes Laravel use that IP (for rate limits, logs and audits); anything else keeps the connection's own address.
 *
 * `client_ip_source` request attribute: `signed` (trusted forwarded IP), `direct` (no forwarded header, or the
 * feature is off), `invalid_signature` (a forwarded header was sent but did not verify — it is ignored).
 */
class TrustSignedClientIp
{
    public const HEADER_IP = 'X-CDC-Client-IP';

    public const HEADER_TIMESTAMP = 'X-CDC-Client-IP-Ts';

    public const HEADER_SIGNATURE = 'X-CDC-Client-IP-Sig';

    public function handle(Request $request, Closure $next): Response
    {
        $source = 'direct';
        $ip = $request->headers->get(self::HEADER_IP);
        $secret = (string) config('services.internal_proxy.secret', '');

        if ($ip !== null && $secret !== '') {
            if ($this->verified($request, $ip, $secret)) {
                $request->server->set('REMOTE_ADDR', $ip);
                $source = 'signed';
            } else {
                $source = 'invalid_signature';
            }
        }

        $request->attributes->set('client_ip_source', $source);

        return $next($request);
    }

    private function verified(Request $request, string $ip, string $secret): bool
    {
        $timestamp = (string) $request->headers->get(self::HEADER_TIMESTAMP, '');
        $signature = (string) $request->headers->get(self::HEADER_SIGNATURE, '');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($timestamp) || $signature === '') {
            return false;
        }

        $skew = (int) config('services.internal_proxy.max_skew_seconds', 120);
        if (abs(now()->getTimestamp() - (int) $timestamp) > $skew) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $ip.'|'.$timestamp, $secret), strtolower($signature));
    }
}
