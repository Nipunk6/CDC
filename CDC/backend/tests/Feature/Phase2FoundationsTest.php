<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ProgrammeBranch;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\ProgrammeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2FoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyUser(): User
    {
        $company = Company::create([
            'name' => 'Demo Co',
            'industry' => 'Technology',
            'website' => 'https://demo.example',
            'hr_name' => 'Demo HR',
            'hr_email' => 'demo.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        return User::factory()->create([
            'role' => 'company',
            'company_id' => $company->id,
        ]);
    }

    public function test_public_admin_register_route_is_removed(): void
    {
        $response = $this->postJson('/api/auth/admin/register', [
            'name' => 'Evil Admin',
            'email' => 'evil@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
    }

    public function test_company_cannot_create_jnf_with_privileged_status(): void
    {
        Sanctum::actingAs($this->makeCompanyUser());

        $response = $this->postJson('/api/company/jnfs', [
            'job_title' => 'Engineer',
            'job_description' => 'Build things.',
            'status' => 'accepted',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
        $this->assertDatabaseCount('jnfs', 0);
    }

    public function test_company_cannot_write_admin_remarks(): void
    {
        Sanctum::actingAs($this->makeCompanyUser());

        $response = $this->postJson('/api/company/jnfs', [
            'job_title' => 'Engineer',
            'job_description' => 'Build things.',
            'status' => 'draft',
            'admin_remarks' => 'Self-approved.',
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('jnf.admin_remarks'));
        $this->assertDatabaseHas('jnfs', ['job_title' => 'Engineer', 'admin_remarks' => null]);
    }

    public function test_company_can_still_delete_a_draft_jnf(): void
    {
        $user = $this->makeCompanyUser();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/jnfs', [
            'job_title' => 'Engineer',
            'job_description' => 'Build things.',
            'status' => 'draft',
        ]);
        $create->assertCreated();

        $this->deleteJson('/api/company/jnfs/'.$create->json('jnf.id'))->assertOk();
        $this->assertDatabaseCount('jnfs', 0);
    }

    public function test_login_requires_email_or_roll_no(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'whatever',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'roll_no']);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@gmail.com',
            'password' => 'secret123',
            'role' => 'company',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@gmail.com',
            'password' => 'secret123',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account suspended. Contact CDC.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_suspended_user_is_rejected_on_authenticated_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account suspended. Contact CDC.');

        $this->getJson('/api/auth/user')
            ->assertForbidden();
    }

    public function test_active_users_are_unaffected_by_the_active_middleware(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/auth/user')->assertOk();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_settings_service_reads_writes_and_audits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = app(SettingsService::class);

        $this->assertSame('queued', $settings->get('mail_mode'));

        $settings->set('mail_mode', 'sync', $admin);

        $this->assertSame('sync', $settings->get('mail_mode'));
        $this->assertDatabaseHas('portal_settings', ['key' => 'mail_mode']);

        $log = AuditLog::query()->where('action', 'setting.update')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['key' => 'mail_mode', 'value' => null], $log->before);
        $this->assertSame(['key' => 'mail_mode', 'value' => 'sync'], $log->after);
    }

    public function test_programme_catalogue_merges_built_in_and_custom_branches(): void
    {
        $btech = 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)';

        $this->assertCount(8, ProgrammeCatalogue::programmes());
        $this->assertTrue(ProgrammeCatalogue::has($btech, 'Computer Science & Engineering'));
        $this->assertTrue(ProgrammeCatalogue::has('Ph.D - GATE/NET', 'All Departments (Specify in Job Description)'));
        $this->assertFalse(ProgrammeCatalogue::has($btech, 'Astrophysics'));

        ProgrammeBranch::create([
            'programme_name' => $btech,
            'branch_name' => 'Astrophysics',
            'is_custom' => true,
            'is_active' => true,
        ]);
        ProgrammeBranch::create([
            'programme_name' => $btech,
            'branch_name' => 'Retired Branch',
            'is_custom' => true,
            'is_active' => false,
        ]);

        $this->assertTrue(ProgrammeCatalogue::has($btech, 'Astrophysics'));
        $this->assertFalse(ProgrammeCatalogue::has($btech, 'Retired Branch'));
    }
}
