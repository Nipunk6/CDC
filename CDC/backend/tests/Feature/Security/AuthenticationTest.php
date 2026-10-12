<?php

namespace Tests\Feature\Security;

use App\Mail\PasswordResetLinkMail;
use App\Mail\PortalNoticeMail;
use App\Mail\RecruiterEmailVerificationMail;
use App\Mail\StudentInvitationMail;
use App\Models\Company;
use App\Models\RecruiterEmailVerification;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Security audit Part 3 — authentication. Every test asserts the SECURE behaviour; a failing test is a finding
 * (see CDC/SECURITY_REPORT.md). Run: php artisan test --group=security
 */
#[Group('security')]
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function reset(string $email, string $token, string $password): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/reset-password', ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password]);
    }

    // ---------------------------------------------------------------- A3.1 / A3.2 enumeration

    /**
     * SEC finding (timing oracle): an unknown account must cost the same as a known one with a wrong password.
     *
     * SEC-003: fixed in P-1.14 (was in group qa-open until then).
     */
    public function test_A3_1_failed_login_timing_does_not_reveal_whether_the_account_exists(): void
    {
        // Factory first (it caches its own cost-4 hash in a static), then production cost for this account only.
        $known = StudentProfile::factory()->create(['roll_no' => '22JE0001']);
        config(['hashing.bcrypt.rounds' => 12]);
        app('hash')->forgetDrivers();
        $known->user->update(['password' => Hash::make('Secret123')]);
        $this->assertSame(12, password_get_info($known->user->fresh()->password)['options']['cost']);

        $time = function (array $body): float {
            $t = hrtime(true);
            $this->postJson('/api/auth/login', $body)->assertStatus(422);

            return (hrtime(true) - $t) / 1e6;
        };
        $knownMs = $unknownMs = [];
        for ($i = 0; $i < 4; $i++) {
            Cache::flush();
            $knownMs[] = $time(['roll_no' => '22JE0001', 'password' => 'Wrong'.$i]);
            $unknownMs[] = $time(['roll_no' => '99ZZ000'.$i, 'password' => 'Wrong'.$i]);
        }
        $gap = array_sum($knownMs) / 4 - array_sum($unknownMs) / 4;
        $this->assertLessThan(50, $gap, sprintf('known accounts answer %.0f ms slower than unknown ones (bcrypt only runs for existing users)', $gap));
    }

    /**
     * SEC finding: the second reset request for an existing account answers 429, an unknown account never does.
     *
     * SEC-003: fixed in P-1.14 (was in group qa-open until then).
     */
    public function test_A3_2_forgot_password_responses_are_identical_for_existing_and_unknown_accounts(): void
    {
        StudentProfile::factory()->create(['roll_no' => '22JE0002']);
        $sequence = fn (array $body) => [
            $this->postJson('/api/auth/forgot-password', $body)->status(),
            $this->postJson('/api/auth/forgot-password', $body)->status(),
        ];

        $this->assertSame($sequence(['roll_no' => '99ZZ0002']), $sequence(['roll_no' => '22JE0002']), 'existing account is distinguishable by the reset throttle');
    }

    /** SEC-003 (P-1.14): a mail fault or a revoked invitation looks exactly like "sent" too. */
    public function test_A3_2_forgot_password_never_answers_differently_for_an_existing_account(): void
    {
        StudentProfile::factory()->create(['roll_no' => '22JE0003']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['roll_no' => '99ZZ0003']);
        $known = $this->postJson('/api/auth/forgot-password', ['roll_no' => '22JE0003']);
        $again = $this->postJson('/api/auth/forgot-password', ['roll_no' => '22JE0003']); // throttled by the broker

        foreach ([$known, $again] as $response) {
            $this->assertSame($unknown->status(), $response->status());
            $this->assertSame($unknown->getContent(), $response->getContent());
        }
        Mail::assertSent(PasswordResetLinkMail::class, 1); // the throttled repeat sends nothing
    }

    // ---------------------------------------------------------------- A3.3 recruiter registration enumeration

    /**
     * SEC-003 (P-1.14): asking for a verification link looks the same for an address that already has an account as
     * for a new one. The new address gets the link; the existing owner gets a notice instead.
     */
    public function test_A3_3_verification_link_response_is_identical_for_registered_and_new_addresses(): void
    {
        config(['services.recruiter_email.dns_check' => false]);
        User::factory()->create(['role' => 'company', 'email' => 'taken@co.qa.test', 'name' => 'Asha Rao']);
        Company::create(['name' => 'Legacy', 'hr_name' => 'HR', 'hr_email' => 'legacy@co.qa.test']);

        $shape = function (string $email) {
            $response = $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => $email, 'name' => 'Someone']);
            $body = $response->json();
            $this->assertIsString($body['status_token'] ?? null, $email);
            $this->assertSame(40, strlen($body['status_token']), $email);
            unset($body['status_token']);

            return [$response->status(), $body];
        };

        $new = $shape('new@co.qa.test');
        $this->assertSame(200, $new[0]);
        $this->assertSame($new, $shape('taken@co.qa.test'), 'an address with a user account');
        $this->assertSame($new, $shape('legacy@co.qa.test'), 'an address used as a company HR email');

        Mail::assertSent(RecruiterEmailVerificationMail::class, fn ($m) => $m->hasTo('new@co.qa.test'));
        Mail::assertSent(RecruiterEmailVerificationMail::class, 1);
        Mail::assertSent(PortalNoticeMail::class, fn ($m) => $m->hasTo('taken@co.qa.test') && str_contains($m->render(), 'already has an account'));
        Mail::assertSent(PortalNoticeMail::class, fn ($m) => $m->hasTo('legacy@co.qa.test'));
        $this->assertSame(['new@co.qa.test'], RecruiterEmailVerification::pluck('email')->all(), 'no verification is started for a registered address');

        // The 10-minute cooldown answers the same for both as well.
        $repeatNew = $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => 'new@co.qa.test']);
        $repeatTaken = $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => 'taken@co.qa.test']);
        $this->assertSame(429, $repeatNew->status());
        $this->assertSame([$repeatNew->status(), $repeatNew->json('message')], [$repeatTaken->status(), $repeatTaken->json('message')]);
    }

    /** SEC-003 (P-1.14): the status check needs the token given to the browser that asked for the link. */
    public function test_A3_3_verification_status_needs_the_status_token(): void
    {
        config(['services.recruiter_email.dns_check' => false, 'app.url' => 'http://localhost']);
        User::factory()->create(['role' => 'company', 'email' => 'taken@co.qa.test']);
        $statusToken = $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => 'new@co.qa.test'])->assertOk()->json('status_token');
        $fakeToken = $this->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => 'taken@co.qa.test'])->assertOk()->json('status_token');
        $status = fn (array $query) => $this->getJson('/api/auth/company/recruiter-email/verification-status?'.http_build_query($query));

        $status(['email' => 'new@co.qa.test'])->assertStatus(422)->assertJsonValidationErrors('status_token');
        $status(['email' => 'new@co.qa.test', 'status_token' => $statusToken])->assertOk()->assertJson(['verified' => false]);

        // The recruiter clicks the emailed link.
        $link = null;
        Mail::assertSent(RecruiterEmailVerificationMail::class, function (RecruiterEmailVerificationMail $m) use (&$link) {
            $link = $m->verifyUrl;

            return true;
        });
        $this->get(str_replace('http://localhost', '', $link))->assertSee('verified successfully');

        $status(['email' => 'new@co.qa.test', 'status_token' => $statusToken])->assertOk()->assertJson(['verified' => true]);
        $status(['email' => 'new@co.qa.test', 'status_token' => str_repeat('x', 40)])->assertOk()->assertJson(['verified' => false]);
        $status(['email' => 'taken@co.qa.test', 'status_token' => $fakeToken])->assertOk()->assertJson(['verified' => false]);
        $status(['email' => 'nobody@co.qa.test', 'status_token' => $statusToken])->assertOk()->assertJson(['verified' => false]);
    }

    /** SEC-003 (P-1.14): registering with an address that already has an account says only "verify the email first". */
    public function test_A3_3_registration_does_not_say_that_an_address_is_registered(): void
    {
        Storage::fake('public');
        User::factory()->create(['role' => 'company', 'email' => 'taken@co.qa.test']);
        $payload = fn (string $email) => [
            'company_name' => 'Acme', 'website' => 'https://acme.example', 'sector' => 'IT', 'company_logo' => UploadedFile::fake()->image('logo.png', 64, 64),
            'recruiter_name' => 'Asha Rao', 'recruiter_designation' => 'HR', 'hr_email' => $email, 'hr_phone' => '9876543210',
            'head_name' => 'Head', 'head_designation' => 'Lead', 'head_email' => 'head@acme.example', 'head_mobile' => '9876543211',
            'poc1_name' => 'Poc', 'poc1_designation' => 'HR', 'poc1_email' => 'poc@acme.example', 'poc1_mobile' => '9876543212',
            'password' => 'Zx9!kLm2#Qw', 'password_confirmation' => 'Zx9!kLm2#Qw',
        ];

        $taken = $this->post('/api/auth/company/register', $payload('taken@co.qa.test'), ['Accept' => 'application/json']);
        $fresh = $this->post('/api/auth/company/register', $payload('fresh@co.qa.test'), ['Accept' => 'application/json']);

        $this->assertSame(422, $taken->status());
        $this->assertSame([$fresh->status(), $fresh->getContent()], [$taken->status(), $taken->getContent()]);
        $this->assertStringNotContainsString('already been registered', $taken->getContent());
    }

    // ---------------------------------------------------------------- A3.4 password policy

    public function test_A3_4_basic_policy_rejects_short_and_simple_passwords(): void
    {
        $user = User::factory()->create(['role' => 'company']);
        foreach (['password', 'Pass12A', 'PASSWORD12', 'password12', 'Password'] as $weak) {
            $token = Password::broker()->createToken($user);
            $this->reset($user->email, $token, $weak)->assertStatus(422);
        }
    }

    /**
     * SEC finding (Low): no compromised-password or personal-information check.
     *
     * Known open finding SEC-012: fails on purpose until fixed (group qa-open; remove the tag when fixed).
     */
    #[Group('qa-open')]
    public function test_A3_4_common_or_personal_passwords_are_rejected(): void
    {
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0003', 'full_name' => 'Asha Verma']);
        $accepted = [];
        foreach (['Password1', 'Welcome123', 'AshaVerma1', '22Je0003pass'] as $weak) {
            $token = Password::broker()->createToken($student->user);
            if ($this->reset($student->user->email, $token, $weak)->status() === 200) {
                $accepted[] = $weak;
            }
        }
        $this->assertSame([], $accepted, 'weak/common/personal passwords accepted');
    }

    // ---------------------------------------------------------------- A3.5 reset flow

    public function test_A3_5_reset_token_is_single_use_email_bound_expiring_and_revokes_api_tokens(): void
    {
        $a = User::factory()->create(['role' => 'company', 'email' => 'a@co.test']);
        $b = User::factory()->create(['role' => 'company', 'email' => 'b@co.test']);
        $oldToken = $a->createToken('auth-token')->plainTextToken;

        $tokenA = Password::broker()->createToken($a);
        $this->reset('b@co.test', $tokenA, 'NewPass123')->assertStatus(422); // A's token with B's email
        $this->reset('a@co.test', $tokenA, 'NewPass123')->assertOk();
        $this->reset('a@co.test', $tokenA, 'NewPass456')->assertStatus(422); // reuse
        $this->bearer($oldToken)->getJson('/api/auth/user')->assertUnauthorized(); // old API tokens revoked

        $tokenB = Password::broker()->createToken($b);
        DB::table('password_reset_tokens')->where('email', 'b@co.test')->update(['created_at' => now()->subMinutes(61)]);
        $this->reset('b@co.test', $tokenB, 'NewPass123')->assertStatus(422); // expired
    }

    public function test_A3_5_reset_link_is_built_from_config_not_from_host_headers(): void
    {
        config(['app.frontend_url' => 'https://portal.cdc.test']);
        User::factory()->create(['role' => 'company', 'email' => 'c@co.test']);

        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
            ->postJson('/api/auth/forgot-password', ['email' => 'c@co.test'])->assertOk();

        Mail::assertSent(PasswordResetLinkMail::class, fn (PasswordResetLinkMail $m) => str_starts_with($m->resetUrl, 'https://portal.cdc.test/') && ! str_contains($m->resetUrl, 'evil'));
    }

    // ---------------------------------------------------------------- A3.6 invitations

    public function test_A3_6_invitation_is_single_use_resend_invalidates_old_and_mail_carries_no_extra_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0004', 'current_cgpa' => 8.73, 'phone' => '9876501234']);
        $link = function () {
            parse_str((string) parse_url(Mail::queued(StudentInvitationMail::class)->last()->setPasswordUrl, PHP_URL_QUERY), $q);

            return $q;
        };

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/students/{$student->id}/resend-invitation")->assertOk();
        $first = $link();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/students/{$student->id}/resend-invitation")->assertOk();
        $second = $link();

        $html = Mail::queued(StudentInvitationMail::class)->last()->render();
        $this->assertStringContainsString('22JE0004', $html);
        $this->assertStringNotContainsString('8.73', $html);
        $this->assertStringNotContainsString('9876501234', $html);

        $this->reset($first['email'], $first['token'], 'NewPass123')->assertStatus(422); // superseded
        $this->reset($second['email'], $second['token'], 'NewPass123')->assertOk();
        $this->reset($second['email'], $second['token'], 'NewPass456')->assertStatus(422); // single use
    }

    // ---------------------------------------------------------------- A3.7 account state

    public function test_A3_7_suspended_deleted_and_orphaned_accounts_are_rejected_on_every_request(): void
    {
        $student = StudentProfile::factory()->create();
        $sTok = $student->user->createToken('t')->plainTextToken;
        $student->user->update(['is_active' => false]);
        $this->bearer($sTok)->getJson('/api/student/profile')->assertForbidden()->assertJsonPath('message', 'Account suspended. Contact CDC.');

        $admin = User::factory()->create(['role' => 'admin']);
        $aTok = $admin->createToken('t')->plainTextToken;
        $admin->delete();
        $this->bearer($aTok)->getJson('/api/admin/students')->assertUnauthorized();

        $company = Company::create(['name' => 'Gone', 'hr_name' => 'HR', 'hr_email' => 'gone@co.test']);
        $cUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
        $cTok = $cUser->createToken('t')->plainTextToken;
        $company->delete(); // users.company_id → null
        // An orphaned company user is refused or sees nothing (where company_id = null becomes IS NULL; forms' company_id is NOT NULL).
        foreach (['/api/company/profile' => null, '/api/company/jnfs' => 'jnfs', '/api/company/postings' => 'postings'] as $uri => $key) {
            $response = $this->bearer($cTok)->getJson($uri);
            if (! in_array($response->status(), [401, 403, 404], true)) {
                $this->assertSame([], $key ? ($response->json($key) ?? $response->json()) : $response->json(), "{$uri} for a company user without a company returned data");
            }
        }
    }

    // ---------------------------------------------------------------- A3.8 cross-portal

    public function test_A3_8_api_token_of_one_role_never_reaches_another_roles_routes(): void
    {
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0005']);
        $student->user->update(['password' => Hash::make('Secret123')]);
        // The API issues a token whatever "portal" the user came from (portal choice is UI-only by design)…
        $token = $this->postJson('/api/auth/login', ['roll_no' => '22JE0005', 'password' => 'Secret123'])->assertOk()->json('token');
        // …but the role boundary holds.
        $this->bearer($token)->getJson('/api/company/postings')->assertForbidden();
        $this->bearer($token)->getJson('/api/admin/students')->assertForbidden();
        $this->bearer($token)->getJson('/api/student/profile')->assertOk();
    }

    // ---------------------------------------------------------------- A3.9 recruiter email verification

    public function test_A3_9_verification_token_hashed_expires_and_registration_requires_that_exact_email(): void
    {
        $token = Str::random(64);
        RecruiterEmailVerification::create(['email' => 'hr@newco.test', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30)]);
        $this->assertSame(0, DB::table('recruiter_email_verifications')->where('token_hash', $token)->count(), 'plaintext token never stored');

        $this->get('/api/auth/company/recruiter-email/verify?token='.$token)->assertOk();
        $this->assertNotNull(RecruiterEmailVerification::where('email', 'hr@newco.test')->value('verified_at'));

        $expired = Str::random(64);
        RecruiterEmailVerification::create(['email' => 'hr@late.test', 'token_hash' => hash('sha256', $expired), 'expires_at' => now()->subMinute()]);
        $this->get('/api/auth/company/recruiter-email/verify?token='.$expired)->assertSee('expired');
        $this->assertNull(RecruiterEmailVerification::where('email', 'hr@late.test')->value('verified_at'));
    }

    /** SEC-014 (P-1.12): a verification link works once; the address stays verified. */
    public function test_A3_9_verification_link_is_single_use(): void
    {
        $token = Str::random(64);
        RecruiterEmailVerification::create(['email' => 'hr@twice.test', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30)]);
        $this->get('/api/auth/company/recruiter-email/verify?token='.$token)->assertSee('verified successfully');
        $this->get('/api/auth/company/recruiter-email/verify?token='.$token)->assertDontSee('verified successfully');
        $this->assertNotNull(RecruiterEmailVerification::where('email', 'hr@twice.test')->value('verified_at'));
    }

    /** SEC-014 / Phase 1 B7 (P-1.12): once verified, the address stays verified for 24 hours, not just the link's 30 minutes. */
    public function test_A3_9_verified_address_outlives_the_link_lifetime(): void
    {
        config(['services.recruiter_email.dns_check' => false]);
        $token = Str::random(64);
        RecruiterEmailVerification::create(['email' => 'hr@slow.qa.test', 'token_hash' => hash('sha256', $token), 'status_token_hash' => hash('sha256', 'browser-status-token'), 'expires_at' => now()->addMinutes(30)]);
        $this->get('/api/auth/company/recruiter-email/verify?token='.$token)->assertSee('verified successfully');
        $status = '/api/auth/company/recruiter-email/verification-status?email=hr@slow.qa.test&status_token=browser-status-token';

        $this->travel(2)->hours();
        $this->getJson($status)->assertOk()->assertJson(['verified' => true]);

        $this->travel(23)->hours();
        $this->getJson($status)->assertOk()->assertJson(['verified' => false]);
    }

    /** SEC-007 (P-1.10): the emailed link is built from APP_URL, never from the Host / X-Forwarded-Host headers. */
    public function test_A3_9_verification_link_uses_configured_origin(): void
    {
        config(['app.url' => 'https://api.cdc.test', 'services.recruiter_email.dns_check' => false]);

        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
            ->postJson('/api/auth/company/recruiter-email/verification-link', ['email' => 'hr@newco.qa.test', 'name' => 'Asha'])
            ->assertOk();

        Mail::assertSent(RecruiterEmailVerificationMail::class, fn (RecruiterEmailVerificationMail $m) => str_starts_with($m->verifyUrl, 'https://api.cdc.test/api/auth/company/recruiter-email/verify?token=')
            && ! str_contains($m->verifyUrl, 'evil'));
    }

    /** SEC-007 (P-1.10): Laravel's TrustHosts runs globally; trusted hosts are APP_URL's host (+ subdomains) and the exact names in TRUSTED_HOSTS. */
    public function test_A3_9_trusted_hosts_come_from_config_and_are_exact(): void
    {
        $this->assertContains(TrustHosts::class, app(HttpKernel::class)->getGlobalMiddleware());

        config(['app.url' => 'https://api.cdc.test', 'app.trusted_hosts' => ['portal.cdc.test']]);
        $patterns = app(TrustHosts::class)->hosts();
        // Symfony's Request::setTrustedHosts() wraps every pattern as {pattern}i.
        $trusted = fn (string $host): bool => collect($patterns)->contains(fn (?string $p) => $p !== null && preg_match('{'.$p.'}i', $host) === 1);

        $this->assertTrue($trusted('api.cdc.test'));
        $this->assertTrue($trusted('portal.cdc.test'));
        $this->assertFalse($trusted('evil.example'));
        $this->assertFalse($trusted('portal.cdc.test.evil.example'), 'configured names are anchored, not substrings');
        $this->assertFalse($trusted('xportal.cdc.test'));
    }

    // ---------------------------------------------------------------- A3.10 super-admin protection

    public function test_A3_10_normal_admin_cannot_manage_admins_and_nobody_can_mint_a_super_admin(): void
    {
        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/manage-admins')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/manage-admins/{$super->id}")->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/manage-admins', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();

        // A super admin creating an admin cannot smuggle privilege flags in.
        $this->app['auth']->forgetGuards();
        $this->actingAs($super, 'sanctum')->postJson('/api/admin/manage-admins', ['name' => 'New', 'email' => 'new.admin@example.com', 'is_super_admin' => true, 'role' => 'admin', 'is_active' => true]);
        $created = User::where('email', 'new.admin@example.com')->first();
        if ($created) {
            $this->assertFalse((bool) $created->is_super_admin);
        }
        $this->actingAs($super, 'sanctum')->deleteJson("/api/admin/manage-admins/{$super->id}")->assertForbidden(); // not self
    }
}
