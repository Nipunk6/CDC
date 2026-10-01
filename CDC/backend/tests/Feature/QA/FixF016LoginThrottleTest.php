<?php

namespace Tests\Feature\QA;

use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-016: login attempts are throttled per account, not only per IP — rotating source IPs does not
 * buy more guesses at one roll number, and other accounts are unaffected.
 */
#[Group('qa')]
class FixF016LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $roll, string $password, string $ip): int
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->postJson('/api/auth/login', ['roll_no' => $roll, 'password' => $password])->status();
    }

    public function test_guesses_at_one_account_are_capped_across_source_ips(): void
    {
        Cache::flush();
        $victim = StudentProfile::factory()->create(['roll_no' => '22JE0001']);
        $victim->user->update(['password' => Hash::make('Secret123')]);
        $other = StudentProfile::factory()->create(['roll_no' => '22JE0002']);
        $other->user->update(['password' => Hash::make('Secret123')]);

        $statuses = [];
        for ($i = 1; $i <= 11; $i++) {
            $statuses[] = $this->login($i % 2 ? '22JE0001' : '22je0001 ', 'wrong-'.$i, "10.0.0.{$i}"); // a new IP and spelling each time
        }
        $this->assertSame(array_fill(0, 10, 422), array_slice($statuses, 0, 10));
        $this->assertSame(429, $statuses[10], 'the 11th guess at the same account within a minute is throttled');
        $this->assertSame(429, $this->login('22JE0001', 'Secret123', '10.0.1.1'), 'even the right password waits out the lock');

        $this->assertSame(200, $this->login('22JE0002', 'Secret123', '10.0.0.1'), 'other accounts are unaffected');

        $this->travel(61)->seconds();
        $this->assertSame(200, $this->login('22JE0001', 'Secret123', '10.0.1.2'));
    }
}
