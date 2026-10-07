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
 * Superset parity S1: the per-stage shortlist workspace, email identifiers, the strict check and the stage download.
 */
class StageShortlistTest extends TestCase
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

    private function url(string $suffix = ''): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}".$suffix;
    }

    public function test_shortlist_lists_the_stage_pool_with_draft_decisions_and_navigation(): void
    {
        $this->closeApplications();
        $this->postJson($this->url('/results'), ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0003', 'result' => 'rejected'],
        ]])->assertOk()->assertJsonPath('written', 3);

        $response = $this->getJson($this->url('/shortlist'))->assertOk();
        $response->assertJsonPath('counts.candidates', 6)
            ->assertJsonPath('counts.selected', 1)
            ->assertJsonPath('counts.waitlisted', 1)
            ->assertJsonPath('counts.rejected', 1)
            ->assertJsonPath('counts.undecided', 3)
            ->assertJsonPath('counts.drafts', 3)
            ->assertJsonPath('counts.published', 0)
            ->assertJsonPath('previous_round', null)
            ->assertJsonPath('next_round.id', $this->round(1)->id)
            ->assertJsonPath('next_label', $this->round(1)->name)
            ->assertJsonPath('meta.total', 6);

        $first = collect($response->json('candidates'))->firstWhere('student.roll_no', '22JE0001');
        $this->assertSame('selected', $first['result']);
        $this->assertFalse($first['published']);
        $this->assertTrue($first['in_pool']);

        // Filter and search.
        $this->getJson($this->url('/shortlist?decision=undecided'))->assertJsonPath('meta.total', 3);
        $this->getJson($this->url('/shortlist?search=22je0002'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->url('/shortlist?sort=decision'))->assertJsonPath('candidates.0.student.roll_no', '22JE0001');

        // Drafts stay invisible to the student and the company.
        Sanctum::actingAs($this->students[0]->user);
        $this->assertFalse($this->getJson('/api/student/applications')->json('applications.0.trail.0.published'));
        Sanctum::actingAs($this->companyUser);
        $this->assertEmpty($this->getJson("/api/company/postings/{$this->posting->id}/applicants")->json('applicants.0.rounds'));

        // Stage 2 has nobody until stage 1 is published; then its pool is the shortlisted student.
        Sanctum::actingAs($this->admin);
        $r2 = $this->round(1);
        $this->getJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/shortlist")->assertJsonPath('counts.candidates', 0)
            ->assertJsonPath('previous_round.id', $this->round(0)->id);
        $this->postJson($this->url('/publish'), ['reject_remaining' => true])->assertOk();
        $this->getJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/shortlist")->assertJsonPath('counts.candidates', 1);
        $this->getJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(2)->id}/shortlist")->assertJsonPath('next_label', 'FINAL OFFER');
    }

    public function test_entries_can_be_given_by_email_and_unknown_ones_are_reported(): void
    {
        $this->closeApplications();
        $email = $this->students[1]->institute_email;

        $this->postJson($this->url('/results'), ['roll_nos' => [strtoupper($email), 'nobody@example.test', '22JE0003'], 'result' => 'selected'])
            ->assertOk()
            ->assertJsonPath('written', 2)
            ->assertJsonPath('errors.0.roll_no', 'nobody@example.test');

        $this->assertSame(2, ApplicationRoundResult::query()->where('result', 'selected')->whereNull('published_at')->count());
        $this->assertTrue(ApplicationRoundResult::query()->whereHas('application', fn ($q) => $q->where('student_profile_id', $this->students[1]->id))->exists());
    }

    public function test_strict_check_refuses_students_outside_the_stage_pool(): void
    {
        $this->closeApplications();
        $this->postJson($this->url('/results'), ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->url('/publish'), ['reject_remaining' => true])->assertOk();

        $r2 = "/api/admin/postings/{$this->posting->id}/rounds/{$this->round(1)->id}/results";

        // Default: outside the pool is saved with a warning (D70(b)).
        $this->postJson($r2, ['roll_nos' => ['22JE0002'], 'result' => 'waitlisted'])->assertOk()
            ->assertJsonPath('written', 0) // rejected earlier → refused by the Re-add rule, reported
            ->assertJsonCount(1, 'errors');

        // Strict: someone not in the pool is refused and reported, never written.
        ApplicationRoundResult::query()->whereHas('application', fn ($q) => $q->where('student_profile_id', $this->students[3]->id))->delete();
        $this->postJson($r2, ['roll_nos' => ['22JE0004'], 'result' => 'selected', 'strict' => true])->assertOk()
            ->assertJsonPath('written', 0)
            ->assertJsonPath('errors.0.roll_no', '22JE0004');
        $this->postJson($r2, ['roll_nos' => ['22JE0004'], 'result' => 'selected'])->assertOk()
            ->assertJsonPath('written', 1)
            ->assertJsonPath('warnings.0.roll_no', '22JE0004');
        $this->postJson($r2, ['roll_nos' => ['22JE0001'], 'result' => 'selected', 'strict' => true])->assertOk()->assertJsonPath('written', 1);
    }

    public function test_download_current_shortlist_is_admin_only_audited_and_formula_safe(): void
    {
        $this->closeApplications();
        $this->students[0]->update(['full_name' => '=HYPERLINK("x")']);
        $this->postJson($this->url('/results'), ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();

        $response = $this->get($this->url('/shortlist/export'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'shortlist');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertStringContainsString("to be proceeded to {$this->round(1)->name}", (string) $sheet->getCell('A1')->getValue());
        $this->assertSame('S.No.', $sheet->getCell('A3')->getValue());
        $values = collect($sheet->toArray())->flatten()->filter()->values();
        $this->assertTrue($values->contains('=HYPERLINK("x")'));
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('C4')->getDataType());
        $this->assertTrue($values->contains('Shortlisted'));
        $this->assertTrue($values->contains('Draft'));
        $this->assertTrue($values->contains(fn ($v) => str_starts_with((string) $v, 'Downloaded on ')));
        $this->assertTrue(AuditLog::query()->where('action', 'round.shortlist_export')->exists());
    }

    public function test_pipeline_payload_carries_offers_for_status_and_trophy(): void
    {
        $this->closeApplications();
        \App\Models\Offer::create([
            'application_id' => Application::query()->where('student_profile_id', $this->students[2]->id)->value('id'),
            'student_profile_id' => $this->students[2]->id, 'company_id' => $this->posting->company()->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->posting->placement_cycle_id,
            'offer_type' => 'fulltime', 'ctc_annual' => 1000000, 'currency' => 'INR', 'announced_at' => now(),
        ]);

        $apps = collect($this->getJson("/api/admin/postings/{$this->posting->id}/pipeline")->assertOk()->json('applications'));
        $this->assertTrue($apps->firstWhere('student.roll_no', '22JE0003')['offer_here']);
        $this->assertFalse($apps->firstWhere('student.roll_no', '22JE0001')['offer_here']);
        $this->assertSame([], $apps->firstWhere('student.roll_no', '22JE0003')['offers']);
    }

    public function test_students_and_companies_cannot_reach_the_shortlist_routes(): void
    {
        foreach ([$this->students[0]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson($this->url('/shortlist'))->assertForbidden();
            $this->getJson($this->url('/shortlist/export'))->assertForbidden();
        }

        // A stage of another posting is 404.
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/admin/postings/999999/rounds/{$this->round(0)->id}/shortlist")->assertNotFound();
    }
}
