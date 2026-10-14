<?php

namespace Tests\Feature\Security;

use App\Mail\AlumniOutreachConfirmationMail;
use App\Mail\RecruiterEmailVerificationMail;
use App\Models\AlumniOutreachSubmission;
use App\Models\RecruiterEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-010 (P-1.3): the unauthenticated recruiter verification-link and alumni outreach endpoints send at most one mail
 * per address per 10 minutes, so they cannot be used to flood an inbox.
 */
#[Group('security')]
class MailAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
        config(['services.recruiter_email.dns_check' => false]); // no live DNS in tests
        $this->freezeTime();
    }

    private function requestLink(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => $email, 'name' => 'HR']);
    }

    /** @return array<string, mixed> */
    private function alumni(string $email): array
    {
        return [
            'full_name' => 'Asha Rao', 'email' => $email, 'country_code' => '+91', 'phone_number' => '9876543210',
            'graduation_year' => 2015, 'programme' => 'B.Tech', 'department' => 'CSE', 'current_organization' => 'Acme',
            'current_designation' => 'Engineer', 'city' => 'Pune', 'country' => 'India', 'message' => 'Happy to help.',
        ];
    }

    public function test_five_verification_link_requests_within_ten_minutes_send_one_mail_and_keep_the_first_link(): void
    {
        $this->requestLink('hr@acme.test')->assertOk();
        $firstHash = RecruiterEmailVerification::where('email', 'hr@acme.test')->value('token_hash');

        for ($i = 0; $i < 4; $i++) {
            $this->travel(1)->minutes();
            $this->requestLink('hr@acme.test')->assertStatus(429)->assertHeader('Retry-After');
        }

        Mail::assertSent(RecruiterEmailVerificationMail::class, 1);
        $this->assertSame($firstHash, RecruiterEmailVerification::where('email', 'hr@acme.test')->value('token_hash'), 'a throttled request must not invalidate the link already sent');

        $this->requestLink('other@acme.test')->assertOk();
        Mail::assertSent(RecruiterEmailVerificationMail::class, 2);

        $this->travel(7)->minutes(); // 11 minutes after the first mail
        $this->requestLink('hr@acme.test')->assertOk();
        Mail::assertSent(RecruiterEmailVerificationMail::class, 3);
    }

    public function test_alumni_confirmations_to_one_address_are_capped_but_every_submission_is_kept(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/alumni-outreach', $this->alumni('asha@example.org'))->assertCreated();
        }

        $this->assertSame(3, AlumniOutreachSubmission::count());
        Mail::assertSent(AlumniOutreachConfirmationMail::class, 1);

        $this->travel(11)->minutes();
        $this->postJson('/api/alumni-outreach', $this->alumni('asha@example.org'))->assertCreated();
        Mail::assertSent(AlumniOutreachConfirmationMail::class, 2);
    }
}
