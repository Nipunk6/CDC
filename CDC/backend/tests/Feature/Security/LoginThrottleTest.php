<?php

namespace Tests\Feature\Security;

use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-008 + QA N-1 (P-1.1). The Next.js server forwards the real client IP in a header signed with
 * INTERNAL_PROXY_SECRET; Laravel trusts it only when the signature verifies. Login has its own 600/min per-IP budget
 * (not the 60/min `api` bucket) and a short per-account backoff instead of a hard lockout.
 */
#[Group('security')]
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-internal-proxy-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal_proxy.secret' => self::SECRET]);
        Cache::flush();
        $this->freezeTime(); // backoff waits are whole seconds; a clock tick mid-test must not change them
    }

    /** @return array<string, string> */
    private function signed(string $ip, ?int $ts = null, ?string $secret = null): array
    {
        $ts ??= now()->getTimestamp();

        return [
            'X-CDC-Client-IP' => $ip,
            'X-CDC-Client-IP-Ts' => (string) $ts,
            'X-CDC-Client-IP-Sig' => hash_hmac('sha256', $ip.'|'.$ts, $secret ?? self::SECRET),
        ];
    }

    private function student(string $roll, string $password = 'Secret123'): StudentProfile
    {
        $student = StudentProfile::factory()->create(['roll_no' => $roll]);
        $student->user->update(['password' => Hash::make($password)]);

        return $student;
    }

    private function login(string $roll, string $password, string $ip): \Illuminate\Testing\TestResponse
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->postJson('/api/auth/login', ['roll_no' => $roll, 'password' => $password]);
    }

    public function test_signed_client_ip_is_trusted_and_unsigned_or_forged_headers_are_ignored(): void
    {
        Route::get('/api/_test/client-ip', fn (Request $r) => ['ip' => $r->ip(), 'source' => $r->attributes->get('client_ip_source')]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5']);

        $this->getJson('/api/_test/client-ip', $this->signed('203.0.113.7'))
            ->assertExactJson(['ip' => '203.0.113.7', 'source' => 'signed']);

        $this->getJson('/api/_test/client-ip')
            ->assertExactJson(['ip' => '10.0.0.5', 'source' => 'direct']);

        $forged = $this->signed('203.0.113.7', secret: 'not-the-secret');
        $this->getJson('/api/_test/client-ip', $forged)
            ->assertExactJson(['ip' => '10.0.0.5', 'source' => 'invalid_signature']);

        $stale = $this->signed('203.0.113.7', now()->subMinutes(10)->getTimestamp());
        $this->getJson('/api/_test/client-ip', $stale)
            ->assertExactJson(['ip' => '10.0.0.5', 'source' => 'invalid_signature']);

        $this->getJson('/api/_test/client-ip', ['X-Forwarded-For' => '198.51.100.1'] + ['X-CDC-Client-IP' => '198.51.100.1'])
            ->assertJsonPath('ip', '10.0.0.5');

        config(['services.internal_proxy.secret' => '']);
        $this->getJson('/api/_test/client-ip', $this->signed('203.0.113.7'))
            ->assertExactJson(['ip' => '10.0.0.5', 'source' => 'direct']);
    }

    public function test_login_has_its_own_600_per_minute_budget_per_ip_instead_of_the_60_per_minute_api_bucket(): void
    {
        // 70 failed logins for 70 different (unknown) accounts from one IP: well past the old 60/min `api` bucket.
        for ($i = 1; $i <= 70; $i++) {
            $this->login(sprintf('99ZZ%04d', $i), 'wrong', '10.1.1.1')->assertStatus(422);
        }

        // Fill the login-ip bucket for this IP up to its 600/min limit: the next login is throttled.
        $key = md5('login-ip'.'login-ip:10.1.1.1');
        RateLimiter::clear($key);
        for ($i = 0; $i < 600; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->login('99ZZ9999', 'wrong', '10.1.1.1')->assertStatus(429);

        // Another (signed) client IP still has its own budget.
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1']);
        $this->postJson('/api/auth/login', ['roll_no' => '99ZZ9998', 'password' => 'wrong'], $this->signed('203.0.113.50'))
            ->assertStatus(422);
    }

    public function test_per_account_limit_is_a_short_backoff_not_a_hard_lockout(): void
    {
        $this->student('22JE0001');

        for ($i = 1; $i <= 5; $i++) {
            $this->login('22JE0001', 'wrong-'.$i, '10.2.2.2')->assertStatus(422);
        }
        $sixth = $this->login('22JE0001', 'wrong-6', '10.2.2.2')->assertStatus(429);
        $this->assertLessThanOrEqual(2, (int) $sixth->headers->get('Retry-After'));
        $this->assertStringContainsString('Too many attempts', $sixth->json('message'));

        // Even the right password waits out the (short) backoff from this IP...
        $this->login('22JE0001', 'Secret123', '10.2.2.2')->assertStatus(429);
        // ...and succeeds a couple of seconds later: no minute-long lockout.
        $this->travel(2)->seconds();
        $this->login('22JE0001', 'Secret123', '10.2.2.2')->assertOk();
    }

    public function test_backoff_doubles_but_is_capped_at_a_minute(): void
    {
        $this->student('22JE0002');

        $retryAfter = 0;
        for ($i = 1; $i <= 15; $i++) {
            $response = $this->login('22JE0002', 'wrong-'.$i, '10.3.3.3');
            if ($response->status() === 429) {
                $retryAfter = (int) $response->headers->get('Retry-After');
                $this->travel($retryAfter)->seconds();
                $this->login('22JE0002', 'wrong-again-'.$i, '10.3.3.3')->assertStatus(422);
            }
        }

        $last = $this->login('22JE0002', 'wrong-final', '10.3.3.3')->assertStatus(429);
        $this->assertSame(60, (int) $last->headers->get('Retry-After'), 'the backoff is capped at 60 s');
    }

    public function test_victim_on_another_ip_can_log_in_while_an_attacker_hammers_the_account(): void
    {
        $this->student('22JE0003');

        for ($i = 1; $i <= 40; $i++) {
            $this->login('22JE0003', 'guess-'.$i, '198.51.100.66');
        }

        $this->login('22JE0003', 'Secret123', '10.4.4.4')->assertOk();
    }

    public function test_guessing_from_many_ips_triggers_a_short_account_wide_backoff(): void
    {
        $this->student('22JE0004');

        for ($i = 1; $i <= 5; $i++) {
            $this->login('22JE0004', 'wrong-'.$i, "10.5.5.{$i}")->assertStatus(422);
        }

        $blocked = $this->login('22JE0004', 'wrong-6', '10.5.5.99')->assertStatus(429);
        $this->assertLessThanOrEqual(30, (int) $blocked->headers->get('Retry-After'));

        $this->travel(31)->seconds();
        $this->login('22JE0004', 'Secret123', '10.5.5.100')->assertOk();
    }

    public function test_a_forged_forwarded_ip_cannot_rotate_past_the_backoff(): void
    {
        $this->student('22JE0005');

        for ($i = 1; $i <= 5; $i++) {
            $this->login('22JE0005', 'wrong-'.$i, '10.6.6.6')->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.6.6.6']);
        $this->postJson('/api/auth/login', ['roll_no' => '22JE0005', 'password' => 'wrong-6'], $this->signed('203.0.113.9', secret: 'guessed'))
            ->assertStatus(429);
    }
}
