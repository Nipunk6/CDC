<?php

namespace Tests\Feature\Security;

use App\Mail\ApplicationSubmittedMail;
use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Security audit Part 5.4 (mass assignment / parameter tampering) and 5.5 (business logic).
 * Every request carries fields the caller must not be able to set; the database is checked afterwards.
 */
#[Group('security')]
class MassAssignmentAndLogicTest extends TestCase
{
    use RefreshDatabase;

    private const SMUGGLE = [
        'role' => 'admin', 'is_super_admin' => true, 'is_active' => true, 'company_id' => 999, 'user_id' => 1,
        'student_profile_id' => 1, 'status' => 'approved', 'published_at' => '2026-01-01 00:00:00',
        'used_unverified_resume' => false, 'placed_elsewhere_flag' => false, 'current_cgpa' => 10, 'ongoing_backlogs' => 0,
        'branch' => 'Mining Engineering', 'roll_no' => 'HACKED01', 'admin_remark' => 'pwned', 'admin_remarks' => 'pwned',
        'decided_by' => 1, 'reviewed_by' => 1, 'share_contact_details' => true, 'result' => 'selected',
    ];

    private StudentProfile $student;

    private User $companyUser;

    private Company $company;

    private JobPosting $posting;

    private Resume $resume;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $cycle = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]]);
        $this->company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $this->company->id]);
        $jnf = Jnf::create(['company_id' => $this->company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => [
            'jobTitle' => 'SDE',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
        ]]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()])->assertCreated();
        $this->posting = JobPosting::sole();

        $this->student = StudentProfile::factory()->create(['roll_no' => '22JE0001', 'current_cgpa' => 7.0, 'ongoing_backlogs' => 1]);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $this->student->id, 'status' => 'active']);
        Storage::disk('local')->put('resumes/x.pdf', '%PDF-1.4');
        $this->resume = $this->student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 9, 'status' => 'pending']);
        $this->app['auth']->forgetGuards();
    }

    public function test_5_4_student_profile_update_ignores_privileged_fields(): void
    {
        Sanctum::actingAs($this->student->user);
        $this->patchJson('/api/student/profile', self::SMUGGLE + ['phone' => '9876543210'])->assertOk();

        $p = $this->student->fresh();
        $u = $p->user->fresh();
        $this->assertSame(['22JE0001', 'Computer Science & Engineering', '7.00', 1], [$p->roll_no, $p->branch, (string) $p->current_cgpa, (int) $p->ongoing_backlogs]);
        $this->assertSame(['student', false, null], [$u->role, (bool) $u->is_super_admin, $u->company_id]);
        $this->assertSame('9876543210', $p->phone, 'the allowed field still saves');
    }

    public function test_5_4_resume_upload_and_relabel_cannot_self_approve(): void
    {
        Sanctum::actingAs($this->student->user);
        $this->post('/api/student/resumes', self::SMUGGLE + ['slot' => 2, 'label' => 'New', 'file' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')], ['Accept' => 'application/json']);
        $this->patchJson("/api/student/resumes/{$this->resume->id}", self::SMUGGLE + ['label' => 'Renamed']);

        $this->assertSame(0, Resume::where('status', 'approved')->count(), 'a student approved their own resume');
        $this->assertSame(0, Resume::whereNotNull('reviewed_by')->count());
    }

    public function test_5_4_apply_cannot_set_status_or_flags_and_5_5_cannot_use_another_students_resume(): void
    {
        $other = StudentProfile::factory()->create();
        $otherResume = $other->resumes()->create(['slot' => 1, 'label' => 'Theirs', 'file_path' => 'resumes/x.pdf', 'file_size' => 9, 'status' => 'approved']);

        Sanctum::actingAs($this->student->user);
        $this->postJson("/api/student/postings/{$this->posting->id}/apply", ['resume_id' => $otherResume->id])->assertStatus(422); // L5.2
        $this->postJson("/api/student/postings/{$this->posting->id}/apply", self::SMUGGLE + ['resume_id' => $this->resume->id])->assertCreated();

        $app = Application::sole();
        $this->assertSame(['applied', true, false, $this->student->id], [$app->status, (bool) $app->used_unverified_resume, (bool) $app->placed_elsewhere_flag, $app->student_profile_id]);
    }

    public function test_5_4_company_form_and_proposal_cannot_self_accept_or_inject_results(): void
    {
        Sanctum::actingAs($this->companyUser);
        $this->postJson('/api/company/jnfs', self::SMUGGLE + ['job_title' => 'Smuggle', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => json_encode(['jobTitle' => 'Smuggle'])]);
        $jnf = Jnf::where('job_title', 'Smuggle')->first();
        if ($jnf) {
            $this->assertNotSame('accepted', $jnf->status, 'company accepted its own form (A2)');
            $this->assertNull($jnf->admin_remarks);
            $this->assertSame($this->company->id, $jnf->company_id);
        }

        // Proposal for someone who is not an applicant, with an injected result.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::where('role', 'admin')->first());
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->companyUser);
        $round = $this->posting->rounds()->first();
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$round->id}/proposals", self::SMUGGLE + ['kind' => 'shortlist', 'entries' => [['roll_no' => '22JE0001', 'result' => 'selected']]])->assertStatus(422);
        $this->assertSame(0, ShortlistProposal::count());
        $this->assertSame(0, $round->results()->count(), 'nothing reaches the pipeline without the CDC');
    }

    /** L5.7 — students never learn applicant counts from any payload they receive. */
    public function test_5_5_students_never_receive_applicant_counts(): void
    {
        Sanctum::actingAs($this->student->user);
        $this->postJson("/api/student/postings/{$this->posting->id}/apply", ['resume_id' => $this->resume->id])->assertCreated();
        foreach (['/api/student/postings', "/api/student/postings/{$this->posting->id}", '/api/student/applications', '/api/student/dashboard', '/api/student/calendar'] as $uri) {
            $body = $this->getJson($uri)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/"(applicant_count|applicants_count|applications_count|applied_count|total_applicants)"/', $body, $uri);
        }
    }

    /** L5.5 — withdraw/re-apply loops mail only the student themself, once per apply (Info). */
    public function test_5_5_reapply_loop_only_mails_the_student(): void
    {
        Sanctum::actingAs($this->student->user);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/student/postings/{$this->posting->id}/apply", ['resume_id' => $this->resume->id])->assertSuccessful();
            $app = Application::sole();
            $this->postJson("/api/student/applications/{$app->id}/withdraw")->assertOk();
        }
        Mail::assertQueued(ApplicationSubmittedMail::class, fn ($m) => $m->hasTo($this->student->user->email));
        Mail::assertNotQueued(ApplicationSubmittedMail::class, fn ($m) => ! $m->hasTo($this->student->user->email));
    }
}
