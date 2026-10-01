<?php

namespace Tests\Feature\QA;

use App\Mail\StudentInvitationMail;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-011 (owner decision 3): a student invitation link is valid for 7 days; forgot-password links
 * keep their 60 minutes.
 */
#[Group('qa')]
class FixF011InviteExpiryTest extends TestCase
{
    use RefreshDatabase;

    private StudentProfile $student;

    /** @var array{token: string, email: string} */
    private array $link = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->student = StudentProfile::factory()->create();
        $this->postJson("/api/admin/students/{$this->student->id}/resend-invitation")->assertOk();
        parse_str((string) parse_url(Mail::queued(StudentInvitationMail::class)->sole()->setPasswordUrl, PHP_URL_QUERY), $this->link);
        $this->app['auth']->forgetGuards();
    }

    private function setPassword(string $token): TestResponse
    {
        return $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => $this->link['email'], 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
    }

    private function age(string $table, \DateTimeInterface $createdAt): void
    {
        DB::table($table)->where('email', $this->link['email'])->update(['created_at' => $createdAt]);
    }

    public function test_invitation_link_still_works_after_six_days(): void
    {
        $this->assertStringContainsString('valid for 7 days', Mail::queued(StudentInvitationMail::class)->sole()->render());
        $this->age('student_invite_tokens', now()->subDays(6));
        $this->setPassword($this->link['token'])->assertOk();
        $this->setPassword($this->link['token'])->assertStatus(422); // single use
    }

    public function test_invitation_link_expires_after_seven_days(): void
    {
        $this->age('student_invite_tokens', now()->subDays(7)->subMinute());
        $this->setPassword($this->link['token'])->assertStatus(422)->assertJsonPath('message', 'The password reset link is invalid or has expired.');
    }

    public function test_forgot_password_link_keeps_sixty_minutes_and_retires_the_invitation(): void
    {
        $resetToken = Password::broker()->createToken($this->student->user);
        $this->age('password_reset_tokens', now()->subMinutes(61));
        $this->setPassword($resetToken)->assertStatus(422);

        $resetToken = Password::broker()->createToken($this->student->user);
        $this->setPassword($resetToken)->assertOk();
        $this->setPassword($this->link['token'])->assertStatus(422); // the old invitation no longer works
    }
}
