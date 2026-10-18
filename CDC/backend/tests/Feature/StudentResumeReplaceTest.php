<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner decision 2026-10-18: a resume can be replaced once no application using it is still in progress (neither
 * accepted nor rejected), and the version earlier applications were made with stays exactly as it was for the CDC
 * and the company — it is archived, never overwritten.
 */
class StudentResumeReplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycle;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->student = StudentProfile::factory()->create(['roll_no' => '22JE0001']);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->student->id, 'status' => 'active']);
    }

    private function upload(int $slot = 1, string $content = '%PDF-1.4 v1')
    {
        Sanctum::actingAs($this->student->user);

        return $this->post('/api/student/resumes', [
            'slot' => $slot, 'label' => 'SDE',
            'file' => UploadedFile::fake()->createWithContent('cv.pdf', $content)->mimeType('application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    private function applyWith(Resume $resume): Application
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated();
        $posting = JobPosting::latest('id')->first();

        return Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $this->student->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
    }

    public function test_a_resume_in_an_application_still_in_progress_cannot_be_replaced(): void
    {
        $this->upload()->assertCreated();
        $resume = Resume::sole();
        $this->applyWith($resume);

        $this->upload(1, '%PDF-1.4 v2')->assertStatus(422);
        $this->assertSame($resume->file_path, $resume->fresh()->file_path);
    }

    public function test_once_rejected_it_can_be_replaced_and_the_application_keeps_the_original(): void
    {
        $this->upload()->assertCreated();
        $original = Resume::sole();
        $application = $this->applyWith($original);
        ApplicationRoundResult::create([
            'application_id' => $application->id, 'posting_round_id' => $application->jobPosting->rounds()->first()->id,
            'result' => 'rejected', 'published_at' => now(), 'decided_by' => $this->admin->id,
        ]);

        $this->upload(1, '%PDF-1.4 v2')->assertOk();

        // The application still points at the file it was made with, unchanged.
        $original->refresh();
        $this->assertSame($original->id, $application->fresh()->resume_id);
        $this->assertNotNull($original->archived_at);
        $this->assertNull($original->slot);
        $this->assertSame('%PDF-1.4 v1', Storage::disk('local')->get($original->file_path));

        // The student's slot 1 is the new file; the archived version is not listed, nor queued for verification.
        Sanctum::actingAs($this->student->user);
        $listed = $this->getJson('/api/student/resumes')->assertOk()->json('resumes');
        $this->assertCount(1, $listed);
        $this->assertNotSame($original->id, $listed[0]['id']);
        $this->assertSame('%PDF-1.4 v2', Storage::disk('local')->get(Resume::find($listed[0]['id'])->file_path));
        Sanctum::actingAs($this->admin);
        $this->assertSame([$listed[0]['id']], collect($this->getJson('/api/admin/resumes')->assertOk()->json('resumes'))->pluck('id')->all());
    }

    public function test_once_accepted_with_an_offer_it_can_be_replaced(): void
    {
        $this->upload()->assertCreated();
        $original = Resume::sole();
        $application = $this->applyWith($original);
        Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $this->student->id, 'company_id' => $application->jobPosting->company()->id,
            'job_posting_id' => $application->job_posting_id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime',
            'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);

        $this->upload(1, '%PDF-1.4 v2')->assertOk();
        $this->assertSame($original->id, $application->fresh()->resume_id);
        $this->assertNotNull($original->fresh()->archived_at);
    }

    public function test_a_resume_never_used_is_replaced_in_place(): void
    {
        $this->upload()->assertCreated();
        $original = Resume::sole();

        $this->upload(1, '%PDF-1.4 v2')->assertOk();
        $this->assertSame(1, Resume::count(), 'nothing to keep, so no archived copy');
        $this->assertSame($original->id, Resume::sole()->id);
        $this->assertFalse(Storage::disk('local')->exists($original->file_path), 'the old file is removed');
    }
}
