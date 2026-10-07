<?php

namespace Tests\Feature;

use App\Mail\PostingFloatedMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
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

/**
 * D103: the CDC can change a floated drive's eligibility (PATCH /admin/postings/{p}/eligibility, its preview, and the
 * form editor's eligibility keys), and every reader picks the new rules up through EligibilityService.
 */
class PostingEligibilityEditTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const CSE = 'Computer Science & Engineering';

    private const MINING = 'Mining Engineering';

    private User $admin;

    private PlacementCycle $ft;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->ft = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    // ------------------------------------------------------------------ fixtures

    /** @return array<string, mixed> */
    private function branch(string $name, bool $selected = true, string $cgpa = '7.0', bool $backlogs = false): array
    {
        return ['branch' => $name, 'selected' => $selected, 'cgpa' => $cgpa, 'backlogsAllowed' => $backlogs];
    }

    /** @return list<array<string, mixed>> */
    private function matrix(array $branches): array
    {
        return [['programme' => self::BTECH, 'expanded' => true, 'branches' => $branches]];
    }

    private function floated(?array $branches = null): JobPosting
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'Software Engineer', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'Software Engineer',
                'eligibility' => $this->matrix($branches ?? [$this->branch(self::CSE), $this->branch(self::MINING, false)]),
                'globalCgpa' => '7.0',
                'globalBacklogs' => false,
                'genderFilter' => 'all',
                'graduatingBatch' => '2027',
                'minTenthPercent' => '',
                'minTwelfthPercent' => '',
                'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->ft->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function student(array $attributes = []): StudentProfile
    {
        $student = StudentProfile::factory()->create($attributes);
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student->fresh('user');
    }

    private function resume(StudentProfile $student): Resume
    {
        return $student->resumes()->create(['slot' => 1, 'label' => 'Main', 'file_path' => 'resumes/x.pdf', 'file_size' => 10, 'status' => 'approved']);
    }

    private function apply(StudentProfile $student, JobPosting $posting)
    {
        Sanctum::actingAs($student->user);

        return $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => ($student->resumes()->first() ?? $this->resume($student))->id]);
    }

    private function edit(JobPosting $posting, array $payload)
    {
        Sanctum::actingAs($this->admin);

        return $this->patchJson("/api/admin/postings/{$posting->id}/eligibility", $payload);
    }

    private function preview(JobPosting $posting, array $criteria)
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson("/api/admin/postings/{$posting->id}/eligibility/preview?".http_build_query(['criteria' => json_encode($criteria)]));
    }

    /** @return array<string, mixed> */
    private function formKeys(JobPosting $posting): array
    {
        $data = $posting->fresh()->postable->form_data;

        return array_intersect_key($data, array_flip(JobPosting::SNAPSHOT_KEYS));
    }

    // ------------------------------------------------------------------ tests

    public function test_lowering_the_cgpa_cutoff_makes_a_previously_ineligible_student_eligible_and_able_to_apply(): void
    {
        $posting = $this->floated();
        $low = $this->student(['current_cgpa' => 6.5]);

        Sanctum::actingAs($low->user);
        $this->getJson('/api/student/postings')->assertJsonPath('postings.0.eligibility.eligible', false);
        $this->apply($low, $posting)->assertStatus(422);

        $this->edit($posting, ['eligibility' => $this->matrix([$this->branch(self::CSE, true, '6.0'), $this->branch(self::MINING, false)])])
            ->assertOk()
            ->assertJsonPath('posting.eligibility_snapshot.eligibility.0.branches.0.cgpa', '6.0');

        Sanctum::actingAs($low->user);
        $this->getJson('/api/student/postings')->assertJsonPath('postings.0.eligibility.eligible', true);
        $this->getJson("/api/student/postings/{$posting->id}")
            ->assertOk()
            ->assertJsonPath('posting.form_data.eligibility.0.branches.0.cgpa', '6.0');
        $this->apply($low, $posting)->assertCreated();
    }

    public function test_raising_the_cutoff_blocks_new_applications_but_keeps_existing_ones(): void
    {
        $posting = $this->floated();
        $applied = $this->student(['current_cgpa' => 8.0]);
        $late = $this->student(['current_cgpa' => 8.0]);
        $this->apply($applied, $posting)->assertCreated();
        $application = Application::sole();

        $this->edit($posting, ['eligibility' => $this->matrix([$this->branch(self::CSE, true, '8.5'), $this->branch(self::MINING, false)])])->assertOk();

        $this->apply($late, $posting)->assertStatus(422)->assertJsonPath('reasons.0', 'CGPA below cutoff (8.0 < 8.5)');

        $this->assertSame('applied', $application->fresh()->status);
        Sanctum::actingAs($applied->user);
        $this->getJson("/api/student/postings/{$posting->id}")->assertOk()->assertJsonPath('posting.application.status', 'applied');
        $this->patchJson("/api/student/applications/{$application->id}", ['resume_id' => $application->resume_id])->assertOk();
        $this->postJson("/api/student/applications/{$application->id}/withdraw")->assertOk();
    }

    public function test_removing_a_branch_hides_the_drive_from_that_branchs_non_applicants_only(): void
    {
        $posting = $this->floated([$this->branch(self::CSE), $this->branch(self::MINING)]);
        $minerApplied = $this->student(['branch' => self::MINING]);
        $minerOther = $this->student(['branch' => self::MINING]);
        $this->apply($minerApplied, $posting)->assertCreated();

        $this->edit($posting, ['eligibility' => $this->matrix([$this->branch(self::CSE), $this->branch(self::MINING, false)])])->assertOk();

        Sanctum::actingAs($minerOther->user);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 0);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();

        Sanctum::actingAs($minerApplied->user);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/student/postings/{$posting->id}")->assertOk()->assertJsonPath('posting.eligibility.eligible', false);
    }

    public function test_check_and_eligible_students_query_still_agree_after_an_edit(): void
    {
        $posting = $this->floated([$this->branch(self::CSE), $this->branch(self::MINING)]);
        $population = [
            $this->student(['current_cgpa' => 7.2]),
            $this->student(['current_cgpa' => 6.8]),
            $this->student(['current_cgpa' => 8.4, 'gender' => 'female']),
            $this->student(['branch' => self::MINING, 'current_cgpa' => 9.0]),
            $this->student(['current_cgpa' => 8.0, 'ongoing_backlogs' => 1, 'total_backlogs' => 2]),
            $this->student(['current_cgpa' => 8.0, 'tenth_percent' => 70]),
            $this->student(['current_cgpa' => 8.0, 'graduating_batch' => 2028]),
        ];

        $this->edit($posting, [
            'eligibility' => $this->matrix([
                ['branch' => self::CSE, 'selected' => true, 'cgpa' => '6.75', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '1', 'maxTotalBacklogs' => '2'],
                $this->branch(self::MINING, false),
            ]),
            'minTenthPercent' => '75',
            'genderFilter' => 'all',
        ])->assertOk();

        $posting = $posting->fresh();
        $service = app(EligibilityService::class);
        $fromQuery = $service->eligibleStudentsQuery($posting)->pluck('id')->sort()->values()->all();
        $fromCheck = collect($population)->filter(fn ($s) => $service->check($s->fresh(), $posting)['eligible'])->pluck('id')->sort()->values()->all();

        $this->assertSame($fromCheck, $fromQuery);
        $this->assertSame([$population[0]->id, $population[1]->id, $population[2]->id, $population[4]->id], $fromQuery);
    }

    public function test_notify_mails_only_newly_eligible_students_and_never_twice(): void
    {
        $already = $this->student(['current_cgpa' => 8.0]);
        $low = $this->student(['current_cgpa' => 6.5]);
        $quiet = $this->student(['current_cgpa' => 6.2]);
        $posting = $this->floated();
        Mail::assertQueued(PostingFloatedMail::class, 1);

        $lower = fn (string $cgpa) => ['eligibility' => $this->matrix([$this->branch(self::CSE, true, $cgpa), $this->branch(self::MINING, false)])];

        // Notify off: nobody is mailed.
        $this->edit($posting, $lower('6.5') + ['notify_newly_eligible' => false])->assertOk()->assertJsonPath('result.notified', 0);
        Mail::assertQueued(PostingFloatedMail::class, 1);

        // Back up, then down again with notify on (the default): only the low student is mailed; the float audience is not.
        $this->edit($posting, $lower('7.0'))->assertOk();
        $this->edit($posting, $lower('6.5'))->assertOk()->assertJsonPath('result.newly_eligible', 1)->assertJsonPath('result.notified', 1);
        Mail::assertQueued(PostingFloatedMail::class, 2);
        Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($low->user->email) && ! $m->hasBcc($already->user->email) && count($m->bcc) === 1);
        $this->assertSame(1, $low->user->notifications()->count());

        // Up and down again: the low student was already told about this drive, so no second mail.
        $this->edit($posting, $lower('7.0'))->assertOk();
        $this->edit($posting, $lower('6.0'))->assertOk()->assertJsonPath('result.newly_eligible', 2)->assertJsonPath('result.notified', 1);
        Mail::assertQueued(PostingFloatedMail::class, 3);
        Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($quiet->user->email) && count($m->bcc) === 1);
        $this->assertSame(1, Mail::queued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($low->user->email))->count());
        $this->assertSame(1, Mail::queued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($already->user->email))->count());
    }

    public function test_the_audit_row_has_before_and_after_values(): void
    {
        $posting = $this->floated();
        $this->edit($posting, ['minTwelfthPercent' => '60.5', 'genderFilter' => 'female'])->assertOk();

        $row = AuditLog::where('action', 'posting.eligibility_update')->sole();
        $this->assertSame($this->admin->id, $row->user_id);
        $this->assertSame(JobPosting::class, $row->subject_type);
        $this->assertSame($posting->id, $row->subject_id);
        $this->assertSame('', $row->before['criteria']['minTwelfthPercent']);
        $this->assertSame('all', $row->before['criteria']['genderFilter']);
        $this->assertSame('60.5', $row->after['criteria']['minTwelfthPercent']);
        $this->assertSame('female', $row->after['criteria']['genderFilter']);
        $this->assertSame('posting', $row->after['source']);
    }

    public function test_form_data_and_snapshot_stay_identical_after_both_edit_paths(): void
    {
        $posting = $this->floated();

        $this->edit($posting, ['eligibility' => $this->matrix([$this->branch(self::CSE, true, '6.5'), $this->branch(self::MINING)]), 'minTenthPercent' => '70'])->assertOk();
        $this->assertEquals(JobPosting::withoutAdminOnlyKeys($posting->fresh()->eligibility_snapshot), $this->formKeys($posting)); // admin-only keys stay in the snapshot (fix M2)
        $this->assertSame('70', $posting->fresh()->eligibility_snapshot['minTenthPercent']);

        // The admin form editor routes eligibility keys through the same update.
        $form = $posting->fresh()->postable;
        $data = $form->form_data;
        $data['eligibility'][0]['branches'][0]['cgpa'] = '8.25';
        $data['jobTitle'] = 'Senior Software Engineer';
        $this->patchJson("/api/admin/jnfs/{$form->id}/form-data", ['form_data' => $data])->assertOk();

        $posting = $posting->fresh();
        $this->assertSame('8.25', $posting->eligibility_snapshot['eligibility'][0]['branches'][0]['cgpa']);
        $this->assertEquals(JobPosting::withoutAdminOnlyKeys($posting->eligibility_snapshot), $this->formKeys($posting)); // fix M2
        $this->assertSame('Senior Software Engineer', $posting->postable->form_data['jobTitle']);

        $rows = AuditLog::where('action', 'posting.eligibility_update')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('form_editor', $rows[1]->after['source']);
        $this->assertSame('6.5', $rows[1]->before['criteria']['eligibility'][0]['branches'][0]['cgpa']);

        // Bad eligibility numbers through the form editor are refused like on the drive page, and nothing changes.
        $data['minTenthPercent'] = '101';
        $this->patchJson("/api/admin/jnfs/{$form->id}/form-data", ['form_data' => $data])->assertStatus(422);
        $this->assertSame('70', $posting->fresh()->eligibility_snapshot['minTenthPercent']);
        $this->assertEquals(JobPosting::withoutAdminOnlyKeys($posting->fresh()->eligibility_snapshot), $this->formKeys($posting)); // admin-only keys stay in the snapshot (fix M2)
    }

    public function test_preview_counts_newly_and_no_longer_eligible_and_lists_affected_applicants_without_writing(): void
    {
        $posting = $this->floated();
        $keeps = $this->student(['current_cgpa' => 9.0]);
        $loses = $this->student(['current_cgpa' => 7.5]);
        $losesQuietly = $this->student(['current_cgpa' => 7.2]);
        $gains = $this->student(['current_cgpa' => 6.6]);
        $this->apply($keeps, $posting)->assertCreated();
        $this->apply($loses, $posting)->assertCreated();

        $criteria = ['eligibility' => $this->matrix([$this->branch(self::CSE, true, '7.0'), $this->branch(self::MINING, false)])];
        $criteria['eligibility'][0]['branches'][0]['cgpa'] = '7.6';
        $this->preview($posting, $criteria)->assertOk()
            ->assertJsonPath('preview.currently_eligible', 3)
            ->assertJsonPath('preview.eligible_after', 1)
            ->assertJsonPath('preview.newly_eligible', 0)
            ->assertJsonPath('preview.no_longer_eligible', 2)
            ->assertJsonCount(1, 'preview.affected_applicants')
            ->assertJsonPath('preview.affected_applicants.0.roll_no', $loses->roll_no)
            ->assertJsonPath('preview.affected_applicants.0.reasons.0', 'CGPA below cutoff (7.5 < 7.6)');

        $criteria['eligibility'][0]['branches'][0]['cgpa'] = '6.5';
        $this->preview($posting, $criteria)->assertOk()
            ->assertJsonPath('preview.newly_eligible', 1)
            ->assertJsonPath('preview.will_be_notified', 1)
            ->assertJsonPath('preview.no_longer_eligible', 0)
            ->assertJsonCount(0, 'preview.affected_applicants');

        $this->assertSame('7.0', $posting->fresh()->eligibility_snapshot['eligibility'][0]['branches'][0]['cgpa']);
        $this->assertSame(0, AuditLog::where('action', 'posting.eligibility_update')->count());
        unset($gains, $losesQuietly);
    }

    public function test_criteria_are_validated_like_the_wizard_and_the_catalogue(): void
    {
        $posting = $this->floated();
        $cases = [
            ['minTenthPercent' => '100.5'],
            ['minTwelfthPercent' => '80.123'],
            ['genderFilter' => 'any'],
            ['graduatingBatch' => '27'],
            ['eligibility' => $this->matrix([['branch' => self::CSE, 'selected' => true, 'cgpa' => '7', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '1000']])],
            ['eligibility' => $this->matrix([['branch' => self::CSE, 'selected' => true, 'cgpa' => '7', 'backlogsAllowed' => true, 'maxTotalBacklogs' => '1.5']])],
            ['eligibility' => $this->matrix([['branch' => self::CSE, 'selected' => true, 'cgpa' => '11']])],
            ['eligibility' => $this->matrix([$this->branch('Underwater Basket Weaving')])],
            ['eligibility' => [['programme' => 'B.Arch (5 Year)', 'branches' => [$this->branch(self::CSE)]]]],
            ['eligibility' => $this->matrix([$this->branch(self::CSE, false)])],
        ];

        foreach ($cases as $payload) {
            $this->edit($posting, $payload)->assertStatus(422);
            $this->preview($posting, $payload)->assertStatus(422);
        }

        // Blank = no limit; backlogs switched off clears the caps (D57).
        $this->edit($posting, [
            'minTenthPercent' => '',
            'eligibility' => $this->matrix([['branch' => self::CSE, 'selected' => true, 'cgpa' => '', 'backlogsAllowed' => false, 'maxOngoingBacklogs' => '3']]),
        ])->assertOk();
        $row = $posting->fresh()->eligibility_snapshot['eligibility'][0]['branches'][0];
        $this->assertSame('', $row['cgpa']);
        $this->assertSame('', $row['maxOngoingBacklogs']);
        $this->assertSame(1, AuditLog::where('action', 'posting.eligibility_update')->count());
    }

    public function test_completed_or_cancelled_drives_return_422(): void
    {
        $payload = ['minTenthPercent' => '60'];

        foreach (['completed', 'cancelled'] as $status) {
            $posting = $this->floated();
            $posting->update(['status' => $status]);

            $this->edit($posting, $payload)->assertStatus(422)->assertJsonPath('message', "This job profile is {$status}, so its eligibility can no longer be changed.");
            $this->preview($posting, $payload)->assertStatus(422);
            $this->assertSame('', $posting->fresh()->eligibility_snapshot['minTenthPercent']);
        }

        // In process is still editable.
        $posting = $this->floated();
        $posting->update(['status' => 'in_process']);
        $this->edit($posting, $payload)->assertOk();

        // The form editor refuses eligibility changes on a completed drive, so form and drive never disagree.
        $posting = $this->floated();
        $posting->update(['status' => 'completed']);
        $data = $posting->postable->form_data;
        $data['minTenthPercent'] = '60';
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/jnfs/{$posting->postable_id}/form-data", ['form_data' => $data])->assertStatus(422);
        $this->assertSame('', $posting->fresh()->postable->form_data['minTenthPercent']);
    }

    public function test_students_and_companies_cannot_use_either_endpoint(): void
    {
        $posting = $this->floated();
        $student = $this->student();
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $posting->postable->company_id]);

        foreach ([$student->user, $companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->patchJson("/api/admin/postings/{$posting->id}/eligibility", ['minTenthPercent' => '60'])->assertForbidden();
            $this->getJson("/api/admin/postings/{$posting->id}/eligibility/preview?criteria=".urlencode('{"minTenthPercent":"60"}'))->assertForbidden();
        }

        $this->assertSame('', $posting->fresh()->eligibility_snapshot['minTenthPercent']);
    }

    public function test_reopening_applications_with_a_change_lets_everyone_now_eligible_apply_and_mails_those_never_told(): void
    {
        $applied = $this->student(['current_cgpa' => 8.0]);
        $low = $this->student(['current_cgpa' => 6.5]);
        $posting = $this->floated();
        $late = $this->student(['current_cgpa' => 8.0]); // enrolled after the float, so never mailed
        $this->apply($applied, $posting)->assertCreated();
        $posting->update(['status' => 'in_process', 'application_deadline' => now()->subHour()]);
        $lower = ['eligibility' => $this->matrix([$this->branch(self::CSE, true, '6.0'), $this->branch(self::MINING, false)])];

        $this->preview($posting, $lower)->assertOk()
            ->assertJsonPath('preview.applications_open', false)
            ->assertJsonPath('preview.will_be_notified', 0)
            ->assertJsonPath('preview.can_reopen', true)
            ->assertJsonPath('preview.notified_on_reopen', 2);

        $until = now()->addDays(3)->startOfMinute();
        $this->edit($posting, $lower + ['applications_open_until' => $until->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('result.changed', true)
            ->assertJsonPath('result.reopened', true)
            ->assertJsonPath('result.notified', 2)
            ->assertJsonPath('posting.status', 'open');

        $this->assertTrue($posting->fresh()->application_deadline->equalTo($until));
        Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($low->user->email) && $m->hasBcc($late->user->email) && ! $m->hasBcc($applied->user->email));

        $this->apply($low, $posting)->assertCreated();
        $this->apply($late, $posting)->assertCreated();

        $row = AuditLog::where('action', 'posting.eligibility_update')->sole();
        $this->assertSame('in_process', $row->before['applications']['status']);
        $this->assertSame('open', $row->after['applications']['status']);
    }

    public function test_reopening_is_refused_once_results_exist_or_for_a_past_date(): void
    {
        $posting = $this->floated();
        $applied = $this->student();
        $this->apply($applied, $posting)->assertCreated();
        $posting->update(['status' => 'in_process', 'application_deadline' => now()->subHour()]);
        $payload = ['minTenthPercent' => '60'];

        $this->edit($posting, $payload + ['applications_open_until' => now()->subDay()->toIso8601String()])->assertStatus(422);

        \App\Models\ApplicationRoundResult::create([
            'application_id' => Application::sole()->id, 'posting_round_id' => $posting->rounds()->first()->id, 'result' => 'selected',
        ]);
        $this->preview($posting, $payload)->assertOk()->assertJsonPath('preview.can_reopen', false);
        $this->edit($posting, $payload + ['applications_open_until' => now()->addDays(2)->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Results have already been entered for this job profile, so applications cannot be reopened.');

        $this->assertSame('', $posting->fresh()->eligibility_snapshot['minTenthPercent']);
        $this->assertSame('in_process', $posting->fresh()->status);

        // Without reopening, the eligibility change itself still goes through.
        $this->edit($posting, $payload)->assertOk()->assertJsonPath('result.reopened', false)->assertJsonPath('result.notified', 0);
    }
}
