<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30',
            'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function posting(array $overrides = [], string $type = 'jnf'): JobPosting
    {
        $rules = array_replace_recursive([
            'eligibility' => [[
                'programme' => self::BTECH,
                'branches' => [
                    ['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false],
                    ['branch' => 'Mining Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '1', 'maxTotalBacklogs' => '2'],
                    ['branch' => 'Civil Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                    ['branch' => 'Electrical Engineering', 'selected' => false, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                ],
            ]],
            'globalCgpa' => '7.0',
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
        ], $overrides);

        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);
        $formClass = $type === 'inf' ? Inf::class : Jnf::class;
        $form = $formClass::create($type === 'inf'
            ? ['company_id' => $company->id, 'internship_title' => 'Intern', 'internship_description' => 'x', 'status' => 'accepted', 'form_data' => $rules]
            : ['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => $rules]);

        return JobPosting::create([
            'postable_type' => $formClass,
            'postable_id' => $form->id,
            'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3),
            'status' => 'open',
            'eligibility_snapshot' => array_intersect_key($rules, array_flip(JobPosting::SNAPSHOT_KEYS)),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function student(array $attributes = [], bool $enrolled = true): StudentProfile
    {
        $student = StudentProfile::factory()->create($attributes);
        if ($enrolled) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }

        return $student->fresh();
    }

    /** @return list<string> */
    private function reasons(StudentProfile $student, JobPosting $posting): array
    {
        return app(EligibilityService::class)->check($student, $posting)['reasons'];
    }

    private function assertAgrees(JobPosting $posting): void
    {
        $service = app(EligibilityService::class);
        $fromQuery = $service->eligibleStudentsQuery($posting)->pluck('id')->sort()->values()->all();
        $fromCheck = StudentProfile::all()->filter(fn ($s) => $service->check($s, $posting)['eligible'])->pluck('id')->sort()->values()->all();

        $this->assertSame($fromCheck, $fromQuery, 'eligibleStudentsQuery() and check() disagree');
    }

    public function test_eligible_student_has_no_reasons(): void
    {
        $posting = $this->posting();
        $this->assertSame([], $this->reasons($this->student(), $posting));
    }

    public function test_not_enrolled(): void
    {
        $posting = $this->posting();
        $this->assertContains('You are not enrolled in this placement cycle.', $this->reasons($this->student([], false), $posting));
    }

    public function test_suspended_enrolment_and_account(): void
    {
        $posting = $this->posting();
        $student = $this->student();
        $student->cycleEnrollments()->update(['status' => 'suspended']);
        $student->user->update(['is_active' => false]);

        $reasons = $this->reasons($student->fresh(), $posting);
        $this->assertContains('Your enrolment in this placement cycle is suspended.', $reasons);
        $this->assertContains('Your account is suspended.', $reasons);
    }

    public function test_branch_not_listed_or_not_selected(): void
    {
        $posting = $this->posting();
        $this->assertContains('Your branch is not eligible.', $this->reasons($this->student(['branch' => 'Electrical Engineering']), $posting));
        $this->assertContains('Your branch is not eligible.', $this->reasons($this->student(['branch' => 'Engineering Physics']), $posting));
        $this->assertContains('Your programme is not eligible.', $this->reasons($this->student(['programme' => 'MBA (2 Year) - CAT', 'branch' => 'Finance']), $posting));
    }

    public function test_cgpa_cutoff_and_missing_cgpa(): void
    {
        $posting = $this->posting();
        $this->assertContains('CGPA below cutoff (6.8 < 7.0)', $this->reasons($this->student(['current_cgpa' => 6.8]), $posting));
        $this->assertSame([], $this->reasons($this->student(['current_cgpa' => 7.0]), $posting));
        $this->assertContains('CGPA not on record (cutoff 7.0).', $this->reasons($this->student(['current_cgpa' => null]), $posting));
    }

    public function test_legacy_boolean_backlogs_false_requires_zero(): void
    {
        $posting = $this->posting();
        $this->assertContains('No backlogs allowed (you have 0 ongoing, 1 total).', $this->reasons($this->student(['total_backlogs' => 1]), $posting));
    }

    public function test_legacy_boolean_backlogs_true_is_unlimited(): void
    {
        $posting = $this->posting();
        $this->assertSame([], $this->reasons($this->student(['branch' => 'Civil Engineering', 'ongoing_backlogs' => 5, 'total_backlogs' => 9]), $posting));
    }

    public function test_numeric_backlog_caps(): void
    {
        $posting = $this->posting();
        $this->assertSame([], $this->reasons($this->student(['branch' => 'Mining Engineering', 'ongoing_backlogs' => 1, 'total_backlogs' => 2]), $posting));

        $reasons = $this->reasons($this->student(['branch' => 'Mining Engineering', 'ongoing_backlogs' => 2, 'total_backlogs' => 3]), $posting);
        $this->assertContains('Ongoing backlogs above the limit (2 > 1).', $reasons);
        $this->assertContains('Total backlogs above the limit (3 > 2).', $reasons);
    }

    public function test_gender_filter(): void
    {
        $posting = $this->posting(['genderFilter' => 'female']);
        $this->assertContains('Open to female students only.', $this->reasons($this->student(['gender' => 'male']), $posting));
        $this->assertSame([], $this->reasons($this->student(['gender' => 'female']), $posting));
    }

    public function test_graduating_batch(): void
    {
        $posting = $this->posting();
        $this->assertContains('Open to the 2027 graduating batch only.', $this->reasons($this->student(['graduating_batch' => 2028]), $posting));
    }

    public function test_tenth_and_twelfth_cutoffs(): void
    {
        $posting = $this->posting(['minTenthPercent' => '80', 'minTwelfthPercent' => '75.5']);
        $reasons = $this->reasons($this->student(['tenth_percent' => 79.99, 'twelfth_percent' => null]), $posting);

        $this->assertContains('10th % below cutoff (79.99 < 80.0)', $reasons);
        $this->assertContains('12th % not on record (cutoff 75.5).', $reasons);
        $this->assertSame([], $this->reasons($this->student(['tenth_percent' => 80, 'twelfth_percent' => 75.5]), $posting));
    }

    public function test_snapshot_is_frozen_against_later_form_edits(): void
    {
        $posting = $this->posting();
        $student = $this->student(['current_cgpa' => 6.5]);

        $data = $posting->postable->form_data;
        $data['eligibility'][0]['branches'][0]['cgpa'] = '6.0';
        $posting->postable->update(['form_data' => $data]);

        $this->assertContains('CGPA below cutoff (6.5 < 7.0)', $this->reasons($student, $posting->fresh()));
    }

    public function test_query_and_check_agree_across_a_mixed_population(): void
    {
        $this->student();
        $this->student(['current_cgpa' => 6.99]);
        $this->student(['current_cgpa' => null]);
        $this->student(['total_backlogs' => 1]);
        $this->student(['branch' => 'Mining Engineering', 'current_cgpa' => 6.0, 'ongoing_backlogs' => 1, 'total_backlogs' => 2]);
        $this->student(['branch' => 'Mining Engineering', 'ongoing_backlogs' => 2, 'total_backlogs' => 2]);
        $this->student(['branch' => 'Civil Engineering', 'ongoing_backlogs' => 4, 'total_backlogs' => 7]);
        $this->student(['branch' => 'Electrical Engineering']);
        $this->student(['graduating_batch' => 2028]);
        $this->student(['gender' => 'female', 'tenth_percent' => 60]);
        $this->student([], false);
        $suspended = $this->student();
        $suspended->user->update(['is_active' => false]);

        $this->assertAgrees($this->posting());
        $this->assertAgrees($this->posting(['genderFilter' => 'female', 'minTenthPercent' => '70']));
        $this->assertAgrees($this->posting(['graduatingBatch' => '']));

        $this->assertSame(4, app(EligibilityService::class)->eligibleStudentsQuery($this->posting())->count());
    }

    public function test_legacy_per_programme_batches_and_phd_exemption(): void
    {
        $posting = $this->posting(['graduatingBatch' => '', 'eligibility' => [0 => ['graduatingBatches' => ['2027']]]]);
        $this->assertContains('Open to the 2027 graduating batch only.', $this->reasons($this->student(['graduating_batch' => 2029]), $posting));
        $this->assertSame([], $this->reasons($this->student(['graduating_batch' => 2027]), $posting));
        $this->assertAgrees($posting);

        $phd = 'Ph.D - GATE/NET';
        $phdPosting = $this->posting(['eligibility' => [1 => ['programme' => $phd, 'branches' => [['branch' => 'All Departments (Specify in Job Description)', 'selected' => true, 'cgpa' => '', 'backlogsAllowed' => true]]]]]);
        $this->assertSame([], $this->reasons($this->student(['programme' => $phd, 'branch' => 'All Departments (Specify in Job Description)', 'graduating_batch' => 2030]), $phdPosting));
        $this->assertAgrees($phdPosting);
    }

    public function test_three_decimal_cutoffs_agree(): void
    {
        $this->student(['current_cgpa' => 6.99]);
        $this->student(['current_cgpa' => 7.0]);
        $posting = $this->posting(['eligibility' => [0 => ['branches' => [0 => ['cgpa' => '6.994']]]], 'minTenthPercent' => '89.996']);

        $this->assertAgrees($posting);
    }
}
