<?php

namespace Tests\Feature;

use App\Mail\ApplicationSubmittedMail;
use App\Mail\EventAnnouncedMail;
use App\Mail\PostingFloatedMail;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostingFlowTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private User $admin;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $this->ft = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime'] + $base);
        $this->intern = PlacementCycle::create(['name' => 'Intern', 'type' => 'internship'] + $base);
    }

    private function formData(): array
    {
        return [
            'jobTitle' => 'Software Engineer',
            'internshipTitle' => 'Summer Intern',
            'companyProfile' => ['name' => 'Acme', 'postalAddress' => 'Secret street 1', 'website' => 'https://acme.test'],
            'signatory' => ['name' => 'Priya', 'designation' => 'HR'],
            'eligibility' => [[
                'programme' => self::BTECH,
                'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false]],
            ]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
            'selectionRounds' => [
                ['id' => '1', 'type' => 'aptitude_test', 'enabled' => true, 'date' => '2027-01-10'],
                ['id' => '2', 'type' => 'other', 'description' => 'Case study', 'enabled' => true],
                ['id' => '3', 'type' => 'hr_interview', 'enabled' => true],
                ['id' => '4', 'type' => 'group_discussion', 'enabled' => false],
            ],
        ];
    }

    private function form(string $type = 'jnf', string $status = 'accepted'): Jnf|Inf
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test', 'primary_contact' => ['email' => 'secret@acme.test']]);

        return $type === 'inf'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => 'Summer Intern', 'internship_description' => 'x', 'status' => $status, 'form_data' => $this->formData()])
            : Jnf::create(['company_id' => $company->id, 'job_title' => 'Software Engineer', 'job_description' => 'x', 'status' => $status, 'form_data' => $this->formData()]);
    }

    private function student(array $attributes = [], ?PlacementCycle $cycle = null): StudentProfile
    {
        $student = StudentProfile::factory()->create($attributes);
        CycleEnrollment::create(['placement_cycle_id' => ($cycle ?? $this->ft)->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student;
    }

    private function resume(StudentProfile $student, string $status = 'approved'): Resume
    {
        return $student->resumes()->create(['slot' => 1, 'label' => 'Main', 'file_path' => 'resumes/x.pdf', 'file_size' => 10, 'status' => $status]);
    }

    private function float(Jnf|Inf $form, array $overrides = [])
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/postings', array_merge([
            'form_type' => $form instanceof Inf ? 'inf' : 'jnf',
            'form_id' => $form->id,
            'placement_cycle_id' => $form instanceof Inf ? $this->intern->id : $this->ft->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
            'questions' => [
                ['question' => 'Why Acme?', 'qtype' => 'text', 'required' => true],
                ['question' => 'Preferred location', 'qtype' => 'mcq_single', 'options' => ['Bengaluru', 'Pune'], 'required' => true],
                ['question' => 'Languages', 'qtype' => 'mcq_multi', 'options' => ['Python', 'Go', 'Java']],
            ],
        ], $overrides));
    }

    public function test_float_copies_rounds_questions_snapshot_and_mails_exactly_the_eligible_students(): void
    {
        $eligible = [$this->student(), $this->student(), $this->student()];
        $this->student(['current_cgpa' => 6.0]);
        $this->student(['branch' => 'Mining Engineering']);
        $this->student([], $this->intern);
        config(['mail.bulk_batch_size' => 2]);

        $response = $this->float($this->form());
        $response->assertCreated()->assertJsonPath('posting.stats.eligible', 3);

        $posting = JobPosting::sole();
        $this->assertSame(['Aptitude Test', 'Case study', 'HR Interview'], $posting->rounds->pluck('name')->all());
        $this->assertTrue($posting->rounds->last()->is_final);
        $this->assertSame(1, $posting->rounds->where('is_final', true)->count());
        $this->assertSame('2027-01-10', $posting->rounds->first()->scheduled_at->toDateString());
        $this->assertCount(3, $posting->questions);
        $this->assertArrayHasKey('eligibility', $posting->eligibility_snapshot);
        $this->assertArrayNotHasKey('signatory', $posting->eligibility_snapshot);

        $expected = app(EligibilityService::class)->eligibleStudentsQuery($posting)->count();
        $this->assertSame(3, $expected);
        $this->assertSame($expected, EmailLog::where('template', 'emails.posting-floated')->count());
        // Batch size 2 → 3 eligible students go out as 2 BCC messages addressed to the portal itself (D89).
        Mail::assertQueued(PostingFloatedMail::class, 2);
        Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasTo(config('mail.from.address')) && count($m->to) === 1);
        foreach ($eligible as $student) {
            Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($student->user->email) && ! $m->hasTo($student->user->email));
        }
        $this->assertSame(1, AuditLog::where('action', 'posting.float')->count());
    }

    public function test_float_guards(): void
    {
        $this->float($this->form('jnf', 'submitted'))->assertStatus(422);
        $this->float($this->form('jnf'), ['placement_cycle_id' => $this->intern->id])->assertStatus(422);
        $this->float($this->form('inf'), ['placement_cycle_id' => $this->ft->id])->assertStatus(422);
        $this->float($this->form(), ['application_deadline' => now()->subHour()->toIso8601String()])->assertStatus(422);
        $this->float($this->form(), ['questions' => [['question' => 'Pick', 'qtype' => 'mcq_single', 'options' => ['Only one']]]])->assertStatus(422);

        $form = $this->form();
        $this->float($form)->assertCreated();
        $this->float($form)->assertStatus(422);

        $this->intern->update(['status' => 'closed']);
        $this->float($this->form('inf'))->assertStatus(422);
        $this->assertSame(1, JobPosting::count());
    }

    public function test_inf_floats_into_internship_cycle(): void
    {
        $this->student([], $this->intern);
        $this->float($this->form('inf'))->assertCreated()->assertJsonPath('posting.type', 'internship');
    }

    public function test_floated_form_cannot_be_deleted_by_company(): void
    {
        $form = $this->form();
        $this->float($form)->assertCreated();

        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $form->company_id]);
        Sanctum::actingAs($companyUser);
        $this->deleteJson("/api/company/jnfs/{$form->id}")->assertStatus(422);
    }

    public function test_student_board_shows_postings_with_eligibility_and_hides_contacts(): void
    {
        $this->float($this->form())->assertCreated();
        $posting = JobPosting::sole();

        $ok = $this->student();
        $low = $this->student(['current_cgpa' => 6.5]);
        $outsider = StudentProfile::factory()->create();

        Sanctum::actingAs($low->user);
        $this->getJson('/api/student/postings')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('postings.0.eligibility.eligible', false)
            ->assertJsonPath('postings.0.eligibility.reasons.0', 'CGPA below cutoff (6.5 < 7.0)')
            ->assertJsonPath('postings.0.compensation.ctc_annual', 1800000);
        $this->getJson('/api/student/postings?eligibility=eligible')->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($ok->user);
        $detail = $this->getJson("/api/student/postings/{$posting->id}")->assertOk();
        $detail->assertJsonPath('posting.eligibility.eligible', true);
        $body = $detail->getContent();
        $this->assertStringNotContainsString('Secret street', $body);
        $this->assertStringNotContainsString('secret@acme.test', $body);
        $this->assertStringNotContainsString('Priya', $body);
        $this->assertStringNotContainsString('applied_count', $body);

        Sanctum::actingAs($outsider->user);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 0);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
    }

    public function test_apply_edit_withdraw_reapply_and_deadline(): void
    {
        $this->float($this->form())->assertCreated();
        $posting = JobPosting::sole();
        $questions = $posting->questions;
        $student = $this->student();
        $resume = $this->resume($student, 'pending');
        Sanctum::actingAs($student->user);

        $answers = [
            ['question_id' => $questions[0]->id, 'answer' => 'Great team'],
            ['question_id' => $questions[1]->id, 'answer' => 'Pune'],
            ['question_id' => $questions[2]->id, 'answer' => ['Go', 'Python']],
        ];

        // Required question missing / invalid option.
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => [$answers[1]]])->assertStatus(422);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => [$answers[0], ['question_id' => $questions[1]->id, 'answer' => 'Delhi']]])->assertStatus(422);

        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => $answers])
            ->assertCreated()
            ->assertJsonPath('application.used_unverified_resume', true);
        Mail::assertQueued(ApplicationSubmittedMail::class);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => $answers])->assertStatus(409);

        $application = Application::sole();
        $this->assertTrue($resume->fresh()->isLocked());

        // Approving the resume clears the flag.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertOk();
        $this->assertFalse($application->fresh()->used_unverified_resume);

        Sanctum::actingAs($student->user);
        $this->patchJson("/api/student/applications/{$application->id}", ['answers' => [$answers[0], ['question_id' => $questions[1]->id, 'answer' => 'Bengaluru']]])->assertOk();
        $this->postJson("/api/student/applications/{$application->id}/withdraw")->assertOk();
        $this->assertSame('withdrawn', $application->fresh()->status);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => $answers])->assertCreated();
        $this->assertSame(1, Application::count());
        $this->assertSame('applied', $application->fresh()->status);

        $this->getJson('/api/student/applications')->assertOk()->assertJsonCount(1, 'applications')->assertJsonCount(3, 'applications.0.trail');

        $this->travel(6)->days();
        $this->postJson("/api/student/applications/{$application->id}/withdraw")->assertStatus(422)->assertJsonPath('message', 'The application deadline has passed.');
        $this->patchJson("/api/student/applications/{$application->id}", ['resume_id' => $resume->id])->assertStatus(422);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id, 'answers' => $answers])->assertStatus(422);
    }

    public function test_ineligible_apply_is_rejected_with_reasons_and_foreign_resume_refused(): void
    {
        $this->float($this->form(), ['questions' => []])->assertCreated();
        $posting = JobPosting::sole();

        $low = $this->student(['current_cgpa' => 6.0]);
        Sanctum::actingAs($low->user);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $this->resume($low)->id])
            ->assertStatus(422)
            ->assertJsonPath('reasons.0', 'CGPA below cutoff (6.0 < 7.0)');

        $ok = $this->student();
        $other = $this->student();
        Sanctum::actingAs($ok->user);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $this->resume($other)->id])->assertStatus(422);
        $this->assertSame(0, Application::count());
    }

    public function test_admin_round_management_and_deadline_edit(): void
    {
        $this->float($this->form())->assertCreated();
        $posting = JobPosting::sole();
        [$r1, $r2, $r3] = $posting->rounds->all();

        $this->postJson("/api/admin/postings/{$posting->id}/rounds", ['name' => 'Medical', 'round_type' => 'medical', 'is_final' => true])->assertCreated();
        $this->assertSame(1, $posting->rounds()->where('is_final', true)->count());
        $this->assertTrue($posting->rounds()->where('name', 'Medical')->first()->is_final);

        $medical = $posting->rounds()->where('name', 'Medical')->first();
        $this->deleteJson("/api/admin/postings/{$posting->id}/rounds/{$medical->id}")->assertOk();
        $this->assertTrue($r3->fresh()->is_final);

        $this->postJson("/api/admin/postings/{$posting->id}/rounds/reorder", ['ordered_round_ids' => [$r3->id, $r1->id, $r2->id]])->assertOk();
        $this->assertSame([$r3->id, $r1->id, $r2->id], $posting->rounds()->pluck('id')->all());
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/reorder", ['ordered_round_ids' => [$r1->id]])->assertStatus(422);

        $this->patchJson("/api/admin/postings/{$posting->id}", ['application_deadline' => now()->addDays(10)->toIso8601String(), 'share_contact_details' => true])->assertOk()
            ->assertJsonPath('posting.share_contact_details', true);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk()->assertJsonPath('posting.status', 'in_process');
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertStatus(422);

        $this->assertGreaterThanOrEqual(4, AuditLog::whereIn('action', ['round.create', 'round.delete', 'round.reorder', 'posting.update', 'posting.close'])->count());
    }

    public function test_settings_mail_mode_is_admin_controlled_and_audited(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonPath('settings.mail_mode', 'queued');
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'bogus'])->assertStatus(422);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk()->assertJsonPath('settings.mail_mode', 'sync');
        $this->assertSame(1, AuditLog::where('action', 'setting.update')->count());
    }

    public function test_immediate_mode_sends_one_bcc_message_and_logs_each_student(): void
    {
        $eligible = [$this->student(), $this->student(), $this->student()];
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk();

        $this->float($this->form())->assertCreated();

        Mail::assertNothingQueued();
        Mail::assertSent(PostingFloatedMail::class, 1);
        Mail::assertSent(PostingFloatedMail::class, fn ($m) => count($m->bcc) === 3 && $m->hasTo(config('mail.from.address')));
        foreach ($eligible as $student) {
            $this->assertDatabaseHas('email_logs', ['user_id' => $student->user->id, 'template' => 'emails.posting-floated', 'status' => 'sent']);
        }

        // The shared message greets everyone, not one student.
        $html = Mail::sent(PostingFloatedMail::class)->first()->render();
        $this->assertStringContainsString('Dear Students,', $html);
        $this->assertStringNotContainsString($eligible[0]->full_name, $html);
        $this->assertStringContainsString('Dear Students,', (new EventAnnouncedMail('PPT', 'Pre-Placement Talk', 'Mon', null, null, null, null))->render());
        $this->assertStringContainsString('Dear Candidate,', (new RoundResultMail(null, 'Acme', 'SDE', 'Test', 'rejected', null))->render());
    }

    public function test_closed_cycle_stops_applications_and_floated_form_stays_accepted(): void
    {
        $form = $this->form();
        $this->float($form, ['questions' => []])->assertCreated();
        $posting = JobPosting::sole();

        // A live posting's form cannot be pulled back for review.
        $this->patchJson("/api/admin/jnfs/{$form->id}/status", ['status' => 'under_review', 'admin_remarks' => 'Fix CTC'])->assertStatus(422);
        $this->assertSame('accepted', $form->fresh()->status);

        $student = $this->student();
        $resume = $this->resume($student);
        $this->ft->update(['status' => 'closed']);

        Sanctum::actingAs($student->user);
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This placement cycle is closed, so it no longer accepts applications.');
        $this->getJson("/api/student/postings/{$posting->id}")->assertJsonPath('posting.accepts_applications', false);

        // Once the posting is cancelled, the form can go back to review.
        $this->ft->update(['status' => 'open']);
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        $this->patchJson("/api/admin/jnfs/{$form->id}/status", ['status' => 'under_review', 'admin_remarks' => 'Fix CTC'])->assertOk();
    }

    public function test_student_detail_shows_the_frozen_eligibility(): void
    {
        $form = $this->form();
        $this->float($form, ['questions' => []])->assertCreated();
        $posting = JobPosting::sole();

        $data = $form->form_data;
        $data['eligibility'][0]['branches'][0]['cgpa'] = '9.5';
        $form->update(['form_data' => $data]);

        $student = $this->student();
        Sanctum::actingAs($student->user);
        $this->getJson("/api/student/postings/{$posting->id}")
            ->assertJsonPath('posting.form_data.eligibility.0.branches.0.cgpa', '7.0')
            ->assertJsonPath('posting.eligibility.eligible', true);
    }
}
