<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Superset parity S8.1–S8.3: Account branding, the Users directory and Draft placements. */
class AdminHubTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    private function admin(bool $super = false): User
    {
        return User::factory()->create(['role' => 'admin', 'is_super_admin' => $super]);
    }

    private function companyUser(): User
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);

        return User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
    }

    private function png(int $kb = 10): UploadedFile
    {
        return UploadedFile::fake()->image('logo.png', 64, 64)->size($kb);
    }

    // ---- S8.1 Account: logo and institute name ----

    public function test_logo_upload_is_stored_privately_served_publicly_with_nosniff_and_audited(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/branding')->assertExactJson(['display_name' => null, 'has_logo' => false]);
        $this->get('/api/branding/logo')->assertNotFound();

        $this->post('/api/admin/settings/logo', ['logo' => $this->png()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('branding.has_logo', true);

        $setting = \App\Models\PortalSetting::query()->where('key', 'account_logo')->sole();
        $path = $setting->value['path'];
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('branding/', $path);

        $log = AuditLog::query()->where('action', 'settings.logo_update')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertNull($log->before['value']);
        $this->assertSame($path, $log->after['value']['path']);

        // Public: no token needed.
        $this->app['auth']->forgetGuards();
        $response = $this->get('/api/branding/logo');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->getContent());

        // A matching ETag revalidates without the bytes.
        $this->get('/api/branding/logo', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);

        $this->getJson('/api/branding')->assertExactJson(['display_name' => null, 'has_logo' => true]);
    }

    public function test_a_new_logo_replaces_the_old_file_and_delete_falls_back_to_404(): void
    {
        Sanctum::actingAs($this->admin());
        $this->post('/api/admin/settings/logo', ['logo' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $first = \App\Models\PortalSetting::query()->where('key', 'account_logo')->sole()->value['path'];

        $this->post('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.jpg', 32, 32)], ['Accept' => 'application/json'])->assertOk();
        $second = \App\Models\PortalSetting::query()->where('key', 'account_logo')->sole()->value['path'];
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->deleteJson('/api/admin/settings/logo')->assertOk()->assertJsonPath('branding.has_logo', false);
        Storage::disk('local')->assertMissing($second);
        $this->get('/api/branding/logo')->assertNotFound();
        $this->assertSame(3, AuditLog::query()->where('action', 'settings.logo_update')->count());
    }

    public function test_logo_type_and_size_limits_and_svg_is_refused(): void
    {
        Sanctum::actingAs($this->admin());

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/api/admin/settings/logo', ['logo' => $svg], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('logo');

        // SVG content behind a .png name is refused by content.
        $disguised = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        $this->post('/api/admin/settings/logo', ['logo' => $disguised], ['Accept' => 'application/json'])->assertStatus(422);

        $gif = UploadedFile::fake()->image('logo.gif', 10, 10);
        $this->post('/api/admin/settings/logo', ['logo' => $gif], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/admin/settings/logo', ['logo' => $this->png(1025)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.logo.0', 'The logo must be 1 MB or smaller.');

        $this->post('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.webp', 20, 20)], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.logo_update')->count());
    }

    public function test_institute_name_is_saved_trimmed_audited_and_public(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings', ['institute_name' => '  IIT (ISM) Dhanbad  '])
            ->assertOk()
            ->assertJsonPath('branding.display_name', 'IIT (ISM) Dhanbad');

        $log = AuditLog::query()->where('action', 'settings.institute_name_update')->sole();
        $this->assertSame('IIT (ISM) Dhanbad', $log->after['value']);
        $this->assertNull($log->before['value']);

        // Saving the same value again writes nothing; mail mode keeps its own audit name.
        $this->patchJson('/api/admin/settings', ['institute_name' => 'IIT (ISM) Dhanbad'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.institute_name_update')->count());
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'setting.update')->count());

        $this->patchJson('/api/admin/settings', ['institute_name' => str_repeat('x', 151)])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/branding')->assertExactJson(['display_name' => 'IIT (ISM) Dhanbad', 'has_logo' => false]);

        // The email header uses it.
        $html = view('emails.layouts.portal')->render();
        $this->assertStringContainsString('IIT (ISM) Dhanbad', $html);
    }

    public function test_email_header_uses_an_absolute_logo_url_only_when_set(): void
    {
        $this->assertStringNotContainsString('/api/branding/logo', view('emails.layouts.portal')->render());

        Sanctum::actingAs($this->admin());
        $this->post('/api/admin/settings/logo', ['logo' => $this->png()], ['Accept' => 'application/json'])->assertOk();

        $html = view('emails.layouts.portal')->render();
        $this->assertStringContainsString(url('/api/branding/logo').'?v=', $html);
        $this->assertStringContainsString('IIT (ISM) Dhanbad', $html); // built-in name stays when no display name is set
    }

    public function test_settings_admin_routes_are_admin_only_and_branding_exposes_nothing_else(): void
    {
        $student = StudentProfile::factory()->create();
        $cycle = PlacementCycle::create($this->cyclePayload(['is_draft' => true]) + ['status' => 'open']);
        foreach ([$student->user, $this->companyUser()] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/settings')->assertForbidden();
            $this->patchJson('/api/admin/settings', ['institute_name' => 'X'])->assertForbidden();
            $this->post('/api/admin/settings/logo', ['logo' => $this->png()], ['Accept' => 'application/json'])->assertForbidden();
            $this->deleteJson('/api/admin/settings/logo')->assertForbidden();
            $this->getJson('/api/admin/manage-admins')->assertForbidden();
            $this->patchJson("/api/admin/placement-cycles/{$cycle->id}/publish")->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $keys = array_keys($this->getJson('/api/branding')->assertOk()->json());
        sort($keys);
        $this->assertSame(['display_name', 'has_logo'], $keys);
    }

    // ---- S8.2 Users directory ----

    public function test_super_admin_edits_a_user_with_audit_and_email_stays_read_only(): void
    {
        $super = $this->admin(true);
        $target = User::factory()->create(['role' => 'admin', 'name' => 'Old Name', 'email' => 'officer@cdc.test']);
        Sanctum::actingAs($super);

        $this->patchJson("/api/admin/manage-admins/{$target->id}", [
            'first_name' => 'Asha',
            'middle_name' => ' ',
            'last_name' => 'Verma',
            'designation' => 'Placement Officer',
            'mobile' => '+91 9876543210',
            'alias' => 'CDC Office',
            'email' => 'hijack@cdc.test',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Asha Verma')
            ->assertJsonPath('user.designation', 'Placement Officer')
            ->assertJsonPath('user.middle_name', null)
            ->assertJsonPath('user.email', 'officer@cdc.test');

        $target->refresh();
        $this->assertSame('officer@cdc.test', $target->email);
        $this->assertSame('+91 9876543210', $target->mobile);
        $this->assertSame('CDC Office', $target->alias);

        $log = AuditLog::query()->where('action', 'admin.update')->sole();
        $this->assertSame($super->id, $log->user_id);
        $this->assertSame('Old Name', $log->before['name']);
        $this->assertSame('Asha Verma', $log->after['name']);
        $this->assertSame('Placement Officer', $log->after['designation']);
        $this->assertArrayNotHasKey('email', $log->after);

        // Nothing changed → no audit row; bad mobile and missing first name are refused.
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'Asha', 'last_name' => 'Verma', 'designation' => 'Placement Officer', 'mobile' => '+91 9876543210', 'alias' => 'CDC Office'])
            ->assertOk()->assertJsonPath('message', 'Nothing changed.');
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.update')->count());
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'Asha', 'mobile' => '98765'])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['last_name' => 'Verma'])->assertStatus(422)->assertJsonValidationErrors('first_name');

        // Only admin users are edited here.
        $student = StudentProfile::factory()->create();
        $this->patchJson("/api/admin/manage-admins/{$student->user_id}", ['first_name' => 'X'])->assertStatus(422);

        // The directory lists the new fields.
        $this->getJson('/api/admin/manage-admins')->assertOk()->assertJsonFragment(['designation' => 'Placement Officer', 'alias' => 'CDC Office']);
    }

    public function test_a_normal_admin_cannot_edit_or_list_users(): void
    {
        $target = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/manage-admins')->assertForbidden();
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'X'])->assertForbidden();
        $this->assertSame(0, AuditLog::query()->where('action', 'admin.update')->count());

        Sanctum::actingAs(StudentProfile::factory()->create()->user);
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'X'])->assertForbidden();
        Sanctum::actingAs($this->companyUser());
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'X'])->assertForbidden();
    }

    // ---- S8.3 Draft placements ----

    private function cyclePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Internship 2029',
            'type' => 'fulltime',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ], $overrides);
    }

    private function acceptedJnf(): Jnf
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);

        return Jnf::create(['company_id' => $company->id, 'job_title' => 'Software Engineer', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => [
            'jobTitle' => 'Software Engineer',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'selectionRounds' => [['id' => '1', 'type' => 'hr_interview', 'enabled' => true]],
        ]]);
    }

    public function test_save_as_draft_refuses_floats_allows_enrolment_and_publish_is_audited(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/placement-cycles', $this->cyclePayload(['is_draft' => true]))
            ->assertCreated()
            ->assertJsonPath('placement_cycle.is_draft', true);
        $cycle = PlacementCycle::sole();
        $this->assertTrue($cycle->is_draft);
        $this->assertTrue(AuditLog::query()->where('action', 'cycle.create')->sole()->after['is_draft']);

        // Enrolment is allowed while it is a draft.
        $student = StudentProfile::factory()->create();
        $this->postJson("/api/admin/placement-cycles/{$cycle->id}/enroll", ['roll_nos' => [$student->roll_no]])->assertSuccessful();
        $this->assertTrue(CycleEnrollment::query()->where('placement_cycle_id', $cycle->id)->where('student_profile_id', $student->id)->exists());

        // No job profile can be opened for applications into it.
        $jnf = $this->acceptedJnf();
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $jnf->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertStatus(422)->assertJsonPath('message', 'The selected placement is a draft. Publish the placement before opening job profiles for applications in it.');
        $this->assertSame(0, JobPosting::count());

        $this->patchJson("/api/admin/placement-cycles/{$cycle->id}/publish")
            ->assertOk()
            ->assertJsonPath('placement_cycle.is_draft', false);
        $log = AuditLog::query()->where('action', 'cycle.publish')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['is_draft' => true], $log->before);
        $this->assertSame(['is_draft' => false], $log->after);
        $this->patchJson("/api/admin/placement-cycles/{$cycle->id}/publish")->assertStatus(422);

        // Published: floats work again; creating without the flag is never a draft.
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $jnf->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated();
        $this->postJson('/api/admin/placement-cycles', $this->cyclePayload(['name' => 'FT']))->assertCreated()->assertJsonPath('placement_cycle.is_draft', false);
    }

    public function test_a_draft_placement_is_hidden_from_students_everywhere(): void
    {
        $admin = $this->admin();
        $cycle = PlacementCycle::create($this->cyclePayload() + ['status' => 'open']);
        $student = StudentProfile::factory()->create();
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $this->acceptedJnf()->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'visit_date' => now('Asia/Kolkata')->addDays(2)->toDateString(),
        ])->assertCreated();
        $posting = JobPosting::sole();
        $month = now('Asia/Kolkata')->addDays(3)->format('Y-m');

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 1);
        $this->assertNotEmpty($this->getJson('/api/student/dashboard')->json('nudges'));

        // A draft never takes job profiles; flip it directly to prove the student reads still hide it.
        $cycle->update(['is_draft' => true]);

        $this->getJson('/api/student/postings')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => 1, 'answers' => []])->assertNotFound();
        $dashboard = $this->getJson('/api/student/dashboard')->assertOk();
        $this->assertSame([], $dashboard->json('nudges'));
        $this->assertSame([], $dashboard->json('upcoming'));
        $calendar = $this->getJson("/api/student/calendar?month={$month}")->assertOk();
        $this->assertSame([], collect($calendar->json('items'))->filter(fn ($i) => str_contains(json_encode($i), (string) $posting->id) && ($i['type'] ?? '') !== 'event')->values()->all());
    }
}
