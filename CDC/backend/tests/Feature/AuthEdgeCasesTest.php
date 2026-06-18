<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLinkMail;
use App\Models\Company;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@gmail.com',
            'password' => 'secret123',
            'role' => 'admin',
            'company_id' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'valid@gmail.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $company = Company::create([
            'name' => 'Owner Co',
            'industry' => 'Technology',
            'website' => 'https://owner.example',
            'hr_name' => 'Owner HR',
            'hr_email' => 'owner.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        $userA = User::factory()->create([
            'role' => 'company',
            'company_id' => $company->id,
        ]);

        $userB = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $notification = PortalNotification::create([
            'user_id' => $userA->id,
            'title' => 'Private',
            'message' => 'Only owner can read this.',
            'type' => 'info',
        ]);

        Sanctum::actingAs($userB);

        $response = $this->patchJson("/api/auth/notifications/{$notification->id}/read");

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_forgot_password_sends_reset_mail_for_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'forgot.user@gmail.com',
            'role' => 'company',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'If the account exists, a password reset link has been sent.');

        Mail::assertSent(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user): bool {
            if (! $mail->hasTo($user->email)) {
                return false;
            }

            $url = parse_url($mail->resetUrl);

            if (! isset($url['query'])) {
                return false;
            }

            parse_str($url['query'], $query);

            return isset($query['token'], $query['email'])
                && is_string($query['token'])
                && $query['token'] !== ''
                && $query['email'] === $user->email;
        });
    }

    public function test_forgot_password_does_not_send_mail_for_unknown_user(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown.user@gmail.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'If the account exists, a password reset link has been sent.');
        Mail::assertNothingSent();
    }
}
