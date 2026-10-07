<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Add New Job" (S6.1): the CDC fills the JNF/INF wizard for a company (picked or created) and the
 * form is accepted directly, ready to be opened for applications.
 */
class AdminCreateJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]];
        $this->ft = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime'] + $base);
        $this->intern = PlacementCycle::create(['name' => 'Intern', 'type' => 'internship'] + $base);
    }

    private function formData(): array
    {
        return [
            'jobTitle' => 'Software Engineer',
            'internshipTitle' => 'Summer Intern',
            'companyProfile' => ['name' => 'Offline Co'],
            'signatory' => ['name' => 'CDC Officer', 'designation' => 'TPO', 'date' => '2026-10-06'],
            'eligibility' => [[
                'programme' => StudentProfileFactory::BTECH,
                'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false]],
            ]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
            'selectionRounds' => [['id' => '1', 'type' => 'hr_interview', 'enabled' => true]],
        ];
    }

    private function company(string $email = 'hr@offline.test'): Company
    {
        return Company::create(['name' => 'Offline Co', 'hr_name' => 'HR', 'hr_email' => $email]);
    }

    private function jnfPayload(array $overrides = []): array
    {
        return array_merge([
            'job_title' => 'Software Engineer',
            'job_description' => 'Build things.',
            'job_location' => 'Bengaluru',
            'form_data' => json_encode($this->formData()),
            'status' => 'submitted',
        ], $overrides);
    }

    public function test_admin_lists_and_creates_a_company_without_a_login_user(): void
    {
        Sanctum::actingAs($this->admin);
        $usersBefore = User::count();

        $response = $this->postJson('/api/admin/form-builder/companies', [
            'name' => 'Offline Co',
            'hr_name' => 'Asha Rao',
            'hr_email' => 'Asha@Offline.test',
            'website' => 'https://offline.test',
            'sector' => 'Software',
        ])->assertCreated()->assertJsonPath('company.name', 'Offline Co');

        $company = Company::findOrFail($response->json('company.id'));
        $this->assertSame('asha@offline.test', $company->hr_email);
        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, $company->users()->count());

        $audit = AuditLog::where('action', 'company.admin_create')->sole();
        $this->assertSame($company->id, $audit->subject_id);
        $this->assertSame('asha@offline.test', $audit->after['hr_email']);

        // Duplicate HR email and missing fields are rejected.
        $this->postJson('/api/admin/form-builder/companies', ['name' => 'X', 'hr_name' => 'Y', 'hr_email' => 'asha@offline.test'])
            ->assertStatus(422)->assertJsonValidationErrors('hr_email');
        $this->postJson('/api/admin/form-builder/companies', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'hr_name', 'hr_email']);

        $this->company('other@elsewhere.test')->update(['name' => 'Elsewhere Ltd']);
        $this->getJson('/api/admin/form-builder/companies?search=offline')
            ->assertOk()->assertJsonCount(1, 'companies')->assertJsonPath('companies.0.hr_email', 'asha@offline.test');
        $this->getJson('/api/admin/form-builder/companies')->assertOk()->assertJsonCount(2, 'companies');
    }

    public function test_profile_and_policy_documents_mirror_the_company_endpoints(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/admin/form-builder/{$company->id}/profile")->assertOk()->assertJsonPath('company.name', 'Offline Co');
        $this->getJson("/api/admin/form-builder/{$company->id}/policy-documents?form_type=jnf")->assertOk();
        $this->getJson("/api/admin/form-builder/{$company->id}/policy-documents")->assertStatus(400);
        $this->getJson('/api/admin/form-builder/999999/profile')->assertNotFound();
    }

    public function test_autosave_keeps_a_draft_then_submit_accepts_and_the_form_floats_with_working_eligibility(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->admin);

        $draft = $this->postJson("/api/admin/form-builder/{$company->id}/jnfs/autosave", [
            'job_title' => 'Untitled JNF Draft',
            'job_description' => 'Draft in progress.',
            'form_data' => json_encode(['graduatingBatch' => '2027']),
            'status' => 'draft',
        ])->assertCreated();
        $id = $draft->json('jnf.id');
        $this->assertSame('draft', Jnf::findOrFail($id)->status);
        $this->assertSame($company->id, Jnf::findOrFail($id)->company_id);

        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs/autosave", [
            'id' => $id,
            'job_title' => 'Software Engineer',
            'job_description' => 'Draft in progress.',
        ])->assertOk()->assertJsonPath('jnf.status', 'draft');
        $this->assertSame(0, AuditLog::where('action', 'form.admin_create')->count());

        $this->putJson("/api/admin/form-builder/{$company->id}/jnfs/{$id}", $this->jnfPayload())
            ->assertOk()->assertJsonPath('jnf.status', 'accepted');

        $jnf = Jnf::findOrFail($id);
        $this->assertSame('accepted', $jnf->status);
        $this->assertSame('2027', $jnf->form_data['graduatingBatch']);
        $history = FormStatusHistory::where('form_type', Jnf::class)->where('form_id', $id)->latest('id')->first();
        $this->assertSame('accepted', $history->new_status);
        $this->assertSame('draft', $history->old_status);
        $this->assertSame('Created by the CDC.', $history->remarks);
        $audit = AuditLog::where('action', 'form.admin_create')->sole();
        $this->assertSame('jnf', $audit->after['form_type']);
        $this->assertSame($id, $audit->after['form_id']);
        $this->assertSame($company->id, $audit->after['company_id']);

        // Once accepted, the wizard endpoints refuse further edits.
        $this->putJson("/api/admin/form-builder/{$company->id}/jnfs/{$id}", $this->jnfPayload())->assertStatus(422);
        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs/autosave", ['id' => $id, 'job_title' => 'X', 'job_description' => 'Y'])->assertStatus(422);

        // It floats like any accepted company form, and eligibility reads its form_data.
        $eligible = StudentProfile::factory()->create();
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $eligible->id, 'status' => 'active']);
        $low = StudentProfile::factory()->create(['current_cgpa' => 6.0]);
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $low->id, 'status' => 'active']);

        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $id,
            'placement_cycle_id' => $this->ft->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('posting.stats.eligible', 1);

        $posting = JobPosting::sole();
        $ids = app(EligibilityService::class)->eligibleStudentsQuery($posting)->pluck('id')->all();
        $this->assertSame([$eligible->id], $ids);
        $this->assertSame(['HR Interview'], $posting->rounds->pluck('name')->all());
    }

    public function test_direct_store_creates_accepted_jnf_and_inf_for_the_company_in_the_url(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->admin);

        $jnfId = $this->postJson("/api/admin/form-builder/{$company->id}/jnfs", $this->jnfPayload())
            ->assertCreated()->assertJsonPath('jnf.status', 'accepted')->json('jnf.id');
        $this->assertSame($company->id, Jnf::findOrFail($jnfId)->company_id);

        $infId = $this->postJson("/api/admin/form-builder/{$company->id}/infs", [
            'internship_title' => 'Summer Intern',
            'internship_description' => 'Intern things.',
            'form_data' => json_encode($this->formData()),
            'status' => 'submitted',
        ])->assertCreated()->assertJsonPath('inf.status', 'accepted')->json('inf.id');
        $this->assertSame($company->id, Inf::findOrFail($infId)->company_id);
        $this->assertSame(2, AuditLog::where('action', 'form.admin_create')->count());

        $this->postJson('/api/admin/postings', [
            'form_type' => 'inf',
            'form_id' => $infId,
            'placement_cycle_id' => $this->intern->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('posting.type', 'internship');
    }

    public function test_company_in_the_url_scopes_every_write(): void
    {
        $mine = $this->company();
        $other = $this->company('hr@other.test');
        $otherJnf = Jnf::create(['company_id' => $other->id, 'job_title' => 'T', 'job_description' => 'D', 'status' => 'draft']);
        $otherInf = Inf::create(['company_id' => $other->id, 'internship_title' => 'T', 'internship_description' => 'D', 'status' => 'draft']);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/admin/form-builder/{$mine->id}/jnfs/{$otherJnf->id}", $this->jnfPayload())->assertNotFound();
        $this->postJson("/api/admin/form-builder/{$mine->id}/jnfs/autosave", ['id' => $otherJnf->id, 'job_title' => 'X', 'job_description' => 'Y'])->assertNotFound();
        $this->putJson("/api/admin/form-builder/{$mine->id}/infs/{$otherInf->id}", [
            'internship_title' => 'X', 'internship_description' => 'Y', 'status' => 'submitted',
        ])->assertNotFound();
        $this->postJson("/api/admin/form-builder/{$mine->id}/infs/autosave", ['id' => $otherInf->id, 'internship_title' => 'X', 'internship_description' => 'Y'])->assertNotFound();

        $this->assertSame('draft', $otherJnf->fresh()->status);
        $this->assertSame('T', $otherJnf->fresh()->job_title);
        $this->assertSame('draft', $otherInf->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'form.admin_create')->count());
    }

    public function test_validation_errors(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs", [])->assertStatus(422)->assertJsonValidationErrors(['job_title', 'job_description']);
        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs", $this->jnfPayload(['form_data' => 'not json']))->assertStatus(422)->assertJsonValidationErrors('form_data');
        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs", $this->jnfPayload(['ctc_min' => 10, 'ctc_max' => 5]))->assertStatus(422)->assertJsonValidationErrors('ctc_max');
        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs", $this->jnfPayload([
            'form_data' => json_encode(['joiningMonth' => now()->subMonth()->format('Y-m')] + $this->formData()),
        ]))->assertStatus(422)->assertJsonValidationErrors('joiningMonth');
        $this->postJson("/api/admin/form-builder/{$company->id}/infs", [])->assertStatus(422)->assertJsonValidationErrors(['internship_title', 'internship_description']);
        $this->postJson("/api/admin/form-builder/{$company->id}/jnfs/autosave", [])->assertStatus(422)->assertJsonValidationErrors(['job_title', 'job_description']);
        $this->postJson("/api/admin/form-builder/{$company->id}/infs/autosave", [])->assertStatus(422)->assertJsonValidationErrors(['internship_title', 'internship_description']);

        $this->assertSame(0, Jnf::count() + Inf::count());
    }

    public function test_students_and_companies_get_403_on_every_route(): void
    {
        $company = $this->company();
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'T', 'job_description' => 'D', 'status' => 'draft']);
        $inf = Inf::create(['company_id' => $company->id, 'internship_title' => 'T', 'internship_description' => 'D', 'status' => 'draft']);
        $student = StudentProfile::factory()->create()->user;
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id]);

        $routes = [
            ['get', '/api/admin/form-builder/companies'],
            ['post', '/api/admin/form-builder/companies'],
            ['get', "/api/admin/form-builder/{$company->id}/profile"],
            ['get', "/api/admin/form-builder/{$company->id}/policy-documents?form_type=jnf"],
            ['post', "/api/admin/form-builder/{$company->id}/jnfs/autosave"],
            ['post', "/api/admin/form-builder/{$company->id}/jnfs"],
            ['put', "/api/admin/form-builder/{$company->id}/jnfs/{$jnf->id}"],
            ['post', "/api/admin/form-builder/{$company->id}/infs/autosave"],
            ['post', "/api/admin/form-builder/{$company->id}/infs"],
            ['put', "/api/admin/form-builder/{$company->id}/infs/{$inf->id}"],
        ];

        foreach ([$student, $companyUser] as $user) {
            Sanctum::actingAs($user);
            foreach ($routes as [$method, $uri]) {
                $this->json($method, $uri, $this->jnfPayload())->assertForbidden();
            }
        }

        $this->assertSame('draft', $jnf->fresh()->status);
        $this->assertSame(1, Company::count());
    }
}
