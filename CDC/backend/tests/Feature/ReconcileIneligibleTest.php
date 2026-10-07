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
 * Fix H1 (B2-11): Reconcile Ineligible Students on a stage.
 */
class ReconcileIneligibleTest extends TestCase
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

    private function base(): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/reconcile";
    }

    /** 22JE0002: CGPA below the 6.0 cut-off. 22JE0003: blocked by a full-time offer in this cycle. */
    private function makeIneligible(): void
    {
        $this->students[1]->update(['current_cgpa' => 5.5]);
        \App\Models\PlacementBlock::create([
            'student_profile_id' => $this->students[2]->id, 'placement_cycle_id' => $this->posting->placement_cycle_id,
            'scope' => 'all', 'reason' => 'manual', 'remark' => 'Placed through another drive', 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
    }

    public function test_lists_only_ineligible_pool_members_with_the_same_reasons_as_check(): void
    {
        $this->closeApplications();
        $this->makeIneligible();

        $response = $this->getJson($this->base())->assertOk()->assertJsonPath('pool_count', 6);
        $rolls = collect($response->json('students'))->pluck('student.roll_no')->all();
        $this->assertSame(['22JE0002', '22JE0003'], $rolls);

        $service = app(\App\Services\EligibilityService::class);
        foreach ($response->json('students') as $row) {
            $student = StudentProfile::where('roll_no', $row['student']['roll_no'])->first();
            $this->assertSame($service->check($student->fresh(), $this->posting->fresh())['reasons'], $row['reasons']);
        }
        $this->assertStringContainsString('CGPA below cutoff', $response->json('students.0.reasons.0'));
    }

    public function test_confirming_rejects_publishes_and_mails_once_in_bcc(): void
    {
        $this->closeApplications();
        $this->makeIneligible();
        Mail::fake();
        $ids = collect($this->getJson($this->base())->json('students'))->pluck('application_id')->all();

        $this->postJson($this->base(), ['application_ids' => $ids])->assertStatus(422); // confirm required
        $this->postJson($this->base(), ['application_ids' => $ids, 'confirm' => true])->assertOk()->assertJsonPath('rejected', 2);

        $rows = ApplicationRoundResult::where('posting_round_id', $this->round(0)->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('rejected', $row->result);
            $this->assertNotNull($row->published_at);
            $this->assertStringStartsWith('No longer eligible: ', $row->remark);
        }
        Mail::assertQueued(RoundResultMail::class, 1);
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && $m->hasBcc($this->students[1]->user->email) && $m->hasBcc($this->students[2]->user->email) && ! $m->hasTo($this->students[1]->user->email));
        $this->assertSame(2, EmailLog::where('job_posting_id', $this->posting->id)->where('kind', 'reconcile_regret')->count());

        $audit = AuditLog::where('action', 'stage.reconcile')->sole();
        $this->assertSame($ids, $audit->before['application_ids']);
        $this->assertCount(2, $audit->after['rejected']);

        // Running it again writes and mails nothing new.
        $this->getJson($this->base())->assertJsonCount(0, 'students');
        $this->postJson($this->base(), ['application_ids' => $ids, 'confirm' => true])->assertStatus(422);
        Mail::assertQueued(RoundResultMail::class, 1);
        $this->assertSame(2, ApplicationRoundResult::where('posting_round_id', $this->round(0)->id)->count());
    }

    public function test_eligible_students_offer_holders_and_outsiders_are_never_rejected(): void
    {
        $this->closeApplications();
        $this->makeIneligible();
        $eligible = Application::where('student_profile_id', $this->students[0]->id)->value('id');
        $holder = Application::where('student_profile_id', $this->students[2]->id)->first();
        \App\Models\Offer::create([
            'application_id' => $holder->id, 'student_profile_id' => $this->students[2]->id, 'company_id' => $this->posting->company()->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->posting->placement_cycle_id,
            'offer_type' => 'fulltime', 'ctc_annual' => 1, 'currency' => 'INR', 'announced_at' => now(),
        ]);

        $listed = collect($this->getJson($this->base())->json('students'))->pluck('student.roll_no')->all();
        $this->assertSame(['22JE0002'], $listed); // the offer holder is not listed

        $this->postJson($this->base(), ['application_ids' => [$eligible, $holder->id], 'confirm' => true])->assertStatus(422);
        $this->assertSame(0, ApplicationRoundResult::count());

        // Someone outside this stage's pool (stage 2 before stage 1 is published) cannot be rejected there.
        $r2 = $this->round(1);
        $other = Application::where('student_profile_id', $this->students[1]->id)->value('id');
        $this->getJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/reconcile")->assertJsonCount(0, 'students');
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/reconcile", ['application_ids' => [$other], 'confirm' => true])->assertStatus(422);
    }

    public function test_report_is_formula_safe_audited_and_admin_only(): void
    {
        $this->closeApplications();
        $this->makeIneligible();
        $this->students[1]->update(['full_name' => '=HYPERLINK("x")']);

        $response = $this->get($this->base().'/export')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);
        $this->assertSame('Roll Number', $sheet->getCell('B3')->getValue());
        $this->assertSame('=HYPERLINK("x")', $sheet->getCell('C4')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('C4')->getDataType());
        $this->assertTrue(collect($sheet->toArray())->flatten()->contains(fn ($v) => str_starts_with((string) $v, 'Downloaded on ')));
        $this->assertTrue(AuditLog::where('action', 'stage.reconcile_report')->exists());

        foreach ([$this->students[0]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson($this->base())->assertForbidden();
            $this->getJson($this->base().'/export')->assertForbidden();
            $this->postJson($this->base(), ['application_ids' => [1], 'confirm' => true])->assertForbidden();
        }
    }

    public function test_refused_while_applications_are_open(): void
    {
        $this->makeIneligible();
        $ids = collect($this->getJson($this->base())->json('students'))->pluck('application_id')->all();
        $this->postJson($this->base(), ['application_ids' => $ids, 'confirm' => true])->assertStatus(422);
    }
}
