<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * P-1.14 (owner decision 2026-10-12): the `api` limiter gives a signed-in user 300 requests a minute (per user) and
 * keeps guests at 60 a minute per IP. An admin working normally must never be stopped by it.
 */
#[Group('security')]
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function statuses(int $count, string $uri, ?string $token, string $ip = '203.0.113.50', string $method = 'GET'): array
    {
        $statuses = [];
        for ($i = 0; $i < $count; $i++) {
            $this->app['auth']->forgetGuards();
            $request = $this->withServerVariables(['REMOTE_ADDR' => $ip]);
            $request = $token ? $request->withHeader('Authorization', 'Bearer '.$token) : $request->withHeaders(['Authorization' => '']);
            $statuses[] = $request->json($method, $uri)->status();
        }

        return $statuses;
    }

    public function test_one_admin_making_120_requests_in_a_minute_is_not_throttled(): void
    {
        $token = User::factory()->create(['role' => 'admin'])->createToken('auth-token')->plainTextToken;

        $statuses = $this->statuses(120, '/api/admin/ping', $token);

        $this->assertSame([200], array_values(array_unique($statuses)));
    }

    public function test_a_signed_in_user_is_limited_at_300_a_minute_per_user(): void
    {
        $first = User::factory()->create(['role' => 'admin'])->createToken('auth-token')->plainTextToken;
        $second = User::factory()->create(['role' => 'admin'])->createToken('auth-token')->plainTextToken;

        $statuses = $this->statuses(301, '/api/admin/ping', $first);
        $this->assertSame(300, array_search(429, $statuses, true), 'the 301st request is the first 429');
        $this->assertSame([200], $this->statuses(1, '/api/admin/ping', $second), 'another user on the same address has their own budget');
    }

    public function test_guests_stay_at_60_a_minute_per_ip(): void
    {
        // A guest route in the `api` bucket; an empty body is a 422, which still counts as a request.
        $statuses = $this->statuses(61, '/api/auth/forgot-password', null, '203.0.113.60', 'POST');
        $this->assertSame(60, array_search(429, $statuses, true), 'the 61st guest request is the first 429');
        $this->assertSame([422], $this->statuses(1, '/api/auth/forgot-password', null, '203.0.113.61', 'POST'), 'another address has its own budget');
    }
}
