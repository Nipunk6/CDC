<?php

namespace Tests\Feature\QA;

use App\Jobs\SendPostingFloatedMails;
use App\Mail\ApplicationSubmittedMail;
use App\Mail\EventAnnouncedMail;
use App\Mail\OfferMail;
use App\Mail\PortalNoticeMail;
use App\Mail\PostingFloatedMail;
use App\Mail\ResumeReviewedMail;
use App\Mail\RoundResultMail;
use App\Mail\StudentInvitationMail;
use App\Mail\StudentProfileUpdatedMail;
use App\Models\Application;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA acceptance — Phase 2 Part B6 (emails E1–E10 + in-app notifications). Expected recipients are always built BEFORE
 * the trigger, then compared to email_logs (one row per recipient) and to the Mail fake's to/bcc lists.
 * Owner override (D89): E2/E4/E6 are one BCC message per batch with the portal address in To; email_logs stays per student.
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S6NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const CSE = 'Computer Science & Engineering';

    private const ECE = 'Electronics & Communication Engineering';

    private const MECH = 'Mechanical Engineering';

    private const MAILABLES = [
        StudentInvitationMail::class, PostingFloatedMail::class, ApplicationSubmittedMail::class, RoundResultMail::class,
        OfferMail::class, EventAnnouncedMail::class, ResumeReviewedMail::class, StudentProfileUpdatedMail::class, PortalNoticeMail::class,
    ];

    private User $admin;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    private Company $companyA;

    private Company $companyB;

    private User $companyAUser;

    private User $companyBUser;

    /** @var array<string, StudentProfile> */
    private array $actors = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin1@cdc-qa.test']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $this->ft = PlacementCycle::create(['name' => 'FT 2026-27', 'type' => 'fulltime'] + $base);
        $this->intern = PlacementCycle::create(['name' => 'Intern 2026-27', 'type' => 'internship'] + $base);

        $this->companyA = Company::create(['name' => 'Company A', 'hr_name' => 'HR A', 'hr_email' => 'hr@company-a.test']);
        $this->companyB = Company::create(['name' => 'Company B', 'hr_name' => 'HR B', 'hr_email' => 'hr@company-b.test']);
        $this->companyAUser = User::factory()->create(['role' => 'company', 'company_id' => $this->companyA->id, 'email' => 'recruiter@company-a.test']);
        $this->companyBUser = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id, 'email' => 'recruiter@company-b.test']);
    }

    // ============================================================================================
    // Fixture builders (same oracle as S3EligibilityApplyTest)
    // ============================================================================================

    private function formData(array $overrides = []): array
    {
        $row = fn (string $b) => ['branch' => $b, 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '0', 'maxTotalBacklogs' => '1'];

        return array_replace([
            'jobTitle' => 'Graduate Engineer',
            'internshipTitle' => 'Summer Intern',
            'companyProfile' => ['name' => 'Company A'],
            'eligibility' => [['programme' => self::BTECH, 'branches' => [
                $row(self::CSE), $row(self::ECE), ['branch' => self::MECH, 'selected' => false, 'cgpa' => '', 'backlogsAllowed' => false],
            ]]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'minTenthPercent' => '60',
            'minTwelfthPercent' => '60',
            'currency' => 'INR',
            'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
            'programmeStipends' => [['programme' => self::BTECH, 'total' => '80000', 'enabled' => true]],
            'selectionRounds' => [
                ['id' => '1', 'type' => 'aptitude_test', 'enabled' => true],
                ['id' => '2', 'type' => 'group_discussion', 'enabled' => true],
                ['id' => '3', 'type' => 'technical_interview', 'enabled' => true],
                ['id' => '4', 'type' => 'hr_interview', 'enabled' => false],
            ],
        ], $overrides);
    }

    private function jnf(?Company $company = null, array $overrides = [], string $title = 'Graduate Engineer'): Jnf
    {
        $company ??= $this->companyA;

        return Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 5, 'form_data' => $this->formData($overrides + ['jobTitle' => $title])]);
    }

    private function inf(?Company $company = null, array $overrides = [], string $title = 'Summer Intern'): Inf
    {
        $company ??= $this->companyB;

        return Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => 'accepted', 'vacancies' => 5, 'form_data' => $this->formData($overrides + ['internshipTitle' => $title])]);
    }

    private function float(Jnf|Inf $form, array $overrides = []): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/postings', array_merge([
            'form_type' => $form instanceof Inf ? 'inf' : 'jnf',
            'form_id' => $form->id,
            'placement_cycle_id' => ($form instanceof Inf ? $this->intern : $this->ft)->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
            'questions' => [],
        ], $overrides));
    }

    private function floatOk(Jnf|Inf $form, array $overrides = []): JobPosting
    {
        $response = $this->float($form, $overrides)->assertCreated();

        return JobPosting::query()->findOrFail($response->json('posting.id'));
    }

    private function student(array $attributes = [], ?array $cycles = null): StudentProfile
    {
        $student = StudentProfile::factory()->create(array_merge([
            'programme' => self::BTECH, 'branch' => self::CSE, 'graduating_batch' => 2027,
            'tenth_percent' => 90, 'twelfth_percent' => 88, 'current_cgpa' => 8.5,
            'ongoing_backlogs' => 0, 'total_backlogs' => 0, 'gender' => 'male',
        ], $attributes));
        $student->update(['personal_email' => strtolower($student->roll_no).'@personal-mail.test']);

        foreach ($cycles ?? [$this->ft] as $cycle) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }
        $student->resumes()->create(['slot' => 1, 'label' => 'Main CV', 'file_path' => "resumes/{$student->roll_no}/1.pdf", 'file_size' => 100, 'status' => 'approved']);

        return $student;
    }

    private function userOf(StudentProfile $s): User
    {
        return User::query()->findOrFail($s->user_id);
    }

    private function as(StudentProfile|User $who): void
    {
        Sanctum::actingAs($who instanceof StudentProfile ? $this->userOf($who) : $who->fresh());
    }

    private function actors(): void
    {
        $both = [$this->ft, $this->intern];
        $this->actors = [
            'ELIGIBLE' => $this->student([], $both),
            'LOWCGPA' => $this->student(['current_cgpa' => 6.2], $both),
            'BACKLOG' => $this->student(['ongoing_backlogs' => 1, 'total_backlogs' => 2], $both),
            'TOTALBACKLOG_ONLY' => $this->student(['ongoing_backlogs' => 0, 'total_backlogs' => 1]),
            'FEMALE' => $this->student(['gender' => 'female'], $both),
            'OTHERBRANCH' => $this->student(['branch' => self::MECH]),
            'WRONGBATCH' => $this->student(['graduating_batch' => 2028]),
            'LOW10TH' => $this->student(['tenth_percent' => 55]),
            'LOW12TH' => $this->student(['twelfth_percent' => 55]),
            'NOTENROLLED' => $this->student([], []),
            'SUSPENDED' => $this->student(['gender' => 'female'], $both),
            'DEBARRED' => $this->student(['gender' => 'female'], $both),
        ];

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$this->actors['SUSPENDED']->id}/suspend")->assertOk();
        foreach ([$this->ft, $this->intern] as $cycle) {
            $this->postJson('/api/admin/blocks', [
                'student_profile_id' => $this->actors['DEBARRED']->id, 'placement_cycle_id' => $cycle->id,
                'scope' => 'all', 'reason' => 'debarred', 'remark' => 'Skipped a scheduled interview',
            ])->assertCreated();
        }
    }

    private function apply(StudentProfile $s, JobPosting $p): TestResponse
    {
        $this->as($s);

        return $this->postJson("/api/student/postings/{$p->id}/apply", ['resume_id' => $s->resumes()->orderBy('slot')->first()->id, 'answers' => []]);
    }

    /** @return Collection<int, Mailable> every mailable queued or sent, of the known Phase 2 mail classes */
    private function allMails(?string $class = null): Collection
    {
        $mails = collect();
        foreach ($class ? [$class] : self::MAILABLES as $cls) {
            $mails = $mails->merge(Mail::queued($cls))->merge(Mail::sent($cls));
        }

        return $mails->values();
    }

    /** @return list<string> */
    private function addresses(Mailable $m, string $field = 'all'): array
    {
        $fields = $field === 'all' ? ['to', 'cc', 'bcc'] : [$field];
        $out = [];
        foreach ($fields as $f) {
            foreach ($m->{$f} as $r) {
                $out[] = strtolower($r['address']);
            }
        }

        return $out;
    }

    /** @return list<string> sorted emails */
    private function emails(iterable $students): array
    {
        return collect($students)->map(fn (StudentProfile $s) => strtolower($this->userOf($s)->email))->sort()->values()->all();
    }

    /** @return list<int> sorted user ids */
    private function userIds(iterable $students): array
    {
        return collect($students)->map(fn (StudentProfile $s) => $s->user_id)->sort()->values()->all();
    }

    private function loggedUserIds(string $template, ?string $subject = null): array
    {
        return EmailLog::query()->where('template', $template)->when($subject, fn ($q) => $q->where('subject', $subject))
            ->pluck('user_id')->sort()->values()->all();
    }

    /** Broadcast check (D89): BCC-only, portal address in To, the BCC union equals the expected set exactly. */
    private function assertBroadcast(Collection $messages, array $expectedEmails, string $label): void
    {
        $this->assertNotEmpty($messages, "{$label}: no message");
        $bcc = [];
        foreach ($messages as $m) {
            $this->assertSame([strtolower((string) config('mail.from.address'))], $this->addresses($m, 'to'), "{$label}: To must be the portal address only");
            $this->assertSame([], $this->addresses($m, 'cc'), "{$label}: no CC");
            $this->assertLessThanOrEqual((int) config('mail.bulk_batch_size', 100), count($m->bcc), "{$label}: batch larger than MAIL_BULK_BATCH_SIZE");
            $bcc = array_merge($bcc, $this->addresses($m, 'bcc'));
        }
        sort($bcc);
        $this->assertSame($expectedEmails, $bcc, "{$label}: BCC recipients differ from the expected set (or someone got it twice)");
    }

    /** Close applications, then publish every non-final round selecting $rolls. */
    private function reachFinal(JobPosting $posting, array $rolls): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        foreach ($posting->rounds()->where('is_final', false)->get() as $round) {
            $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}/results", ['roll_nos' => $rolls, 'result' => 'selected'])->assertOk();
            $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}/publish", ['reject_remaining' => true])->assertOk();
        }
    }

    private function appOf(JobPosting $p, StudentProfile $s): Application
    {
        return Application::query()->where('job_posting_id', $p->id)->where('student_profile_id', $s->id)->sole();
    }

    private function createStudentViaApi(string $roll, array $extra = []): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/students', array_merge([
            'roll_no' => $roll, 'full_name' => 'Asha Verma', 'institute_email' => strtolower($roll).'@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => self::CSE, 'graduating_batch' => 2027, 'gender' => 'female',
            'current_cgpa' => 8.4, 'tenth_percent' => 90, 'twelfth_percent' => 90, 'personal_email' => strtolower($roll).'@personal-mail.test',
        ], $extra));
    }

    // ============================================================================================
    // T6.1 — E1 account created
    // ============================================================================================

    public function test_T6_1_E1_invitation_per_created_student_with_roll_and_link(): void
    {
        config(['app.frontend_url' => 'https://portal.cdc-qa.test']);

        $this->createStudentViaApi('23JE0101')->assertCreated();
        $this->createStudentViaApi('23JE0102', ['full_name' => 'Ravi Kumar', 'gender' => 'male'])->assertCreated();

        $header = 'roll_no,full_name,institute_email,programme,branch,graduating_batch,gender,current_cgpa,ongoing_backlogs,total_backlogs,tenth_percent,twelfth_percent,date_of_birth,personal_email,phone,category,pwd,home_state';
        $rows = [];
        foreach (['23JE0201' => 'Meera Das', '23JE0202' => 'Karan Shah'] as $roll => $name) {
            $rows[] = sprintf('%s,%s,%s,"%s",%s,2027,male,8.0,0,0,90,90,,%s,,,no,Bihar', $roll, $name, strtolower($roll).'@iitism.ac.in', self::BTECH, self::CSE, strtolower($roll).'@personal-mail.test');
        }
        Sanctum::actingAs($this->admin);
        $this->post('/api/admin/students/import', ['file' => UploadedFile::fake()->createWithContent('students.csv', $header."\n".implode("\n", $rows))], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('created', 2);

        $created = StudentProfile::query()->whereIn('roll_no', ['23JE0101', '23JE0102', '23JE0201', '23JE0202'])->get();
        $this->assertCount(4, $created);

        $invites = $this->allMails(StudentInvitationMail::class);
        $this->assertCount(4, $invites, 'exactly one invitation per created student');
        foreach ($created as $student) {
            $mine = $invites->filter(fn ($m) => $m->hasTo($student->institute_email));
            $this->assertCount(1, $mine, "{$student->roll_no}: one invitation to the institute email");
            $html = $mine->first()->render();
            $this->assertStringContainsString($student->roll_no, $html, 'E1 must show the roll number');
            $this->assertStringContainsString('https://portal.cdc-qa.test/auth/student/set-password?token=', $html, 'E1 must carry the set-password link');
            $this->assertSame(1, EmailLog::query()->where('user_id', $student->user_id)->where('template', 'emails.student-invitation')->count());
        }
    }

    // ============================================================================================
    // T6.2 — E2 floated
    // ============================================================================================

    public function test_T6_2_E2_float_recipients_are_exactly_the_eligible_set(): void
    {
        $this->actors();
        $expected = collect(['ELIGIBLE', 'TOTALBACKLOG_ONLY', 'FEMALE'])->map(fn ($a) => $this->actors[$a]);
        $expectedEmails = $this->emails($expected);
        $expectedIds = $this->userIds($expected);
        config(['mail.bulk_batch_size' => 2]); // force >1 batch

        $posting = $this->floatOk($this->jnf());

        $queryIds = app(EligibilityService::class)->eligibleStudentsQuery($posting)->pluck('user_id')->sort()->values()->all();
        $this->assertSame($expectedIds, $queryIds, 'eligibleStudentsQuery differs from the oracle');
        $this->assertSame($expectedIds, $this->loggedUserIds('emails.posting-floated'), 'email_logs must hold exactly one row per eligible student');

        $messages = $this->allMails(PostingFloatedMail::class);
        $this->assertCount(2, $messages, '3 recipients at batch size 2 = 2 BCC messages');
        $this->assertBroadcast($messages, $expectedEmails, 'E2');

        $notified = PortalNotification::query()->where('title', 'New opening: Company A')->pluck('user_id')->sort()->values()->all();
        $this->assertSame($expectedIds, $notified, 'in-app E2 audience must equal the mail audience');

        foreach (['LOWCGPA', 'BACKLOG', 'OTHERBRANCH', 'WRONGBATCH', 'LOW10TH', 'LOW12TH', 'NOTENROLLED', 'SUSPENDED', 'DEBARRED'] as $actor) {
            $email = strtolower($this->userOf($this->actors[$actor])->email);
            $this->assertFalse($messages->contains(fn ($m) => in_array($email, $this->addresses($m), true)), "{$actor} must not receive E2");
        }
    }

    public function test_T6_2_E2_female_inf_audience_excludes_suspended_and_debarred(): void
    {
        $this->actors();
        $expected = [$this->actors['FEMALE']];

        $this->floatOk($this->inf(null, ['genderFilter' => 'female']));

        $this->assertSame($this->userIds($expected), $this->loggedUserIds('emails.posting-floated'));
        $this->assertBroadcast($this->allMails(PostingFloatedMail::class), $this->emails($expected), 'E2 INF');
    }

    public function test_T6_2_E2_is_dispatched_at_float_time(): void
    {
        $this->actors();
        Queue::fake();

        $posting = $this->floatOk($this->jnf());

        Queue::assertPushed(SendPostingFloatedMails::class, fn ($job) => $job->jobPostingId === $posting->id);
        $this->assertSame(0, EmailLog::query()->where('template', 'emails.posting-floated')->count());

        // Run the pushed job as the worker would.
        Queue::pushed(SendPostingFloatedMails::class)->each(fn ($job) => app()->call([$job, 'handle']));
        $this->assertSame(3, EmailLog::query()->where('template', 'emails.posting-floated')->count());
    }

    // ============================================================================================
    // T6.3 — E3 application confirmation
    // ============================================================================================

    public function test_T6_3_E3_confirmation_on_apply_and_reapply_recorded(): void
    {
        $posting = $this->floatOk($this->jnf());
        $student = $this->student();

        $this->apply($student, $posting)->assertCreated();
        $mails = $this->allMails(ApplicationSubmittedMail::class);
        $this->assertCount(1, $mails);
        $this->assertSame([strtolower($this->userOf($student)->email)], $this->addresses($mails->first()));
        $html = $mails->first()->render();
        $this->assertStringContainsString('Graduate Engineer', $html);
        $this->assertStringContainsString('Main CV', $html, 'E3 must name the resume');
        $this->assertSame(1, EmailLog::query()->where('user_id', $student->user_id)->where('template', 'emails.application-submitted')->count());

        // Withdraw + re-apply: behaviour recorded (implemented: a fresh confirmation is sent).
        $this->as($student);
        $this->postJson('/api/student/applications/'.$this->appOf($posting, $student)->id.'/withdraw')->assertOk();
        $this->apply($student, $posting)->assertCreated();
        $this->assertCount(2, $this->allMails(ApplicationSubmittedMail::class), 'RECORDED: re-apply sends E3 again');
    }

    // ============================================================================================
    // T6.4 — E4 round result + regret
    // ============================================================================================

    public function test_T6_4_E4_round_publish_result_and_regret_mails_and_notifications(): void
    {
        $posting = $this->floatOk($this->jnf());
        $students = [];
        for ($i = 0; $i < 10; $i++) {
            $students[] = $this->student();
        }
        foreach ($students as $s) {
            $this->apply($s, $posting)->assertCreated();
        }

        $selected = array_slice($students, 0, 5);
        $waitlisted = array_slice($students, 5, 2);
        $rejected = array_slice($students, 7);

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $round = $posting->rounds()->first();
        $entries = array_merge(
            array_map(fn ($s) => ['roll_no' => $s->roll_no, 'result' => 'selected'], $selected),
            array_map(fn ($s) => ['roll_no' => $s->roll_no, 'result' => 'waitlisted'], $waitlisted),
        );
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}/results", ['entries' => $entries])->assertOk();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}/publish", ['reject_remaining' => true])
            ->assertOk()->assertJsonPath('counts.selected', 5)->assertJsonPath('counts.waitlisted', 2)->assertJsonPath('counts.rejected', 3);

        $mails = $this->allMails(RoundResultMail::class);
        $this->assertBroadcast($mails->where('outcome', 'selected')->values(), $this->emails($selected), 'E4 selected');
        $this->assertBroadcast($mails->where('outcome', 'waitlisted')->values(), $this->emails($waitlisted), 'E4 waitlisted');
        $this->assertBroadcast($mails->where('outcome', 'rejected')->values(), $this->emails($rejected), 'E4 regret');
        $this->assertStringContainsString('not been shortlisted', $mails->where('outcome', 'rejected')->first()->render());

        $this->assertSame($this->userIds($students), $this->loggedUserIds('emails.round-result'), 'email_logs: exactly one result/regret row per applicant of the round');

        $title = 'Company A: Aptitude Test result';
        $this->assertSame($this->userIds($students), PortalNotification::query()->where('title', $title)->pluck('user_id')->sort()->values()->all(), 'one in-app row per applicant');
        foreach ($rejected as $s) {
            $this->assertStringContainsString('not shortlisted', PortalNotification::query()->where('user_id', $s->user_id)->where('title', $title)->value('message'));
        }
    }

    // ============================================================================================
    // T6.5 — E5 final result
    // ============================================================================================

    public function test_T6_5_E5_offer_mail_names_offer_type_and_company_and_final_regrets(): void
    {
        $posting = $this->floatOk($this->jnf(null, ['selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]]]));
        [$a, $b, $c, $d] = [$this->student(), $this->student(), $this->student(), $this->student()];
        foreach ([$a, $b, $c, $d] as $s) {
            $this->apply($s, $posting)->assertCreated();
        }
        $this->reachFinal($posting, [$a->roll_no, $b->roll_no, $c->roll_no]);

        $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => [
            ['application_id' => $this->appOf($posting, $a)->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1800000, 'block' => true, 'block_scope' => 'all'],
            ['application_id' => $this->appOf($posting, $b)->id, 'offer_type' => 'intern_fulltime', 'ctc_annual' => 1800000, 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();

        $offers = $this->allMails(OfferMail::class);
        $this->assertCount(2, $offers);
        foreach ([[$a, 'Full-Time'], [$b, 'Intern + Full-Time']] as [$s, $label]) {
            $mine = $offers->filter(fn ($m) => $m->hasTo($this->userOf($s)->email));
            $this->assertCount(1, $mine);
            $html = $mine->first()->render();
            $this->assertStringContainsString($label, $html, 'E5 must name the offer type');
            $this->assertStringContainsString('Company A', $html, 'E5 must name the company');
            $this->assertStringContainsString('Company A', $mine->first()->envelope()->subject);
        }
        $this->assertSame($this->userIds([$a, $b]), $this->loggedUserIds('emails.offer'));

        // Others in the final round get a regret.
        $finalRegrets = $this->allMails(RoundResultMail::class)->filter(fn ($m) => $m->roundName === 'Technical Interview' && $m->outcome === 'rejected')->values();
        $this->assertBroadcast($finalRegrets, $this->emails([$c]), 'E5 final-round regret');
        $this->assertSame(1, PortalNotification::query()->where('user_id', $a->user_id)->where('title', 'Offer from Company A')->count());
    }

    // ============================================================================================
    // T6.6 — E6 event audiences
    // ============================================================================================

    public function test_T6_6_E6_event_audiences_all_branches_and_live_applicants(): void
    {
        $posting = $this->floatOk($this->jnf());
        $cse1 = $this->student();
        $cse2 = $this->student();
        $ece = $this->student(['branch' => self::ECE]);
        $mech = $this->student(['branch' => self::MECH]);
        $suspended = $this->student(['branch' => self::ECE]);
        $live = $this->student();
        $withdrawn = $this->student();
        $notEnrolled = $this->student([], []);

        $this->apply($live, $posting)->assertCreated();
        $this->apply($withdrawn, $posting)->assertCreated();
        $this->as($withdrawn);
        $this->postJson('/api/student/applications/'.$this->appOf($posting, $withdrawn)->id.'/withdraw')->assertOk();
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$suspended->id}/suspend")->assertOk();

        $cases = [
            'All hands' => [['audience_type' => 'all'], [$cse1, $cse2, $ece, $mech, $live, $withdrawn, $notEnrolled]],
            'ECE talk' => [['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => self::BTECH, 'branch' => self::ECE]]]], [$ece]],
            'Core talk' => [['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => self::BTECH, 'branch' => self::ECE], ['programme' => self::BTECH, 'branch' => self::MECH]]]], [$ece, $mech]],
            'Applicant briefing' => [['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $posting->id]], [$live]],
        ];

        foreach ($cases as $title => [$audience, $expected]) {
            Sanctum::actingAs($this->admin);
            $id = $this->postJson('/api/admin/events', ['title' => $title, 'event_type' => 'ppt', 'starts_at' => now()->addDays(3)->toIso8601String()] + $audience)->assertCreated()->json('event.id');
            $this->postJson("/api/admin/events/{$id}/publish")->assertOk();

            $subject = "Pre-Placement Talk: {$title}";
            $this->assertSame($this->userIds($expected), $this->loggedUserIds('emails.event-announced', $subject), "{$title}: email_logs audience");
            $this->assertBroadcast($this->allMails(EventAnnouncedMail::class)->filter(fn ($m) => $m->title === $title)->values(), $this->emails($expected), "E6 {$title}");
            $this->assertSame($this->userIds($expected), PortalNotification::query()->where('title', $subject)->pluck('user_id')->sort()->values()->all(), "{$title}: in-app audience");
        }
    }

    // ============================================================================================
    // T6.7 — E7 resume decision
    // ============================================================================================

    public function test_T6_7_E7_resume_decision_includes_remark_on_reject(): void
    {
        $student = $this->student();
        $resume = $student->resumes()->create(['slot' => 2, 'label' => 'Core CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'pending']);
        $approve = $student->resumes()->create(['slot' => 3, 'label' => 'Data CV', 'file_path' => 'y.pdf', 'file_size' => 1, 'status' => 'pending']);

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => 'Add your CGPA on page one'])->assertOk();
        $this->patchJson("/api/admin/resumes/{$approve->id}", ['status' => 'approved'])->assertOk();

        $mails = $this->allMails(ResumeReviewedMail::class);
        $this->assertCount(2, $mails);
        $reject = $mails->first(fn ($m) => ! $m->approved);
        $this->assertTrue($reject->hasTo($this->userOf($student)->email));
        $this->assertStringContainsString('Add your CGPA on page one', $reject->render());
        $this->assertSame(2, EmailLog::query()->where('user_id', $student->user_id)->where('template', 'emails.resume-reviewed')->count());
    }

    // ============================================================================================
    // T6.8 — E8 profile change / branch decision
    // ============================================================================================

    public function test_T6_8_E8_admin_profile_change_and_branch_decisions(): void
    {
        $student = $this->student();
        $mover = $this->student();
        $refused = $this->student();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$student->id}", ['current_cgpa' => 9.12])->assertOk();

        foreach ([$mover, $refused] as $s) {
            $this->as($s);
            $this->postJson('/api/student/branch-change', ['requested_branch' => self::ECE, 'reason' => 'Branch sliding after first year was approved.'])->assertCreated();
        }
        Sanctum::actingAs($this->admin);
        $requests = \App\Models\BranchChangeRequest::query()->orderBy('id')->get();
        $this->patchJson("/api/admin/branch-changes/{$requests[0]->id}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/branch-changes/{$requests[1]->id}", ['status' => 'rejected', 'admin_remark' => 'Seat not available'])->assertOk();

        $mails = $this->allMails(StudentProfileUpdatedMail::class);
        $profile = $mails->filter(fn ($m) => $m->hasTo($this->userOf($student)->email));
        $this->assertCount(1, $profile, 'E8 profile change');
        $this->assertStringContainsString('9.12', $profile->first()->render());

        $this->assertCount(1, $mails->filter(fn ($m) => $m->hasTo($this->userOf($mover)->email) && str_contains($m->subjectLine, 'approved')));
        $no = $mails->filter(fn ($m) => $m->hasTo($this->userOf($refused)->email));
        $this->assertCount(1, $no);
        $this->assertStringContainsString('Seat not available', $no->first()->render());
        $this->assertSame(3, EmailLog::query()->where('template', 'emails.student-profile-updated')->count());
    }

    // ============================================================================================
    // T6.9 — E9 removed from process
    // ============================================================================================

    public function test_T6_9_E9_company_notice_with_replacement_invitation(): void
    {
        $inactiveB = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id, 'email' => 'old-recruiter@company-b.test', 'is_active' => false]);
        $secondB = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id, 'email' => 'second@company-b.test']);
        $p1 = $this->floatOk($this->jnf($this->companyA, [], 'SDE at A'));
        $p2 = $this->floatOk($this->jnf($this->companyB, [], 'Analyst at B'));
        $x = $this->student();
        $this->apply($x, $p1)->assertCreated();
        $this->apply($x, $p2)->assertCreated();
        $this->reachFinal($p1, [$x->roll_no]);
        $this->postJson("/api/admin/postings/{$p1->id}/results/publish", ['selections' => [
            ['application_id' => $this->appOf($p1, $x)->id, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();

        $expected = collect([$this->companyBUser->email, $secondB->email])->map(fn ($e) => strtolower($e))->sort()->values()->all();

        $this->postJson("/api/admin/postings/{$p2->id}/applications/{$this->appOf($p2, $x)->id}/remove-from-process")->assertOk();

        $notices = $this->allMails(PortalNoticeMail::class);
        $recipients = $notices->flatMap(fn ($m) => $this->addresses($m))->sort()->values()->all();
        $this->assertSame($expected, $recipients, 'E9 goes to every ACTIVE user of the affected company, nobody else');
        foreach ($notices as $m) {
            $html = $m->render();
            $this->assertStringContainsString($x->roll_no, $html);
            $this->assertStringContainsStringIgnoringCase('replacement', $html, 'E9 must invite a replacement request');
        }
        $this->assertSame(2, EmailLog::query()->where('template', 'emails.portal-notice')->count());
        $this->assertFalse($notices->contains(fn ($m) => $m->hasTo($inactiveB->email)));
    }

    // ============================================================================================
    // T6.10 — E10 proposals
    // ============================================================================================

    public function test_T6_10_E10_admins_on_submit_company_on_decision(): void
    {
        $admin2 = User::factory()->create(['role' => 'admin', 'email' => 'admin2@cdc-qa.test']);
        $inactiveAdmin = User::factory()->create(['role' => 'admin', 'email' => 'gone@cdc-qa.test', 'is_active' => false]);
        $secondA = User::factory()->create(['role' => 'company', 'company_id' => $this->companyA->id, 'email' => 'second@company-a.test']);

        $posting = $this->floatOk($this->jnf());
        $students = [$this->student(), $this->student(), $this->student()];
        foreach ($students as $s) {
            $this->apply($s, $posting)->assertCreated();
        }
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $round = $posting->rounds()->first();

        $expectedAdmins = collect([$this->admin->email, $admin2->email])->map(fn ($e) => strtolower($e))->sort()->values()->all();
        $expectedCompany = collect([$this->companyAUser->email, $secondA->email])->map(fn ($e) => strtolower($e))->sort()->values()->all();

        Sanctum::actingAs($this->companyAUser);
        $this->postJson("/api/company/postings/{$posting->id}/rounds/{$round->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => $students[0]->roll_no]]])->assertCreated();
        $this->postJson("/api/company/postings/{$posting->id}/rounds/{$round->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => $students[1]->roll_no]]])->assertCreated();

        $submit = $this->allMails(PortalNoticeMail::class);
        $this->assertSame(collect(array_merge($expectedAdmins, $expectedAdmins))->sort()->values()->all(), $submit->flatMap(fn ($m) => $this->addresses($m))->sort()->values()->all(), 'E10 submit → every active admin, once per proposal');
        $this->assertFalse($submit->contains(fn ($m) => $m->hasTo($inactiveAdmin->email)));
        $this->assertSame(2, PortalNotification::query()->where('user_id', $admin2->id)->count());

        [$p1, $p2] = ShortlistProposal::query()->orderBy('id')->get()->all();
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/proposals/{$p1->id}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/proposals/{$p2->id}", ['status' => 'rejected', 'admin_remark' => 'Candidate not in the pool'])->assertOk();

        $decisions = $this->allMails(PortalNoticeMail::class)->slice($submit->count())->values();
        $this->assertSame(collect(array_merge($expectedCompany, $expectedCompany))->sort()->values()->all(), $decisions->flatMap(fn ($m) => $this->addresses($m))->sort()->values()->all(), 'E10 decision → every active user of the company');
        $this->assertTrue($decisions->contains(fn ($m) => str_contains($m->render(), 'Candidate not in the pool')), 'reject remark reaches the company');
    }

    // ============================================================================================
    // T6.11 — no deadline reminders
    // ============================================================================================

    public function test_T6_11_no_deadline_reminder_mails_or_schedule(): void
    {
        $hits = [];
        foreach (['app', 'routes', 'bootstrap', 'resources/views/emails'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php') && stripos((string) file_get_contents($file->getPathname()), 'remind') !== false) {
                    $hits[] = $file->getPathname();
                }
            }
        }
        $this->assertSame([], $hits, 'a reminder implementation exists');

        // The only scheduled task is "Schedule For Later" opening job profiles (Superset parity S6.2, D112); it sends
        // the normal new-opening mail once, never a reminder. Anything else scheduled is still a failure.
        $console = (string) file_get_contents(base_path('routes/console.php'));
        $console = preg_replace("/Schedule::command\\('placement:open-scheduled'\\)->everyMinute\\(\\)->withoutOverlapping\\(\\);/", '', $console);
        $this->assertDoesNotMatchRegularExpression('/Schedule::|->daily|->hourly|->everyMinute|->cron\(/', $console);
        $this->assertStringNotContainsString('withSchedule', (string) file_get_contents(base_path('bootstrap/app.php')));
    }

    // ============================================================================================
    // T6.12 — institute email only
    // ============================================================================================

    public function test_T6_12_no_mail_ever_goes_to_a_personal_email(): void
    {
        $this->assertInstituteOnly('queued');
    }

    public function test_T6_12_no_mail_ever_goes_to_a_personal_email_in_sync_mode(): void
    {
        $this->assertInstituteOnly('sync');
    }

    private function assertInstituteOnly(string $mode): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/settings', ['mail_mode' => $mode])->assertOk();

        // E1 with personal_email set at creation.
        $this->createStudentViaApi('23JE0301')->assertCreated();
        $created = StudentProfile::query()->where('roll_no', '23JE0301')->sole();
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $created->id, 'status' => 'active']);
        $created->resumes()->create(['slot' => 1, 'label' => 'Main CV', 'file_path' => 'z.pdf', 'file_size' => 1, 'status' => 'approved']);

        $students = [$created, $this->student(), $this->student(), $this->student()];
        // A student also updates their own personal email through the self-service endpoint.
        $this->as($students[1]);
        $this->patchJson('/api/student/profile', ['personal_email' => 'self-updated@personal-mail.test'])->assertOk();

        // E2 + E3
        $posting = $this->floatOk($this->jnf(null, ['selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]]]));
        foreach ($students as $s) {
            $this->apply($s, $posting)->assertCreated();
        }
        // E4 + E5
        $this->reachFinal($posting, [$students[0]->roll_no, $students[1]->roll_no, $students[2]->roll_no]);
        $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => [
            ['application_id' => $this->appOf($posting, $students[0])->id, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();
        // E6
        $event = $this->postJson('/api/admin/events', ['title' => 'Town hall', 'event_type' => 'other', 'starts_at' => now()->addDay()->toIso8601String(), 'audience_type' => 'all'])->assertCreated()->json('event.id');
        $this->postJson("/api/admin/events/{$event}/publish")->assertOk();
        // E7
        $pending = $students[3]->resumes()->create(['slot' => 2, 'label' => 'Alt', 'file_path' => 'a.pdf', 'file_size' => 1, 'status' => 'pending']);
        $this->patchJson("/api/admin/resumes/{$pending->id}", ['status' => 'rejected', 'admin_remark' => 'Blurry scan'])->assertOk();
        // E8
        $this->patchJson("/api/admin/students/{$students[3]->id}", ['current_cgpa' => 8.9])->assertOk();

        $personal = StudentProfile::query()->whereNotNull('personal_email')->pluck('personal_email')->map(fn ($e) => strtolower($e))->all();
        $this->assertContains('self-updated@personal-mail.test', $personal);

        $mails = $this->allMails();
        $this->assertGreaterThanOrEqual(8, $mails->count(), 'precondition: every trigger produced mail');
        foreach ($mails as $m) {
            $leak = array_intersect($this->addresses($m), $personal);
            $this->assertSame([], array_values($leak), get_class($m).' was addressed to a personal email');
        }
        $this->assertSame(0, EmailLog::query()->whereIn('recipient_email', $personal)->count(), 'email_logs show a personal address');
    }

    // ============================================================================================
    // T6.13 — in-app notification per student-facing trigger
    // ============================================================================================

    /** QA F-041: fixed in P-1.13 (was in group qa-open until then). */
    public function test_T6_13_in_app_notification_for_every_student_facing_trigger(): void
    {
        $missing = [];
        $delta = function (User $user, callable $trigger) {
            $before = PortalNotification::query()->where('user_id', $user->id)->count();
            $trigger();

            return PortalNotification::query()->where('user_id', $user->id)->count() - $before;
        };

        // E1
        $before = PortalNotification::count();
        $this->createStudentViaApi('23JE0401')->assertCreated();
        $student = StudentProfile::query()->where('roll_no', '23JE0401')->sole();
        if (PortalNotification::query()->where('user_id', $student->user_id)->count() === 0) {
            $missing[] = 'E1 account created';
        }
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        $student->resumes()->create(['slot' => 1, 'label' => 'Main CV', 'file_path' => 'z.pdf', 'file_size' => 1, 'status' => 'approved']);
        $user = $this->userOf($student);
        $peer = $this->student();

        $posting = null;
        $checks = [
            'E2 job floated' => function () use (&$posting) {
                $posting = $this->floatOk($this->jnf(null, ['selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]]]));
            },
            'E3 application submitted' => function () use (&$posting, $student, $peer) {
                $this->apply($student, $posting)->assertCreated();
                $this->apply($peer, $posting)->assertCreated();
            },
            'E4 round result' => function () use (&$posting, $student, $peer) {
                $this->reachFinal($posting, [$student->roll_no, $peer->roll_no]);
            },
            'E5 final result' => function () use (&$posting, $student) {
                $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => [
                    ['application_id' => $this->appOf($posting, $student)->id, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
                ]])->assertOk();
            },
            'E6 event announced' => function () {
                Sanctum::actingAs($this->admin);
                $id = $this->postJson('/api/admin/events', ['title' => 'Workshop', 'event_type' => 'workshop', 'starts_at' => now()->addDay()->toIso8601String(), 'audience_type' => 'all'])->assertCreated()->json('event.id');
                $this->postJson("/api/admin/events/{$id}/publish")->assertOk();
            },
            'E7 resume decided' => function () use ($student) {
                $r = $student->resumes()->create(['slot' => 2, 'label' => 'Alt', 'file_path' => 'a.pdf', 'file_size' => 1, 'status' => 'pending']);
                Sanctum::actingAs($this->admin);
                $this->patchJson("/api/admin/resumes/{$r->id}", ['status' => 'rejected', 'admin_remark' => 'Blurry'])->assertOk();
            },
            'E8 profile changed by admin' => function () use ($student) {
                Sanctum::actingAs($this->admin);
                $this->patchJson("/api/admin/students/{$student->id}", ['phone' => '9876543210'])->assertOk();
            },
            'E8 branch change decided' => function () use ($student) {
                $this->as($student);
                $this->postJson('/api/student/branch-change', ['requested_branch' => self::ECE, 'reason' => 'Branch sliding after first year was approved.'])->assertCreated();
                Sanctum::actingAs($this->admin);
                $this->patchJson('/api/admin/branch-changes/'.\App\Models\BranchChangeRequest::query()->latest('id')->value('id'), ['status' => 'rejected', 'admin_remark' => 'No seat'])->assertOk();
            },
        ];

        foreach ($checks as $label => $trigger) {
            if ($delta($user, $trigger) < 1) {
                $missing[] = $label;
            }
        }

        $this->assertSame([], $missing, 'Student-facing triggers without an in-app notification: '.implode(', ', $missing));
    }

    // ============================================================================================
    // T6.15 — templates
    // ============================================================================================

    public function test_T6_15_templates_use_frontend_url_and_no_hardcoded_localhost(): void
    {
        config(['app.frontend_url' => 'https://portal.cdc-qa.test']);

        $this->createStudentViaApi('23JE0501')->assertCreated();
        $student = StudentProfile::query()->where('roll_no', '23JE0501')->sole();
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        $student->resumes()->create(['slot' => 1, 'label' => 'Main CV', 'file_path' => 'z.pdf', 'file_size' => 1, 'status' => 'approved']);

        $posting = $this->floatOk($this->jnf());
        $this->apply($student, $posting)->assertCreated();
        $this->reachFinal($posting, [$student->roll_no]);
        $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => [
            ['application_id' => $this->appOf($posting, $student)->id, 'offer_type' => 'fulltime', 'block' => false],
        ]])->assertOk();

        $rendered = [
            'E1 invitation' => $this->allMails(StudentInvitationMail::class)->first(),
            'E2 floated' => $this->allMails(PostingFloatedMail::class)->first(),
            'E3 submitted' => $this->allMails(ApplicationSubmittedMail::class)->first(),
            'E4 round result' => $this->allMails(RoundResultMail::class)->first(),
            'E5 offer' => $this->allMails(OfferMail::class)->first(),
        ];

        foreach ($rendered as $label => $mailable) {
            $this->assertNotNull($mailable, "{$label}: not produced");
            $html = $mailable->render();
            $this->assertStringContainsString('https://portal.cdc-qa.test/', $html, "{$label}: link must use config('app.frontend_url')");
            $this->assertStringNotContainsStringIgnoringCase('localhost', $html, "{$label}: hard-coded localhost");
            $this->assertStringNotContainsString('127.0.0.1', $html, "{$label}: hard-coded loopback");
        }
    }
}
