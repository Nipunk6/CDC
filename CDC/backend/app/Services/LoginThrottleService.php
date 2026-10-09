<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Per-account login backoff (SEC-008 / QA N-1; replaces the 10/min hard lock of QA F-016).
 *
 * - Per account + client IP: the first 5 wrong passwords are free; after that each attempt from that IP must wait
 *   1, 2, 4 … seconds after the previous failure, capped at 60 s. An attacker hammering from one IP never locks the
 *   real user out on their own IP.
 * - Per account across IPs: once wrong passwords come from 5 or more different IPs (distributed guessing), every IP
 *   waits 1, 2, 4 … seconds after the account's last failure, capped at 30 s.
 * - A correct password clears the account + IP counter. Failures are forgotten 15 minutes after the last one.
 *
 * Cache keys hold hashes only (no roll numbers, emails or IPs).
 */
class LoginThrottleService
{
    public const FREE_FAILURES_PER_IP = 5;

    public const MAX_WAIT_PER_IP = 60;

    public const DISTINCT_IPS_FOR_ACCOUNT_BACKOFF = 5;

    public const MAX_WAIT_PER_ACCOUNT = 30;

    public const WINDOW_SECONDS = 900;

    /** Seconds the caller must still wait, or null when the attempt may go ahead. */
    public function retryAfter(string $identifier, string $ip): ?int
    {
        $now = now()->getTimestamp();
        $wait = 0;

        $pair = Cache::get($this->pairKey($identifier, $ip));
        if (is_array($pair) && $pair['fails'] >= self::FREE_FAILURES_PER_IP) {
            $delay = $this->delay($pair['fails'] - self::FREE_FAILURES_PER_IP, self::MAX_WAIT_PER_IP);
            $wait = max($wait, $pair['last'] + $delay - $now);
        }

        $account = Cache::get($this->accountKey($identifier));
        if (is_array($account) && count($account['ips']) >= self::DISTINCT_IPS_FOR_ACCOUNT_BACKOFF) {
            $delay = $this->delay($account['fails'] - self::DISTINCT_IPS_FOR_ACCOUNT_BACKOFF, self::MAX_WAIT_PER_ACCOUNT);
            $wait = max($wait, $account['last'] + $delay - $now);
        }

        return $wait > 0 ? $wait : null;
    }

    public function recordFailure(string $identifier, string $ip): void
    {
        $now = now()->getTimestamp();

        $pairKey = $this->pairKey($identifier, $ip);
        $pair = Cache::get($pairKey);
        $pair = is_array($pair) ? $pair : ['fails' => 0, 'last' => $now];
        $pair['fails']++;
        $pair['last'] = $now;
        Cache::put($pairKey, $pair, self::WINDOW_SECONDS);

        $accountKey = $this->accountKey($identifier);
        $account = Cache::get($accountKey);
        $account = is_array($account) ? $account : ['fails' => 0, 'last' => $now, 'ips' => []];
        $account['fails']++;
        $account['last'] = $now;
        if (count($account['ips']) < 50) {
            $account['ips'][$this->hash($ip)] = true;
        }
        Cache::put($accountKey, $account, self::WINDOW_SECONDS);
    }

    public function recordSuccess(string $identifier, string $ip): void
    {
        Cache::forget($this->pairKey($identifier, $ip));
    }

    private function delay(int $over, int $cap): int
    {
        return min(2 ** min(max($over, 0), 10), $cap);
    }

    private function pairKey(string $identifier, string $ip): string
    {
        return 'login-backoff:pair:'.$this->hash($this->normalise($identifier)).':'.$this->hash($ip);
    }

    private function accountKey(string $identifier): string
    {
        return 'login-backoff:account:'.$this->hash($this->normalise($identifier));
    }

    private function normalise(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
