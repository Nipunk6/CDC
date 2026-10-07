<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\BranchChangeRequest;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\ExportTemplate;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PolicyDocument;
use App\Models\PostingDocument;
use App\Models\ProgrammeBranch;
use App\Models\ShortlistProposal;
use App\Models\StudentCategory;
use App\Models\StudentNote;
use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\SettingsService;
use App\Services\StudentAccountService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA Section A — "Admin is god" + audit (spec A1.6, B1 "Audit", C17, M10.3).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class SAAuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    /** Admin mutating routes that already existed at Phase 1 (df7034b routes/api.php) — predate the audit requirement. */
    private const PHASE1_ROUTES = [
        'POST api/admin/manage-admins',
        'DELETE api/admin/manage-admins/{user}',
        'POST api/admin/programme-branches',
        'PATCH api/admin/programme-branches/status',
        'DELETE api/admin/programme-branches/{programmeBranch}',
        'PATCH api/admin/jnfs/{jnf}/status',
        'PATCH api/admin/jnfs/{jnf}/remarks/latest',
        'POST api/admin/jnfs/{jnf}/notes',
        'PATCH api/admin/jnfs/{jnf}/form-data',
        'PATCH api/admin/infs/{inf}/status',
        'PATCH api/admin/infs/{inf}/remarks/latest',
        'POST api/admin/infs/{inf}/notes',
        'PATCH api/admin/infs/{inf}/form-data',
        'PUT api/admin/companies/{company}',
        'POST api/admin/policy-documents',
        'PUT|PATCH api/admin/policy-documents/{policy_document}',
        'DELETE api/admin/policy-documents/{policy_document}',
    ];

    /**
     * Admin mutating routes that write NO audit row by design. They are still called and must succeed; only the audit
     * assertion is skipped. Keep a one-line reason per entry.
     */
    private const NO_AUDIT_ROUTES = [
        'POST api/admin/audiences/preview' => 'read-only audience count for the notice/survey composers; writes nothing',
    ];

    private User $adminA;

    private User $adminB;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $this->adminA = User::factory()->create(['role' => 'admin', 'name' => 'Admin Alpha', 'email' => 'qa-admin-a@cdc.qa.test', 'is_super_admin' => true]);
        $this->adminB = User::factory()->create(['role' => 'admin', 'name' => 'Admin Beta', 'email' => 'qa-admin-b@cdc.qa.test', 'is_super_admin' => false]);
    }

    // ------------------------------------------------------------------ fixtures

    private function student(?PlacementCycle $cycle = null, array $attributes = []): StudentProfile
    {
        $this->seq++;
        $roll = sprintf('26AU%04d', $this->seq);
        $email = strtolower($roll).'@students.qa.test';
        $student = StudentProfile::factory()->create(array_merge([
            'roll_no' => $roll, 'institute_email' => $email, 'full_name' => 'Student '.chr(64 + ($this->seq % 26 ?: 26)),
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email]),
        ], $attributes));
        if ($cycle) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }
        $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => "resumes/{$roll}/1.pdf", 'file_size' => 10, 'status' => 'approved']);

        return $student->fresh('user');
    }

    private function jnf(Company $company, string $title, string $status = 'accepted', array $rounds = ['aptitude_test', 'hr_interview']): Jnf
    {
        return Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => $status,
            'form_data' => [
                'jobTitle' => $title, 'currency' => 'INR', 'graduatingBatch' => '2027',
                'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => array_map(fn ($t) => ['type' => $t, 'enabled' => true], $rounds),
            ],
        ]);
    }

    private function inf(Company $company, string $title, string $status): Inf
    {
        return Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => $status, 'form_data' => ['internshipTitle' => $title]]);
    }

    /** Float as ADMIN_B (so the sweep's baseline never includes ADMIN_A rows from fixtures). */
    private function float(Jnf $jnf, PlacementCycle $cycle, int $days = 5): JobPosting
    {
        Sanctum::actingAs($this->adminB);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays($days)->toIso8601String(),
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function applyAll(JobPosting $posting, array $students): array
    {
        $apps = [];
        foreach ($students as $s) {
            $apps[$s->id] = Application::create([
                'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $s->resumes()->first()->id,
                'status' => 'applied', 'applied_at' => now(),
            ]);
        }

        return $apps;
    }

    /** @return array<string, mixed> */
    private function world(): array
    {
        $company = Company::create(['name' => 'Alpha Systems', 'hr_name' => 'HR', 'hr_email' => 'hr@alpha.qa.test']);
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@alpha.qa.test']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $ft = PlacementCycle::create(['name' => 'QA FT', 'type' => 'fulltime'] + $base);
        $in = PlacementCycle::create(['name' => 'QA Intern', 'type' => 'internship'] + $base);

        $s = [];
        for ($i = 1; $i <= 6; $i++) {
            $s[$i] = $this->student($ft);
        }
        $outsider = $this->student();

        // P: closed (in_process) drive with every s1..s6 applied — pipeline routes.
        $p = $this->float($this->jnf($company, 'Pipeline Drive'), $ft);
        $pApps = $this->applyAll($p, array_values($s));
        $p->update(['status' => 'in_process']);
        [$pR1] = $p->rounds()->orderBy('sort_order')->get()->all();

        // Popen: open drive with two rounds — posting / round management routes.
        $open = $this->float($this->jnf($company, 'Open Drive'), $ft, 10);
        $openRounds = $open->rounds()->orderBy('sort_order')->pluck('id')->all();

        // Pfinal: single (final) round, closed — results/publish.
        $final = $this->float($this->jnf($company, 'Final Drive', 'accepted', ['hr_interview']), $ft);
        $finalApps = $this->applyAll($final, [$s[1], $s[2]]);
        $final->update(['status' => 'in_process']);

        $notFloated = $this->jnf($company, 'Unfloated JNF');
        $jSub = $this->jnf($company, 'Submitted JNF', 'submitted');
        $jDraft = $this->jnf($company, 'Draft JNF', 'draft');
        $iSub = $this->inf($company, 'Submitted INF', 'submitted');
        $iDraft = $this->inf($company, 'Draft INF', 'draft');

        $pendingResume = $s[1]->resumes()->create(['slot' => 2, 'label' => 'Data', 'file_path' => 'resumes/x2.pdf', 'file_size' => 10, 'status' => 'pending']);
        $event = CampusEvent::create(['title' => 'Draft PPT', 'event_type' => 'ppt', 'starts_at' => now()->addDays(3), 'audience_type' => 'all', 'created_by' => $this->adminB->id]);
        $branch = ProgrammeBranch::create(['programme_name' => self::BTECH, 'branch_name' => 'QA Custom Branch', 'is_custom' => true, 'is_active' => true, 'created_by' => $this->adminB->id]);
        $policy = PolicyDocument::create(['title' => 'QA Policy', 'type' => 'link', 'url' => 'https://example.com/policy.pdf', 'is_visible_jnf' => true, 'is_visible_inf' => true]);
        $branchChange = BranchChangeRequest::create(['student_profile_id' => $s[2]->id, 'current_branch' => 'Computer Science & Engineering', 'requested_branch' => 'Mining Engineering', 'reason' => 'Interest', 'status' => 'pending']);
        $proposal = ShortlistProposal::create(['job_posting_id' => $p->id, 'posting_round_id' => $pR1->id, 'proposed_by' => $companyUser->id, 'kind' => 'shortlist', 'payload' => [['roll_no' => $s[3]->roll_no]], 'status' => 'pending']);
        $block = PlacementBlock::create(['student_profile_id' => $s[4]->id, 'placement_cycle_id' => $ft->id, 'scope' => 'all', 'reason' => 'manual', 'remark' => 'QA', 'active' => true, 'blocked_by' => $this->adminB->id]);

        // Superset parity fixtures.
        $note = StudentNote::create(['student_profile_id' => $s[1]->id, 'author_id' => $this->adminB->id, 'body' => 'Called about the PPT']);
        Storage::disk('local')->put("posting-documents/{$open->id}/jd.pdf", '%PDF-1.4 qa');
        $document = PostingDocument::create(['job_posting_id' => $open->id, 'title' => 'JD', 'file_path' => "posting-documents/{$open->id}/jd.pdf", 'file_size' => 11, 'uploaded_by' => $this->adminB->id]);
        $category = StudentCategory::create(['title' => 'QA Sports Quota', 'description' => 'QA', 'created_by' => $this->adminB->id]);
        $template = ExportTemplate::create(['name' => 'QA Template', 'type' => 'STUDENT_LIST', 'columns' => [['key' => 'name', 'label' => 'Name']], 'created_by' => $this->adminB->id]);
        $notice = Notice::create(['title' => 'QA Draft Notice', 'body' => 'Bring your ID card.', 'created_by' => $this->adminB->id]);
        $notice->audiences()->create(['audience_type' => 'all', 'audience_filter' => null]);
        $survey = Survey::create(['title' => 'QA Draft Survey', 'status' => 'draft', 'created_by' => $this->adminB->id]);
        $survey->questions()->create(['qtype' => 'text', 'question' => 'Any feedback?', 'required' => false, 'sort_order' => 1]);
        $survey->audiences()->create(['audience_type' => 'all', 'audience_filter' => null]);

        return compact('company', 'companyUser', 'ft', 'in', 's', 'outsider', 'p', 'pApps', 'pR1', 'open', 'openRounds', 'final', 'finalApps',
            'notFloated', 'jSub', 'jDraft', 'iSub', 'iDraft', 'pendingResume', 'event', 'branch', 'policy', 'branchChange', 'proposal', 'block',
            'note', 'document', 'category', 'template', 'notice', 'survey');
    }

    /**
     * One valid call per admin mutating route. Each entry returns [method, uri, payload, multipart?] and may set up
     * extra state first (it runs inside a savepoint that is rolled back after the call).
     *
     * @return array<string, \Closure(): array>
     */
    private function plan(array $w): array
    {
        $s = $w['s'];
        $p = $w['p']->id;
        $r1 = $w['pR1']->id;
        $app = fn (int $i) => $w['pApps'][$s[$i]->id];
        $row = fn (int $i, string $result, bool $published) => ApplicationRoundResult::create([
            'application_id' => $app($i)->id, 'posting_round_id' => $r1, 'result' => $result, 'published_at' => $published ? now() : null,
        ]);
        $csv = fn (string $name, array $lines) => UploadedFile::fake()->createWithContent($name, implode("\n", $lines));
        $offer = fn () => Offer::create([
            'application_id' => $w['finalApps'][$s[2]->id]->id, 'student_profile_id' => $s[2]->id, 'company_id' => $w['company']->id,
            'job_posting_id' => $w['final']->id, 'placement_cycle_id' => $w['ft']->id, 'offer_type' => 'fulltime',
            'ctc_annual' => 1_200_000, 'currency' => 'INR', 'announced_by' => $this->adminB->id, 'announced_at' => now(),
        ]);
        $formData = json_encode(['jobTitle' => 'QA Wizard Role', 'internshipTitle' => 'QA Wizard Intern', 'graduatingBatch' => '2027',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false]]]],
            'selectionRounds' => [['id' => '1', 'type' => 'hr_interview', 'enabled' => true]]]);
        $jnfBody = ['job_title' => 'QA Wizard Role', 'job_description' => 'Build things.', 'form_data' => $formData, 'status' => 'submitted'];
        $infBody = ['internship_title' => 'QA Wizard Intern', 'internship_description' => 'Intern things.', 'form_data' => $formData, 'status' => 'submitted'];
        $cycleBody = [
            'name' => 'QA FT (renamed)', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30',
            'description' => 'Updated by QA', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ];

        return [
            // ---------------- Phase 2: cycles
            'POST api/admin/placement-cycles' => fn () => ['POST', '/api/admin/placement-cycles', ['name' => 'QA New Cycle', 'type' => 'internship', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]]],
            'PATCH api/admin/placement-cycles/{placementCycle}' => fn () => ['PATCH', "/api/admin/placement-cycles/{$w['ft']->id}", $cycleBody],
            'PATCH api/admin/placement-cycles/{placementCycle}/close' => fn () => ['PATCH', "/api/admin/placement-cycles/{$w['in']->id}/close", []],
            'POST api/admin/placement-cycles/{placementCycle}/enroll' => fn () => ['POST', "/api/admin/placement-cycles/{$w['ft']->id}/enroll", ['roll_nos' => [$w['outsider']->roll_no]]],
            'DELETE api/admin/placement-cycles/{placementCycle}/enroll/{studentProfile}' => function () use ($w) {
                CycleEnrollment::create(['placement_cycle_id' => $w['in']->id, 'student_profile_id' => $w['outsider']->id, 'status' => 'active']);

                return ['DELETE', "/api/admin/placement-cycles/{$w['in']->id}/enroll/{$w['outsider']->id}", []];
            },
            // ---------------- students
            'POST api/admin/students' => fn () => ['POST', '/api/admin/students', [
                'roll_no' => '26QA9002', 'full_name' => 'Aarav Sharma', 'institute_email' => '26qa9002@iitism.ac.in',
                'programme' => self::BTECH, 'branch' => 'Computer Science & Engineering', 'graduating_batch' => 2027, 'gender' => 'male',
                'current_cgpa' => 8.1, 'ongoing_backlogs' => 0, 'total_backlogs' => 0, 'tenth_percent' => 91, 'twelfth_percent' => 90,
            ]],
            'POST api/admin/students/import' => fn () => ['POST', '/api/admin/students/import', ['file' => $csv('students.csv', [
                implode(',', StudentAccountService::IMPORT_COLUMNS),
                '26QA9001,Import Student,26qa9001@iitism.ac.in,'.self::BTECH.',Mining Engineering,2027,female,7.5,0,0,88,87,2004-05-06,,,GEN,no,Bihar',
            ])], true],
            'POST api/admin/students/academics/import' => fn () => ['POST', '/api/admin/students/academics/import', ['file' => $csv('academics.csv', [
                'roll_no,current_cgpa,ongoing_backlogs,total_backlogs', "{$s[1]->roll_no},9.10,0,0",
            ])], true],
            'PATCH api/admin/students/{studentProfile}' => fn () => ['PATCH', "/api/admin/students/{$s[1]->id}", ['phone' => '9876500000']],
            'PATCH api/admin/students/{studentProfile}/suspend' => fn () => ['PATCH', "/api/admin/students/{$s[5]->id}/suspend", []],
            'PATCH api/admin/students/{studentProfile}/reactivate' => function () use ($s) {
                $s[6]->user->update(['is_active' => false]);

                return ['PATCH', "/api/admin/students/{$s[6]->id}/reactivate", []];
            },
            'POST api/admin/students/{studentProfile}/resend-invitation' => fn () => ['POST', "/api/admin/students/{$s[1]->id}/resend-invitation", []],
            // S5 invitations (domain rule B2-10 is why the student payloads above use @iitism.ac.in)
            'POST api/admin/students/{studentProfile}/revoke-invitation' => fn () => ['POST', "/api/admin/students/{$s[2]->id}/revoke-invitation", []],
            'POST api/admin/students/invitations/resend' => fn () => ['POST', '/api/admin/students/invitations/resend', ['student_ids' => [$s[1]->id]]],
            'POST api/admin/students/invitations/revoke' => fn () => ['POST', '/api/admin/students/invitations/revoke', ['student_ids' => [$s[3]->id]]],
            'PATCH api/admin/resumes/{resume}' => fn () => ['PATCH', "/api/admin/resumes/{$w['pendingResume']->id}", ['status' => 'approved']],
            // ---------------- postings
            'POST api/admin/postings' => fn () => ['POST', '/api/admin/postings', [
                'form_type' => 'jnf', 'form_id' => $w['notFloated']->id, 'placement_cycle_id' => $w['ft']->id,
                'application_deadline' => now()->addDays(7)->toIso8601String(), 'questions' => [['question' => 'Why us?', 'qtype' => 'text']],
            ]],
            'PATCH api/admin/postings/{jobPosting}' => fn () => ['PATCH', "/api/admin/postings/{$w['open']->id}", ['application_deadline' => now()->addDays(12)->toIso8601String(), 'share_contact_details' => true]],
            'PATCH api/admin/postings/{jobPosting}/eligibility' => fn () => ['PATCH', "/api/admin/postings/{$w['open']->id}/eligibility", ['minTenthPercent' => '60', 'notify_newly_eligible' => false]],
            'PATCH api/admin/postings/{jobPosting}/close' => fn () => ['PATCH', "/api/admin/postings/{$w['open']->id}/close", []],
            'PATCH api/admin/postings/{jobPosting}/reopen' => function () use ($w) {
                JobPosting::whereKey($w['open']->id)->update(['status' => 'in_process']);

                return ['PATCH', "/api/admin/postings/{$w['open']->id}/reopen", []];
            },
            'PATCH api/admin/postings/{jobPosting}/cancel' => fn () => ['PATCH', "/api/admin/postings/{$w['open']->id}/cancel", []],
            'POST api/admin/postings/{jobPosting}/rounds' => fn () => ['POST', "/api/admin/postings/{$w['open']->id}/rounds", ['name' => 'Medical', 'round_type' => 'medical']],
            'POST api/admin/postings/{jobPosting}/rounds/reorder' => fn () => ['POST', "/api/admin/postings/{$w['open']->id}/rounds/reorder", ['ordered_round_ids' => array_reverse($w['openRounds'])]],
            'PATCH api/admin/postings/{jobPosting}/rounds/{postingRound}' => fn () => ['PATCH', "/api/admin/postings/{$w['open']->id}/rounds/{$w['openRounds'][0]}", ['scheduled_at' => '2026-10-20T04:30:00Z']],
            'DELETE api/admin/postings/{jobPosting}/rounds/{postingRound}' => fn () => ['DELETE', "/api/admin/postings/{$w['open']->id}/rounds/{$w['openRounds'][0]}", []],
            // ---------------- pipeline
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/results' => fn () => ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/results", ['roll_nos' => [$s[1]->roll_no], 'result' => 'selected']],
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/attendance' => fn () => ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/attendance", ['roll_nos_present' => [$s[1]->roll_no, $s[2]->roll_no], 'roll_nos_absent' => [$s[3]->roll_no]]],
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/publish' => function () use ($row, $p, $r1) {
                $row(1, 'selected', false);

                return ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/publish", ['reject_remaining' => true]];
            },
            'DELETE api/admin/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}' => function () use ($row, $p, $r1, $app) {
                $row(2, 'waitlisted', true);

                return ['DELETE', "/api/admin/postings/{$p}/rounds/{$r1}/waitlist/{$app(2)->id}", []];
            },
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}/promote' => function () use ($row, $p, $r1, $app) {
                $row(2, 'waitlisted', true);

                return ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/waitlist/{$app(2)->id}/promote", []];
            },
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/addendum' => fn () => ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/addendum", ['roll_nos' => [$s[3]->roll_no], 'remark' => 'Late addition']],
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/readd/{application}' => function () use ($row, $p, $r1, $app) {
                $row(4, 'rejected', true);

                return ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/readd/{$app(4)->id}", ['confirm' => true, 'remark' => 'Scored wrongly']];
            },
            'DELETE api/admin/postings/{jobPosting}/rounds/{postingRound}/results/{application}' => function () use ($row, $p, $r1, $app) {
                $row(5, 'selected', false);

                return ['DELETE', "/api/admin/postings/{$p}/rounds/{$r1}/results/{$app(5)->id}", []];
            },
            'POST api/admin/postings/{jobPosting}/applications/{application}/remove-from-process' => function () use ($p, $app) {
                $app(6)->update(['placed_elsewhere_flag' => true]);

                return ['POST', "/api/admin/postings/{$p}/applications/{$app(6)->id}/remove-from-process", ['notify_company' => true]];
            },
            'POST api/admin/postings/{jobPosting}/results/publish' => fn () => ['POST', "/api/admin/postings/{$w['final']->id}/results/publish", ['selections' => [
                ['application_id' => $w['finalApps'][$s[1]->id]->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1_800_000, 'block' => true, 'block_scope' => 'all'],
            ]]],
            // ---------------- blocks
            'POST api/admin/blocks' => fn () => ['POST', '/api/admin/blocks', ['student_profile_id' => $s[1]->id, 'placement_cycle_id' => $w['ft']->id, 'scope' => 'all', 'reason' => 'manual', 'remark' => 'Missed PPT']],
            'DELETE api/admin/blocks/{placementBlock}' => fn () => ['DELETE', "/api/admin/blocks/{$w['block']->id}", []],
            // ---------------- events
            'POST api/admin/events' => fn () => ['POST', '/api/admin/events', ['title' => 'QA Workshop', 'event_type' => 'workshop', 'starts_at' => now()->addDays(4)->toIso8601String()]],
            'PUT api/admin/events/{campusEvent}' => fn () => ['PUT', "/api/admin/events/{$w['event']->id}", ['title' => 'Draft PPT (moved)', 'event_type' => 'ppt', 'starts_at' => now()->addDays(5)->toIso8601String(), 'venue' => 'NLHC']],
            'DELETE api/admin/events/{campusEvent}' => fn () => ['DELETE', "/api/admin/events/{$w['event']->id}", []],
            'POST api/admin/events/{campusEvent}/publish' => fn () => ['POST', "/api/admin/events/{$w['event']->id}/publish", []],
            // ---------------- proposals / settings / branch change
            'PATCH api/admin/proposals/{shortlistProposal}' => fn () => ['PATCH', "/api/admin/proposals/{$w['proposal']->id}", ['status' => 'approved']],
            'PATCH api/admin/settings' => fn () => ['PATCH', '/api/admin/settings', ['mail_mode' => 'sync']],
            'PATCH api/admin/branch-changes/{branchChangeRequest}' => fn () => ['PATCH', "/api/admin/branch-changes/{$w['branchChange']->id}", ['status' => 'approved']],

            // ---------------- Superset parity
            'PATCH api/admin/manage-admins/{user}' => fn () => ['PATCH', "/api/admin/manage-admins/{$this->adminB->id}", ['first_name' => 'Admin', 'last_name' => 'Gamma', 'designation' => 'TPO']],
            'PATCH api/admin/placement-cycles/{placementCycle}/publish' => function () use ($w) {
                $w['in']->update(['is_draft' => true]);

                return ['PATCH', "/api/admin/placement-cycles/{$w['in']->id}/publish", []];
            },
            'PATCH api/admin/placement-cycles/{placementCycle}/enrollments/{enrollment}' => function () use ($w, $s) {
                $enrollment = CycleEnrollment::where('placement_cycle_id', $w['ft']->id)->where('student_profile_id', $s[3]->id)->sole();

                return ['PATCH', "/api/admin/placement-cycles/{$w['ft']->id}/enrollments/{$enrollment->id}", ['status' => 'suspended']];
            },
            'POST api/admin/students/{studentProfile}/notes' => fn () => ['POST', "/api/admin/students/{$s[1]->id}/notes", ['body' => 'Wants a core role']],
            'DELETE api/admin/students/{studentProfile}/notes/{note}' => fn () => ['DELETE', "/api/admin/students/{$s[1]->id}/notes/{$w['note']->id}", []],
            'POST api/admin/students/{studentProfile}/resumes/verify-all' => fn () => ['POST', "/api/admin/students/{$s[1]->id}/resumes/verify-all", ['resumes' => [
                ['id' => $w['pendingResume']->id, 'expected_updated_at' => $w['pendingResume']->fresh()->updated_at->toIso8601String()],
            ]]],
            'POST api/admin/postings/{jobPosting}/open-now' => function () use ($w) {
                JobPosting::whereKey($w['open']->id)->update(['scheduled_open_at' => now()->addDay()]);

                return ['POST', "/api/admin/postings/{$w['open']->id}/open-now", []];
            },
            'POST api/admin/postings/{jobPosting}/documents' => fn () => ['POST', "/api/admin/postings/{$w['open']->id}/documents", ['title' => 'Brochure', 'file' => UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf')], true],
            'DELETE api/admin/postings/{jobPosting}/documents/{postingDocument}' => fn () => ['DELETE', "/api/admin/postings/{$w['open']->id}/documents/{$w['document']->id}", []],
            'POST api/admin/postings/{jobPosting}/send-applicant-list' => fn () => ['POST', "/api/admin/postings/{$p}/send-applicant-list", ['note' => 'Please confirm the test slots.']],
            'POST api/admin/student-categories' => fn () => ['POST', '/api/admin/student-categories', ['title' => 'QA PwD', 'description' => 'Persons with disability']],
            'PATCH api/admin/student-categories/{studentCategory}' => fn () => ['PATCH', "/api/admin/student-categories/{$w['category']->id}", ['title' => 'QA Sports Quota (renamed)']],
            'DELETE api/admin/student-categories/{studentCategory}' => fn () => ['DELETE', "/api/admin/student-categories/{$w['category']->id}", []],
            'POST api/admin/student-categories/{studentCategory}/students' => fn () => ['POST', "/api/admin/student-categories/{$w['category']->id}/students", ['roll_nos' => [$s[1]->roll_no]]],
            'DELETE api/admin/student-categories/{studentCategory}/students/{studentProfile}' => function () use ($w, $s) {
                $w['category']->students()->attach($s[2]->id, ['assigned_by' => $this->adminB->id]);

                return ['DELETE', "/api/admin/student-categories/{$w['category']->id}/students/{$s[2]->id}", []];
            },
            'POST api/admin/export-templates' => fn () => ['POST', '/api/admin/export-templates', ['name' => 'QA New Template']],
            'PATCH api/admin/export-templates/{exportTemplate}' => fn () => ['PATCH', "/api/admin/export-templates/{$w['template']->id}", ['name' => 'QA Template (renamed)']],
            'POST api/admin/export-templates/{exportTemplate}/duplicate' => fn () => ['POST', "/api/admin/export-templates/{$w['template']->id}/duplicate", []],
            'DELETE api/admin/export-templates/{exportTemplate}' => fn () => ['DELETE', "/api/admin/export-templates/{$w['template']->id}", []],
            'POST api/admin/postings/{jobPosting}/offers/ctc-upload' => function () use ($offer, $csv, $s, $w) {
                $offer();

                return ['POST', "/api/admin/postings/{$w['final']->id}/offers/ctc-upload", ['file' => $csv('ctcs.csv', ['roll_no,ctc,interval,currency', "{$s[2]->roll_no},1500000,YEAR,INR"])], true];
            },
            'PATCH api/admin/offers/{offer}' => fn () => ['PATCH', '/api/admin/offers/'.$offer()->id, ['ctc_annual' => 1_400_000, 'apply_blocking' => false]],
            'POST api/admin/offers/{offer}/revoke' => fn () => ['POST', '/api/admin/offers/'.$offer()->id.'/revoke', ['confirm' => true, 'remark' => 'Company withdrew the offer']],
            'POST api/admin/audiences/preview' => fn () => ['POST', '/api/admin/audiences/preview', ['kind' => 'notice', 'audiences' => [['audience_type' => 'all']]]],
            'POST api/admin/notices' => fn () => ['POST', '/api/admin/notices', ['title' => 'QA Notice', 'body' => 'PPT at 5 PM.', 'audiences' => [['audience_type' => 'all']]]],
            'PUT api/admin/notices/{notice}' => fn () => ['PUT', "/api/admin/notices/{$w['notice']->id}", ['title' => 'QA Draft Notice (edited)', 'body' => 'Bring two ID cards.', 'audiences' => [['audience_type' => 'all']]]],
            'DELETE api/admin/notices/{notice}' => fn () => ['DELETE', "/api/admin/notices/{$w['notice']->id}", []],
            'POST api/admin/notices/{notice}/publish' => fn () => ['POST', "/api/admin/notices/{$w['notice']->id}/publish", ['send_email' => false]],
            'POST api/admin/notices/{notice}/attachment' => fn () => ['POST', "/api/admin/notices/{$w['notice']->id}/attachment", ['file' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf')], true],
            'DELETE api/admin/notices/{notice}/attachment' => function () use ($w) {
                Storage::disk('local')->put("notices/{$w['notice']->id}/old.pdf", '%PDF-1.4 qa');
                $w['notice']->update(['attachment_path' => "notices/{$w['notice']->id}/old.pdf", 'attachment_name' => 'old.pdf', 'attachment_size' => 11]);

                return ['DELETE', "/api/admin/notices/{$w['notice']->id}/attachment", []];
            },
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/reconcile' => function () use ($s, $p, $r1, $app) {
                $s[3]->update(['current_cgpa' => 5.0]); // below the 6.0 cut-off → ineligible

                return ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/reconcile", ['application_ids' => [$app(3)->id], 'confirm' => true]];
            },
            'POST api/admin/postings/{jobPosting}/rounds/{postingRound}/email' => function () use ($row, $p, $r1) {
                $row(1, 'selected', true);

                return ['POST', "/api/admin/postings/{$p}/rounds/{$r1}/email", ['subject' => 'Interview venue', 'message' => 'Report to NLHC at 9 AM.', 'results' => ['selected']]];
            },
            'POST api/admin/surveys' => fn () => ['POST', '/api/admin/surveys', ['title' => 'QA New Survey']],
            'PUT api/admin/surveys/{survey}' => fn () => ['PUT', "/api/admin/surveys/{$w['survey']->id}", ['title' => 'QA Draft Survey (edited)', 'allow_edits' => true]],
            'DELETE api/admin/surveys/{survey}' => fn () => ['DELETE', "/api/admin/surveys/{$w['survey']->id}", []],
            'POST api/admin/surveys/{survey}/publish' => fn () => ['POST', "/api/admin/surveys/{$w['survey']->id}/publish", ['send_email' => false]],
            'POST api/admin/surveys/{survey}/clone' => fn () => ['POST', "/api/admin/surveys/{$w['survey']->id}/clone", []],
            'POST api/admin/settings/logo' => fn () => ['POST', '/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)], true],
            'DELETE api/admin/settings/logo' => function () {
                Storage::disk('local')->put('branding/qa-logo.png', 'png');
                app(SettingsService::class)->set('account_logo', ['path' => 'branding/qa-logo.png', 'mime' => 'image/png'], $this->adminB, 'settings.logo_update');

                return ['DELETE', '/api/admin/settings/logo', []];
            },
            'POST api/admin/form-builder/companies' => fn () => ['POST', '/api/admin/form-builder/companies', ['name' => 'Offline Co', 'hr_name' => 'Asha Rao', 'hr_email' => 'asha@offline.qa.test']],
            'POST api/admin/form-builder/{company}/jnfs/autosave' => fn () => ['POST', "/api/admin/form-builder/{$w['company']->id}/jnfs/autosave", ['job_title' => 'QA Wizard Role', 'job_description' => 'Build things.']],
            'POST api/admin/form-builder/{company}/jnfs' => fn () => ['POST', "/api/admin/form-builder/{$w['company']->id}/jnfs", $jnfBody],
            'PUT api/admin/form-builder/{company}/jnfs/{jnf}' => fn () => ['PUT', "/api/admin/form-builder/{$w['company']->id}/jnfs/{$w['jDraft']->id}", $jnfBody],
            'POST api/admin/form-builder/{company}/infs/autosave' => fn () => ['POST', "/api/admin/form-builder/{$w['company']->id}/infs/autosave", ['internship_title' => 'QA Wizard Intern', 'internship_description' => 'Intern things.']],
            'POST api/admin/form-builder/{company}/infs' => fn () => ['POST', "/api/admin/form-builder/{$w['company']->id}/infs", $infBody],
            'PUT api/admin/form-builder/{company}/infs/{inf}' => fn () => ['PUT', "/api/admin/form-builder/{$w['company']->id}/infs/{$w['iDraft']->id}", $infBody],

            // ---------------- Phase 1 routes
            'POST api/admin/manage-admins' => fn () => ['POST', '/api/admin/manage-admins', ['name' => 'QA New Admin', 'email' => 'qa.cdc.newadmin@gmail.com']],
            'DELETE api/admin/manage-admins/{user}' => fn () => ['DELETE', "/api/admin/manage-admins/{$this->adminB->id}", []],
            'POST api/admin/programme-branches' => fn () => ['POST', '/api/admin/programme-branches', ['programme_name' => self::BTECH, 'branch_name' => 'QA Another Branch']],
            'PATCH api/admin/programme-branches/status' => fn () => ['PATCH', '/api/admin/programme-branches/status', ['programme_name' => self::BTECH, 'branch_name' => 'Petroleum Engineering', 'is_active' => false]],
            'DELETE api/admin/programme-branches/{programmeBranch}' => fn () => ['DELETE', "/api/admin/programme-branches/{$w['branch']->id}", []],
            'PATCH api/admin/jnfs/{jnf}/status' => fn () => ['PATCH', "/api/admin/jnfs/{$w['jSub']->id}/status", ['status' => 'under_review', 'admin_remarks' => 'Fix the CTC breakup']],
            'PATCH api/admin/jnfs/{jnf}/remarks/latest' => function () use ($w) {
                $w['jSub']->update(['status' => 'under_review']);
                FormStatusHistory::create(['form_type' => Jnf::class, 'form_id' => $w['jSub']->id, 'old_status' => 'submitted', 'new_status' => 'under_review', 'changed_by' => $this->adminA->id, 'remarks' => 'Old remark']);

                return ['PATCH', "/api/admin/jnfs/{$w['jSub']->id}/remarks/latest", ['remark' => 'Corrected remark']];
            },
            'POST api/admin/jnfs/{jnf}/notes' => fn () => ['POST', "/api/admin/jnfs/{$w['jDraft']->id}/notes", ['note' => 'Call the recruiter']],
            'PATCH api/admin/jnfs/{jnf}/form-data' => fn () => ['PATCH', "/api/admin/jnfs/{$w['jSub']->id}/form-data", ['form_data' => array_merge($w['jSub']->form_data, ['jobTitle' => 'Submitted JNF (edited)'])]],
            'PATCH api/admin/infs/{inf}/status' => fn () => ['PATCH', "/api/admin/infs/{$w['iSub']->id}/status", ['status' => 'under_review', 'admin_remarks' => 'Fix the stipend']],
            'PATCH api/admin/infs/{inf}/remarks/latest' => function () use ($w) {
                $w['iSub']->update(['status' => 'under_review']);
                FormStatusHistory::create(['form_type' => Inf::class, 'form_id' => $w['iSub']->id, 'old_status' => 'submitted', 'new_status' => 'under_review', 'changed_by' => $this->adminA->id, 'remarks' => 'Old remark']);

                return ['PATCH', "/api/admin/infs/{$w['iSub']->id}/remarks/latest", ['remark' => 'Corrected remark']];
            },
            'POST api/admin/infs/{inf}/notes' => fn () => ['POST', "/api/admin/infs/{$w['iDraft']->id}/notes", ['note' => 'Call the recruiter']],
            'PATCH api/admin/infs/{inf}/form-data' => fn () => ['PATCH', "/api/admin/infs/{$w['iSub']->id}/form-data", ['form_data' => array_merge($w['iSub']->form_data, ['internshipTitle' => 'Submitted INF (edited)'])]],
            'PUT api/admin/companies/{company}' => fn () => ['PUT', "/api/admin/companies/{$w['company']->id}", ['name' => 'Alpha Systems Pvt Ltd', 'hr_name' => 'Priya Sharma', 'hr_email' => 'qa.cdc.hr.alpha@gmail.com']],
            'POST api/admin/policy-documents' => fn () => ['POST', '/api/admin/policy-documents', ['title' => 'QA Policy 2', 'type' => 'link', 'url' => 'https://example.com/policy2.pdf']],
            'PUT|PATCH api/admin/policy-documents/{policy_document}' => fn () => ['PUT', "/api/admin/policy-documents/{$w['policy']->id}", ['title' => 'QA Policy (v2)', 'type' => 'link', 'url' => 'https://example.com/policy-v2.pdf']],
            'DELETE api/admin/policy-documents/{policy_document}' => fn () => ['DELETE', "/api/admin/policy-documents/{$w['policy']->id}", []],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Route> */
    private function adminMutatingRoutes()
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('role:admin', $r->gatherMiddleware(), true)
                && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->values();
    }

    private static function key(Route $route): string
    {
        return implode('|', array_values(array_diff($route->methods(), ['HEAD']))).' '.$route->uri();
    }

    // ------------------------------------------------------------------ TA.1

    public function test_TA_1_every_admin_mutating_route_writes_an_attributed_audit_row(): void
    {
        $w = $this->world();
        $plan = $this->plan($w);
        $routes = $this->adminMutatingRoutes();
        $this->assertGreaterThanOrEqual(50, $routes->count());

        $results = [];
        foreach ($routes as $route) {
            $key = self::key($route);
            $phase1 = in_array($key, self::PHASE1_ROUTES, true);
            $exempt = self::NO_AUDIT_ROUTES[$key] ?? null;
            if (! isset($plan[$key])) {
                $results[] = ['key' => $key, 'phase1' => $phase1, 'exempt' => $exempt, 'status' => null, 'actions' => [], 'others' => [], 'before' => false, 'after' => false, 'ip' => false, 'note' => 'NO PLAN (new route — extend the sweep)'];

                continue;
            }

            DB::beginTransaction();
            try {
                $spec = $plan[$key]();
                [$method, $uri, $payload] = $spec;
                $multipart = $spec[3] ?? false;
                Sanctum::actingAs($this->adminA);
                $baseline = (int) AuditLog::max('id');
                $response = $multipart
                    ? $this->post($uri, $payload, ['Accept' => 'application/json'])
                    : $this->json($method, $uri, $payload);
                $logs = AuditLog::where('id', '>', $baseline)->orderBy('id')->get();
                $mine = $logs->where('user_id', $this->adminA->id);
                $results[] = [
                    'key' => $key, 'phase1' => $phase1, 'exempt' => $exempt, 'status' => $response->status(),
                    'actions' => $mine->pluck('action')->unique()->values()->all(),
                    'others' => $logs->where('user_id', '!==', $this->adminA->id)->pluck('action')->all(),
                    'before' => $mine->contains(fn ($l) => $l->before !== null),
                    'after' => $mine->contains(fn ($l) => $l->after !== null),
                    'ip' => $mine->isNotEmpty() && $mine->every(fn ($l) => $l->ip !== null && $l->actor_name === 'Admin Alpha'),
                    'note' => $response->status() >= 300 ? substr((string) $response->json('message'), 0, 120) : '',
                ];
            } finally {
                DB::rollBack();
                Cache::flush();
                $this->app['auth']->forgetGuards();
            }
        }

        $this->writeEvidence($results);

        $problems = [];
        foreach ($results as $r) {
            if ($r['status'] === null) {
                $problems[] = "{$r['key']}: no payload plan";
            } elseif ($r['status'] < 200 || $r['status'] >= 300) {
                $problems[] = "{$r['key']}: valid call returned {$r['status']} ({$r['note']})";
            } elseif ($r['exempt'] !== null) {
                continue; // writes no audit row by design (NO_AUDIT_ROUTES); the call itself must still succeed
            } elseif (! $r['phase1'] && $r['actions'] === []) {
                $problems[] = "{$r['key']}: MISSING audit row";
            } elseif (! $r['phase1']) {
                foreach ($r['actions'] as $action) {
                    if (! preg_match('/^[a-z_]+\.[a-z_]+$/', $action)) {
                        $problems[] = "{$r['key']}: non-meaningful action '{$action}'";
                    }
                }
                if (! $r['ip']) {
                    $problems[] = "{$r['key']}: audit row lacks ip/actor_name";
                }
                $verb = explode(' ', $r['key'])[0];
                if (str_contains($verb, 'PUT') || str_contains($verb, 'PATCH')) {
                    if (! $r['before'] || ! $r['after']) {
                        $problems[] = "{$r['key']}: update without before AND after";
                    }
                } elseif ($verb === 'DELETE' && ! $r['before']) {
                    $problems[] = "{$r['key']}: delete without before";
                } elseif ($verb === 'POST' && ! $r['after'] && ! $r['before']) {
                    $problems[] = "{$r['key']}: no before/after state";
                }
            }
        }

        $this->assertSame([], $problems, "Phase 2 admin audit coverage problems:\n".implode("\n", $problems));
    }

    /**
     * Phase 1 admin routes predate the audit requirement (spec A1.6 says "every admin write action"); the owner decided
     * they must be logged too (QA F-012, decision 4). The evidence table is written by TA.1.
     */
    public function test_TA_1b_phase1_admin_mutating_routes_are_audited(): void
    {
        $w = $this->world();
        $plan = $this->plan($w);
        $missing = [];
        foreach ($this->adminMutatingRoutes() as $route) {
            $key = self::key($route);
            if (! in_array($key, self::PHASE1_ROUTES, true)) {
                continue;
            }
            DB::beginTransaction();
            try {
                $spec = $plan[$key]();
                Sanctum::actingAs($this->adminA);
                $baseline = (int) AuditLog::max('id');
                $status = (($spec[3] ?? false) ? $this->post($spec[1], $spec[2], ['Accept' => 'application/json']) : $this->json($spec[0], $spec[1], $spec[2]))->status();
                $this->assertTrue($status >= 200 && $status < 300, "{$key} valid call returned {$status}");
                if (! AuditLog::where('id', '>', $baseline)->where('user_id', $this->adminA->id)->exists()) {
                    $missing[] = $key;
                }
            } finally {
                DB::rollBack();
                Cache::flush();
                $this->app['auth']->forgetGuards();
            }
        }

        $this->assertSame([], $missing, 'Phase 1 admin routes without audit rows (QA F-012)');
    }

    private function writeEvidence(array $results): void
    {
        $phase2 = array_values(array_filter($results, fn ($r) => ! $r['phase1']));
        $phase1 = array_values(array_filter($results, fn ($r) => $r['phase1']));
        $audited = fn (array $rows) => count(array_filter($rows, fn ($r) => $r['actions'] !== []));

        $lines = [
            '# Audit coverage sweep (TA.1)',
            '',
            'Generated by `tests/Feature/QA/SAAuditCoverageTest.php::test_TA_1_every_admin_mutating_route_writes_an_attributed_audit_row` on '.now()->toDateTimeString().' UTC (in-memory SQLite).',
            'Every route carrying `role:admin` with POST/PUT/PATCH/DELETE was called ONCE as ADMIN_A with a valid payload inside a rolled-back savepoint; `audit_logs` rows written by that call with `user_id = ADMIN_A` are listed.',
            '',
            sprintf('- Admin mutating routes: **%d** (Phase 2: %d, Phase 1: %d)', count($results), count($phase2), count($phase1)),
            sprintf('- Phase 2 routes audited: **%d / %d**', $audited($phase2), count($phase2)),
            sprintf('- Phase 1 routes audited: **%d / %d** (predate the audit requirement — owner to judge)', $audited($phase1), count($phase1)),
            '',
            '| # | Route | Phase | HTTP | Audit action(s) by ADMIN_A | before | after | ip+actor | Result |',
            '|---|---|---|---|---|---|---|---|---|',
        ];
        foreach ($results as $i => $r) {
            $ok = $r['status'] !== null && $r['status'] >= 200 && $r['status'] < 300;
            $result = ! $ok ? 'CALL FAILED '.($r['note'] ?? '') : ($r['actions'] !== [] ? 'AUDITED' : 'MISSING');
            if ($r['phase1'] && $result === 'MISSING') {
                $result = 'MISSING (Phase 1 route)';
            }
            if ($ok && $r['exempt'] !== null && $r['actions'] === []) {
                $result = 'EXEMPT (no audit by design: '.$r['exempt'].')';
            }
            $lines[] = sprintf(
                '| %d | `%s` | %s | %s | %s | %s | %s | %s | %s |',
                $i + 1, $r['key'], $r['phase1'] ? 'Phase 1 route' : 'Phase 2', $r['status'] ?? '-',
                $r['actions'] ? '`'.implode('`, `', $r['actions']).'`' : '—',
                $r['before'] ? 'yes' : '—', $r['after'] ? 'yes' : '—', $r['ip'] ? 'yes' : '—', $result
            );
        }
        $missing = array_filter($results, fn ($r) => $r['status'] !== null && $r['actions'] === [] && $r['exempt'] === null);
        $lines[] = '';
        $lines[] = '## MISSING';
        foreach ($missing as $r) {
            $lines[] = '- `'.$r['key'].'`'.($r['phase1'] ? ' — Phase 1 route' : ' — **Phase 2 finding**');
        }
        if ($missing === []) {
            $lines[] = '- none';
        }
        $lines[] = '';

        $dir = base_path('../qa/evidence');
        if (is_dir($dir)) {
            file_put_contents($dir.'/audit_coverage.md', implode("\n", $lines));
        }
    }

    // ------------------------------------------------------------------ TA.2

    public function test_TA_2_two_admins_are_distinguished_and_audit_log_filters_work(): void
    {
        Sanctum::actingAs($this->adminA);
        $this->postJson('/api/admin/events', ['title' => 'By A', 'event_type' => 'ppt', 'starts_at' => now()->addDays(2)->toIso8601String()])->assertCreated();
        Sanctum::actingAs($this->adminB);
        $eventB = $this->postJson('/api/admin/events', ['title' => 'By B', 'event_type' => 'ppt', 'starts_at' => now()->addDays(2)->toIso8601String()])->assertCreated()->json('event.id');
        $this->putJson("/api/admin/events/{$eventB}", ['title' => 'By B (edited)', 'event_type' => 'ppt', 'starts_at' => now()->addDays(3)->toIso8601String()])->assertOk();

        $rowA = AuditLog::where('action', 'event.create')->where('user_id', $this->adminA->id)->sole();
        $rowB = AuditLog::where('action', 'event.create')->where('user_id', $this->adminB->id)->sole();
        $rowBUpdate = AuditLog::where('action', 'event.update')->sole();
        // Spread over IST days: 2026-09-29T10:00Z = 29 Sep IST; 2026-09-29T20:00Z = 30 Sep 01:30 IST.
        DB::table('audit_logs')->where('id', $rowA->id)->update(['created_at' => '2026-09-29 10:00:00']);
        DB::table('audit_logs')->where('id', $rowB->id)->update(['created_at' => '2026-09-29 20:00:00']);
        DB::table('audit_logs')->where('id', $rowBUpdate->id)->update(['created_at' => '2026-10-01 05:00:00']);

        Sanctum::actingAs($this->adminA);
        $all = collect($this->getJson('/api/admin/audit-logs')->assertOk()->json('audit_logs'));
        $a = $all->firstWhere('id', $rowA->id);
        $b = $all->firstWhere('id', $rowB->id);
        $this->assertSame('event.create', $a['action']);
        $this->assertSame($a['action'], $b['action']);
        $this->assertSame('Admin Alpha', $a['user']['name']);
        $this->assertSame('Admin Beta', $b['user']['name']);
        $this->assertSame('qa-admin-b@cdc.qa.test', $b['user']['email']);
        $this->assertSame('Admin Beta', $b['actor_name']);
        foreach ([$a, $b] as $item) {
            $this->assertArrayHasKey('before', $item);
            $this->assertArrayHasKey('after', $item);
            $this->assertNotEmpty($item['after']);
        }
        $update = $all->firstWhere('id', $rowBUpdate->id);
        $this->assertSame('By B', $update['before']['title']);
        $this->assertSame('By B (edited)', $update['after']['title']);

        // Filters.
        $byB = collect($this->getJson("/api/admin/audit-logs?user_id={$this->adminB->id}")->json('audit_logs'));
        $this->assertEqualsCanonicalizing([$rowB->id, $rowBUpdate->id], $byB->pluck('id')->all());
        $byAction = collect($this->getJson('/api/admin/audit-logs?action=event.create')->json('audit_logs'));
        $this->assertEqualsCanonicalizing([$rowA->id, $rowB->id], $byAction->pluck('id')->all());
        $combined = collect($this->getJson("/api/admin/audit-logs?action=event.create&user_id={$this->adminA->id}")->json('audit_logs'));
        $this->assertSame([$rowA->id], $combined->pluck('id')->all());
        $day30 = collect($this->getJson('/api/admin/audit-logs?from=2026-09-30&to=2026-09-30')->json('audit_logs'));
        $this->assertSame([$rowB->id], $day30->pluck('id')->all(), 'IST date range: 20:00Z on 29 Sep belongs to 30 Sep IST');
        $range = collect($this->getJson('/api/admin/audit-logs?from=2026-09-29&to=2026-09-30')->json('audit_logs'));
        $this->assertEqualsCanonicalizing([$rowA->id, $rowB->id], $range->pluck('id')->all());

        $meta = $this->getJson('/api/admin/audit-logs')->json();
        $this->assertArrayHasKey('meta', $meta);
        $this->assertEqualsCanonicalizing([$this->adminA->id, $this->adminB->id], array_column($meta['admins'], 'id'));
    }

    // ------------------------------------------------------------------ TA.3

    public function test_TA_3_audit_log_is_append_only(): void
    {
        // No route mutates audit logs.
        $writers = collect(app('router')->getRoutes()->getRoutes())->filter(function (Route $r) {
            $touchesAudit = str_contains($r->uri(), 'audit') || str_contains((string) $r->getActionName(), 'AuditLog');

            return $touchesAudit && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
        });
        $this->assertCount(0, $writers, 'routes that can write audit logs: '.$writers->map(fn ($r) => self::key($r))->implode(', '));

        // No application code updates or deletes audit rows.
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (preg_match('/AuditLog::(?:destroy|truncate)\b|AuditLog::[^;]*->(?:update|delete|forceDelete|truncate|increment|decrement|save)\(|table\([\'"]audit_logs[\'"]\)[^;]*->(?:update|delete|truncate)\(/s', $code)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
        $this->assertSame([], $offenders, 'code paths that modify audit_logs');

        // Deleting an admin (Phase 1 hard delete) keeps their audit rows and who did them.
        Sanctum::actingAs($this->adminB);
        $this->postJson('/api/admin/events', ['title' => 'By B', 'event_type' => 'ppt', 'starts_at' => now()->addDay()->toIso8601String()])->assertCreated();
        $row = AuditLog::where('user_id', $this->adminB->id)->sole();
        Sanctum::actingAs($this->adminA);
        $this->deleteJson("/api/admin/manage-admins/{$this->adminB->id}")->assertOk();
        $row->refresh();
        $this->assertNull($row->user_id);
        $this->assertSame('Admin Beta', $row->actor_name);
        $this->assertSame('qa-admin-b@cdc.qa.test', $row->actor_email);
        $this->assertSame(1, AuditLog::where('action', 'event.create')->count());
    }

    // ------------------------------------------------------------------ TA.4

    public function test_TA_4_admin_can_do_everything_a_company_can_propose_and_override_any_record(): void
    {
        $company = Company::create(['name' => 'Alpha Systems', 'hr_name' => 'HR', 'hr_email' => 'hr@alpha.qa.test']);
        User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@alpha.qa.test']);
        $ft = PlacementCycle::create(['name' => 'QA FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]]);
        $s = [];
        for ($i = 1; $i <= 6; $i++) {
            $s[$i] = $this->student($ft);
        }
        $posting = $this->float($this->jnf($company, 'God Mode Drive', 'accepted', ['aptitude_test', 'technical_interview', 'hr_interview']), $ft);
        $apps = $this->applyAll($posting, array_values($s));
        [$r1, $r2] = $posting->rounds()->orderBy('sort_order')->get()->all();
        $base = "/api/admin/postings/{$posting->id}";
        Sanctum::actingAs($this->adminA);

        // ---- edit a posting after float: deadline, questions, contact sharing
        $this->patchJson($base, [
            'application_deadline' => now()->addDays(9)->toIso8601String(),
            'share_contact_details' => true,
            'questions' => [['question' => 'Relocate?', 'qtype' => 'mcq_single', 'options' => ['Yes', 'No'], 'required' => true]],
        ])->assertOk()->assertJsonPath('posting.share_contact_details', true);
        $posting->refresh();
        $this->assertTrue($posting->application_deadline->greaterThan(now()->addDays(8)));
        $this->assertSame(['Relocate?'], $posting->questions()->pluck('question')->all());
        $this->patchJson($base, ['share_contact_details' => false])->assertOk();
        $this->assertFalse($posting->fresh()->share_contact_details);
        $log = AuditLog::where('action', 'posting.update')->where('user_id', $this->adminA->id)->latest('id')->first();
        $this->assertNotNull($log->before);
        $this->assertNotNull($log->after);

        // ---- shortlist + waitlist directly (company kinds: shortlist / waitlist)
        $this->patchJson("{$base}/close")->assertOk();
        $this->postJson("{$base}/rounds/{$r1->id}/results", ['entries' => [
            ['roll_no' => $s[1]->roll_no, 'result' => 'selected'],
            ['roll_no' => $s[2]->roll_no, 'result' => 'waitlisted'],
            ['roll_no' => $s[3]->roll_no, 'result' => 'selected'],
            ['roll_no' => $s[5]->roll_no, 'result' => 'rejected'],
        ]])->assertOk()->assertJsonPath('written', 4);
        $this->postJson("{$base}/rounds/{$r1->id}/publish")->assertOk();
        $this->assertSame('waitlisted', ApplicationRoundResult::where('application_id', $apps[$s[2]->id]->id)->sole()->result);

        // ---- move any waitlisted candidate on (no ranking, D90; promotion within the round, QA F-002)
        $this->postJson("{$base}/rounds/{$r1->id}/waitlist/{$apps[$s[2]->id]->id}/promote")->assertOk();
        $this->assertSame('selected', ApplicationRoundResult::where('application_id', $apps[$s[2]->id]->id)->sole()->result);

        // ---- addendum (company kinds: addendum / replacement_request)
        $this->postJson("{$base}/rounds/{$r1->id}/addendum", ['roll_nos' => [$s[4]->roll_no], 'remark' => 'Replacement'])->assertOk();
        $this->assertTrue(ApplicationRoundResult::where('application_id', $apps[$s[4]->id]->id)->sole()->is_addendum);
        // ---- re-add a published rejection (admin-only protocol)
        $this->postJson("{$base}/rounds/{$r1->id}/readd/{$apps[$s[5]->id]->id}", ['confirm' => true, 'remark' => 'Recount'])->assertOk();
        $this->postJson("{$base}/rounds/{$r1->id}/publish")->assertOk()->assertJsonPath('counts.selected', 2);
        $this->assertNotNull(ApplicationRoundResult::where('application_id', $apps[$s[5]->id]->id)->sole()->published_at);

        // ---- edit ANY student field, incl. admin-controlled academics
        $this->patchJson("/api/admin/students/{$s[6]->id}", [
            'roll_no' => '26AU9999', 'full_name' => 'Renamed Student', 'institute_email' => '26au9999@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => 'Mining Engineering', 'graduating_batch' => 2028, 'gender' => 'female',
            'current_cgpa' => 6.42, 'ongoing_backlogs' => 1, 'total_backlogs' => 2, 'tenth_percent' => 70, 'twelfth_percent' => 71,
            'date_of_birth' => '2004-01-02', 'category' => 'SC', 'pwd' => true, 'home_state' => 'Jharkhand', 'phone' => '9000011111',
            'personal_email' => 'renamed@qa.test', 'linkedin_url' => 'https://linkedin.com/in/qa', 'github_url' => 'https://github.com/qa',
        ])->assertOk();
        $fresh = $s[6]->fresh();
        $this->assertSame('26AU9999', $fresh->roll_no);
        $this->assertSame('Mining Engineering', $fresh->branch);
        $this->assertEquals(6.42, (float) $fresh->current_cgpa);
        $this->assertSame(2028, (int) $fresh->graduating_batch);
        $this->assertSame('26au9999@iitism.ac.in', $fresh->user->email, 'login email follows the institute email');
        $edit = AuditLog::where('action', 'student.update')->where('subject_id', $s[6]->id)->sole();
        $this->assertSame('Computer Science & Engineering', $edit->before['branch']);
        $this->assertSame('Mining Engineering', $edit->after['branch']);

        // ---- block + unblock restores eligibility
        $other = $this->float($this->jnf($company, 'Other Drive'), $ft);
        Sanctum::actingAs($this->adminA);
        $this->postJson('/api/admin/blocks', ['student_profile_id' => $s[1]->id, 'placement_cycle_id' => $ft->id, 'scope' => 'all', 'reason' => 'debarred', 'remark' => 'Misconduct'])->assertCreated();
        $this->assertFalse(app(EligibilityService::class)->check($s[1]->fresh(), $other)['eligible']);
        $this->deleteJson('/api/admin/blocks/'.PlacementBlock::sole()->id)->assertOk();
        $this->assertTrue(app(EligibilityService::class)->check($s[1]->fresh(), $other)['eligible']);

        // ---- deadline can still be extended after it passed (admin is god); questions freeze at the deadline (spec B7)
        $this->travel(10)->days();
        $this->patchJson("/api/admin/postings/{$other->id}", ['questions' => [['question' => 'Late?', 'qtype' => 'text']]])->assertStatus(422);
        $this->patchJson("/api/admin/postings/{$other->id}", ['application_deadline' => now()->addDays(2)->toIso8601String()])->assertOk();
        $this->assertTrue($other->fresh()->application_deadline->isFuture());
    }
}
