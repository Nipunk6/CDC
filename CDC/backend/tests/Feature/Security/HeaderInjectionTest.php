<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-011 (P-1.8): an email address carrying CRLF + an extra header must be rejected by the public forms before any
 * mail is built (Laravel's default email rule and symfony/mime both had CRLF advisories).
 */
#[Group('security')]
class HeaderInjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    /** @return list<string> */
    private function injected(): array
    {
        return [
            "victim@example.org\r\nBcc: attacker@evil.example",
            "victim@example.org\nBcc: attacker@evil.example",
            "victim@example.org%0d%0aBcc:attacker@evil.example",
        ];
    }

    public function test_email_with_crlf_is_rejected_by_public_forms(): void
    {
        foreach ($this->injected() as $email) {
            $this->postJson('/api/auth/forgot-password', ['email' => $email])->assertStatus(422);

            $this->postJson('/api/alumni-outreach', [
                'full_name' => 'Asha Rao', 'email' => $email, 'country_code' => '+91', 'phone_number' => '9876543210',
                'graduation_year' => 2015, 'programme' => 'B.Tech', 'department' => 'CSE', 'current_organization' => 'Acme',
                'current_designation' => 'Engineer', 'city' => 'Pune', 'country' => 'India', 'message' => 'Hello',
            ])->assertStatus(422);
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }
}
