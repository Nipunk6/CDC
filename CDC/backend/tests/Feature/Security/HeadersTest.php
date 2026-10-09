<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-016 (P-1.12): every response Laravel sends carries nosniff, X-Frame-Options and Referrer-Policy; anything sent
 * to an authenticated (bearer) or signed-link request must not be stored by browsers or proxies.
 */
#[Group('security')]
class HeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertHardened($response, string $label): void
    {
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $label);
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'), $label);
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'), $label);
    }

    public function test_api_responses_carry_security_headers_and_no_store(): void
    {
        $public = $this->getJson('/api/branding')->assertOk();
        $this->assertHardened($public, 'public JSON');

        $guest = $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->assertHardened($guest, '401');

        $token = User::factory()->create(['role' => 'admin'])->createToken('auth-token')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $authed = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/auth/user')->assertOk();
        $this->assertHardened($authed, 'authenticated JSON');
        $this->assertStringContainsString('no-store', (string) $authed->headers->get('Cache-Control'));

        $html = $this->get('/api/auth/company/recruiter-email/verify?token=nope');
        $this->assertHardened($html, 'verification result page');
    }

    public function test_signed_link_responses_are_not_stored(): void
    {
        $response = $this->get('/api/resumes/signed/1?expires=1&signature=invalid');
        $this->assertHardened($response, 'signed link');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
