<?php

namespace Tests\Feature;

use App\Mail\PortalNoticeMail;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Superset parity S8.4: Student Categories for Placement and the Allowed Student Categories eligibility rule.
 */
class StudentCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);

        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [
                    ['type' => 'aptitude_test', 'enabled' => true],
                    ['type' => 'technical_interview', 'enabled' => true],
                    ['type' => 'hr_interview', 'enabled' => true],
                ],
            ],
        ]);

        for ($i = 0; $i < 6; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1), 'phone' => '9000000000']);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $this->students[] = $s;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach ($this->students as $s) {
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => $s->roll_no === '22JE0001' ? 'pending' : 'approved']);
            Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => 'applied', 'used_unverified_resume' => $resume->status !== 'approved', 'applied_at' => now(),
            ]);
        }
    }

    private function round(int $index)
    {
        return $this->posting->rounds()->get()[$index];
    }

    private function closeApplications(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private function category(string $title): int
    {
        return $this->postJson('/api/admin/student-categories', ['title' => $title, 'description' => 'x'])->assertCreated()->json('category.id');
    }

    public function test_crud_assign_and_audit(): void
    {
        $id = $this->category('Minor in Data Science');
        $this->postJson('/api/admin/student-categories', ['title' => 'Minor in Data Science'])->assertStatus(422);

        $this->postJson("/api/admin/student-categories/{$id}/students", ['roll_nos' => ['22je0001', '22JE0002', 'NOPE1']])
            ->assertOk()->assertJsonPath('added', 2)->assertJsonPath('errors.0.roll_no', 'NOPE1');
        $this->postJson("/api/admin/student-categories/{$id}/students", ['roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('added', 0);

        $csv = tempnam(sys_get_temp_dir(), 'cat').'.csv';
        file_put_contents($csv, "Roll No\n22JE0003\n");
        $this->post("/api/admin/student-categories/{$id}/students", ['file' => new UploadedFile($csv, 'c.csv', 'text/csv', null, true)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('added', 1);
        @unlink($csv);

        $this->getJson("/api/admin/student-categories/{$id}/students")->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/admin/student-categories')->assertOk()->assertJsonPath('categories.0.students_count', 3);
        $this->getJson("/api/admin/students/{$this->students[0]->id}/categories")->assertJsonPath('categories.0.title', 'Minor in Data Science');
        $this->deleteJson("/api/admin/student-categories/{$id}/students/{$this->students[2]->id}")->assertOk();
        $this->patchJson("/api/admin/student-categories/{$id}", ['title' => 'Minor in DS'])->assertOk();

        foreach (['student_category.create', 'student_category.assign', 'student_category.unassign', 'student_category.update'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }
    }

    public function test_allowed_categories_restrict_eligibility_and_check_agrees_with_the_query(): void
    {
        $ds = $this->category('Minor in Data Science');
        $dm = $this->category('Double Major in CSE');
        $this->postJson("/api/admin/student-categories/{$ds}/students", ['roll_nos' => ['22JE0001']])->assertOk();
        $this->postJson("/api/admin/student-categories/{$dm}/students", ['roll_nos' => ['22JE0002']])->assertOk();

        $this->patchJson("/api/admin/postings/{$this->posting->id}/eligibility", ['allowedStudentCategories' => [$ds, $dm], 'notify_newly_eligible' => false])->assertOk();
        $posting = $this->posting->fresh();
        $this->assertSame([$ds, $dm], $posting->eligibility_snapshot['allowedStudentCategories']);

        $service = app(\App\Services\EligibilityService::class);
        $queryIds = $service->eligibleStudentsQuery($posting)->pluck('id')->sort()->values()->all();
        $checkIds = collect($this->students)->filter(fn ($s) => $service->check($s->fresh(), $posting)['eligible'])->pluck('id')->sort()->values()->all();
        $this->assertSame($checkIds, $queryIds);
        $this->assertSame([$this->students[0]->id, $this->students[1]->id], $queryIds);
        $this->assertContains('Requires student category: Double Major in CSE or Minor in Data Science.', $service->check($this->students[3]->fresh(), $posting)['reasons']);

        // Clearing the list removes the restriction.
        $this->patchJson("/api/admin/postings/{$this->posting->id}/eligibility", ['allowedStudentCategories' => [], 'notify_newly_eligible' => false])->assertOk();
        $this->assertCount(6, $service->eligibleStudentsQuery($this->posting->fresh())->get());

        // A category required by a live job profile cannot be deleted.
        $this->patchJson("/api/admin/postings/{$this->posting->id}/eligibility", ['allowedStudentCategories' => [$ds], 'notify_newly_eligible' => false])->assertOk();
        $this->deleteJson("/api/admin/student-categories/{$ds}")->assertStatus(422);
        $this->deleteJson("/api/admin/student-categories/{$dm}")->assertOk();
        $this->patchJson("/api/admin/postings/{$this->posting->id}/eligibility", ['allowedStudentCategories' => [999999]])->assertStatus(422);
    }

    public function test_opening_a_job_profile_with_categories(): void
    {
        $ds = $this->category('Minor in Data Science');
        $this->postJson("/api/admin/student-categories/{$ds}/students", ['roll_nos' => ['22JE0004']])->assertOk();
        $company = Company::create(['name' => 'Zed', 'hr_name' => 'HR', 'hr_email' => 'hr@zed.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'DS', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => [
            'jobTitle' => 'DS',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
        ]]);
        $preview = $this->getJson("/api/admin/postings/preview-eligibility?form_type=jnf&form_id={$jnf->id}&cycle_id={$this->posting->placement_cycle_id}&allowed_student_categories[]={$ds}")->assertOk();
        $this->assertSame(1, $preview->json('eligible_count'));
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->posting->placement_cycle_id,
            'application_deadline' => now()->addDay()->toIso8601String(), 'allowed_student_categories' => [$ds],
        ])->assertCreated()->json('posting.id');
        $this->assertSame([$this->students[3]->id], app(\App\Services\EligibilityService::class)->eligibleStudentsQuery(JobPosting::find($id))->pluck('id')->all());
    }

    public function test_permissions(): void
    {
        $id = $this->category('X');
        foreach ([$this->students[0]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/student-categories')->assertForbidden();
            $this->postJson('/api/admin/student-categories', ['title' => 'Y'])->assertForbidden();
            $this->postJson("/api/admin/student-categories/{$id}/students", ['roll_nos' => ['22JE0001']])->assertForbidden();
        }
    }
}
