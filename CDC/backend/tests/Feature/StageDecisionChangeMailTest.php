<?php

namespace Tests\Feature;

use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
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
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner decision 2026-10-18: stage rows are never locked, and re-publishing a stage after a change mails ONLY the
 * students whose decision changed. A change undone before publishing restores the published decision silently.
 */
class StageDecisionChangeMailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private JobPosting $posting;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        for ($i = 0; $i < 4; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1)]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
            $this->students[] = $s;
        }

        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private function base(): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->posting->rounds()->first()->id}";
    }

    /** @return list<string> every student address the round-result mails went to (students are Bcc'd; To is the portal's own address). */
    private function mailedStudents(): array
    {
        return Mail::queued(RoundResultMail::class)
            ->flatMap(fn (RoundResultMail $m) => collect([...$m->to, ...$m->cc, ...$m->bcc])->pluck('address'))
            ->reject(fn (string $address) => $address === config('mail.from.address'))
            ->unique()->sort()->values()->all();
    }

    private function emailOf(int $i): string
    {
        return $this->students[$i]->user->email;
    }

    public function test_republishing_mails_only_the_students_whose_decision_changed(): void
    {
        // First publish: 1 and 2 shortlisted, 3 on hold, 4 not selected — all four are told.
        $this->postJson($this->base().'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'selected'],
            ['roll_no' => '22JE0003', 'result' => 'waitlisted'], ['roll_no' => '22JE0004', 'result' => 'rejected'],
        ]])->assertOk();
        $this->postJson($this->base().'/publish', ['reject_remaining' => false])->assertOk();
        $this->assertCount(4, $this->mailedStudents());

        // Later: the on-hold student is shortlisted and student 2 is taken off the shortlist.
        Mail::fake();
        $this->postJson($this->base().'/results', ['entries' => [
            ['roll_no' => '22JE0003', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'rejected'],
        ]])->assertOk()->assertJsonPath('changed', 2);
        $this->postJson($this->base().'/publish', ['reject_remaining' => false])->assertOk();

        $this->assertEqualsCanonicalizing([$this->emailOf(1), $this->emailOf(2)], $this->mailedStudents());
        $this->assertSame(2, AuditLog::where('action', 'round.result_change')->count());
    }

    public function test_a_change_undone_before_publishing_is_restored_without_any_mail(): void
    {
        $this->postJson($this->base().'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'selected'],
        ]])->assertOk();
        $this->postJson($this->base().'/publish', ['reject_remaining' => false])->assertOk();
        $row = ApplicationRoundResult::whereHas('application.studentProfile', fn ($q) => $q->where('roll_no', '22JE0001'))->sole();
        $publishedAt = $row->published_at;

        // Shortlisted → Rejected (a draft) → Shortlisted again, before publishing.
        Mail::fake();
        $this->postJson($this->base().'/results', ['entries' => [['roll_no' => '22JE0001', 'result' => 'rejected']]])->assertOk();
        $this->assertNull($row->fresh()->published_at);
        $this->postJson($this->base().'/results', ['entries' => [['roll_no' => '22JE0001', 'result' => 'selected']]])->assertOk();

        $row->refresh();
        $this->assertSame(['selected', null], [$row->result, $row->published_result]);
        $this->assertEquals($publishedAt, $row->published_at, 'restored as published, with its original time');
        $this->assertSame(1, AuditLog::where('action', 'round.result_change_undone')->count());

        // Nothing is left to publish, and nobody was mailed.
        $this->postJson($this->base().'/publish', ['reject_remaining' => false])->assertStatus(422);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }
}
