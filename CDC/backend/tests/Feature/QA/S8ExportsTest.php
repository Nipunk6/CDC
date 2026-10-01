<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * QA Section 8 — Exports (spec B5, B7 "Exports", Q8.x, Q10.2, M9; D78, D87, D88).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S8ExportsTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const EVIL = '=HYPERLINK("http://evil.qa.test","x")';

    /** Phase 1 JNF CSV header, verbatim from `git show df7034b:CDC/backend/app/Http/Controllers/AdminFormReviewController.php`. */
    private const PHASE1_JNF_HEADERS = [
        'jnf_id', 'status', 'company_name', 'company_hr_name', 'company_hr_email', 'profile_company_name', 'job_title',
        'job_designation', 'job_location', 'work_mode', 'expected_hires', 'minimum_hires', 'joining_month', 'skills',
        'registration_link', 'job_description', 'min_cgpa', 'backlogs_allowed', 'gender_filter', 'slp_requirement',
        'graduating_batch', 'eligible_branches', 'ctc_min', 'ctc_max', 'programme_salaries_breakup', 'joining_bonus',
        'retention_bonus', 'performance_bonus', 'esops_stock_options', 'vesting_period', 'stocks_rsus',
        'relocation_allowance', 'medical_allowance', 'deductions', 'bond_amount', 'bond_duration', 'detailed_ctc_breakup',
        'selection_rounds', 'admin_remarks', 'form_submitted_at', 'form_accepted_at',
    ];

    /** Phase 1 INF CSV header (df7034b). */
    private const PHASE1_INF_HEADERS = [
        'inf_id', 'status', 'company_name', 'company_hr_name', 'company_hr_email', 'profile_company_name',
        'internship_title', 'internship_designation', 'internship_location', 'work_mode', 'expected_hires',
        'duration_weeks', 'joining_month', 'skills', 'registration_link', 'internship_description', 'min_cgpa',
        'backlogs_allowed', 'gender_filter', 'slp_requirement', 'graduating_batch', 'eligible_branches', 'stipend',
        'programme_stipends_breakup', 'ppo_provision', 'ppo_ctc', 'selection_rounds', 'admin_remarks',
        'form_submitted_at', 'form_accepted_at',
    ];

    /** Q10.2 company column set (contact columns only when shared). */
    private const COMPANY_BASE_COLUMNS = ['Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'CGPA', 'Ongoing Backlogs', 'Total Backlogs', '10th %', '12th %', 'Resume Link'];

    private User $admin;

    private Company $companyA;

    private Company $companyB;

    private User $companyUserA;

    private User $companyUserB;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    private StudentProfile $alice;   // live applicant, unverified resume, placed-elsewhere flag, formula-ish data

    private StudentProfile $bob;     // live applicant, approved resume

    private StudentProfile $wanda;   // withdrawn applicant

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->companyA = Company::create(['name' => 'Alpha Systems', 'hr_name' => 'HR', 'hr_email' => 'hr@alpha.qa.test']);
        $this->companyB = Company::create(['name' => 'Beta Labs', 'hr_name' => 'HR', 'hr_email' => 'hr@beta.qa.test']);
        $this->companyUserA = User::factory()->create(['role' => 'company', 'company_id' => $this->companyA->id]);
        $this->companyUserB = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id]);

        $this->cycle = PlacementCycle::create([
            'name' => 'QA FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
        $this->posting = $this->float($this->companyA, 'QA SDE', [
            ['question' => 'Why us?', 'qtype' => 'text'],
            ['question' => 'Languages', 'qtype' => 'mcq_multi', 'options' => ['Go', 'Rust']],
        ]);
        [$q1, $q2] = $this->posting->questions()->orderBy('sort_order')->get()->all();

        $this->alice = $this->student('26EX0001', [
            'full_name' => self::EVIL,
            'home_state' => '+cmd|\' /C calc\'!A0',
            'phone' => '+919876543210',
            'personal_email' => 'alice.personal@qa.test',
            'category' => 'OBC-NCL-QAMARK',
            'pwd' => true,
            'date_of_birth' => '2004-05-06',
            'linkedin_url' => '@SUM(1+1)',
            'gender' => 'female',
        ]);
        $this->bob = $this->student('26EX0002', ['full_name' => 'Bob Kumar', 'phone' => '9000000002', 'personal_email' => 'bob.personal@qa.test']);
        $this->wanda = $this->student('26EX0003', ['full_name' => 'Wanda Withdrawn', 'phone' => '9000000003']);

        $this->applyTo($this->posting, $this->alice, 'pending', [
            ['question_id' => $q1->id, 'answer' => self::EVIL],
            ['question_id' => $q2->id, 'answer' => ['Go', 'Rust']],
        ], ['placed_elsewhere_flag' => true, 'used_unverified_resume' => true]);
        $this->applyTo($this->posting, $this->bob, 'approved', [['question_id' => $q1->id, 'answer' => '-2+3']]);
        $this->applyTo($this->posting, $this->wanda, 'approved', [], ['status' => 'withdrawn', 'withdrawn_at' => now()]);
    }

    // ------------------------------------------------------------------ helpers

    private function float(Company $company, string $title, array $questions = []): JobPosting
    {
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => ['jobTitle' => $title, 'selectionRounds' => [
                ['type' => 'aptitude_test', 'enabled' => true],
                ['type' => 'hr_interview', 'enabled' => true],
            ]],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'questions' => $questions,
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function student(string $roll, array $attributes = []): StudentProfile
    {
        $email = strtolower($roll).'@students.qa.test';
        $student = StudentProfile::factory()->create(array_merge([
            'roll_no' => $roll,
            'institute_email' => $email,
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email]),
        ], $attributes));
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student;
    }

    private function applyTo(JobPosting $posting, StudentProfile $student, string $resumeStatus, array $answers = [], array $extra = []): Application
    {
        $path = "resumes/{$student->roll_no}/1_qa.pdf";
        Storage::disk('local')->put($path, "%PDF-1.4\n% QA resume for {$student->roll_no}\n%%EOF");
        $resume = Resume::firstOrCreate(
            ['student_profile_id' => $student->id, 'slot' => 1],
            ['label' => 'Main CV', 'file_path' => $path, 'file_size' => 40, 'status' => $resumeStatus]
        );

        return Application::create(array_merge([
            'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(), 'answers' => $answers,
            'used_unverified_resume' => $resumeStatus !== 'approved',
        ], $extra));
    }

    private function appOf(StudentProfile $student): Application
    {
        return Application::where('job_posting_id', $this->posting->id)->where('student_profile_id', $student->id)->sole();
    }

    private function load(string $bytes): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'qa-xlsx');
        file_put_contents($path, $bytes);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /** @return array{0: list<string>, 1: list<array<string, mixed>>, 2: Worksheet} header, rows keyed by header, sheet */
    private function table(string $bytes): array
    {
        $sheet = $this->load($bytes);
        $raw = $sheet->toArray(null, false, false, false);
        $header = array_map('strval', array_shift($raw));
        $rows = array_map(fn ($r) => array_combine($header, array_slice(array_pad($r, count($header), null), 0, count($header))), $raw);

        return [$header, $rows, $sheet];
    }

    private function adminExport(): array
    {
        Sanctum::actingAs($this->admin);

        return $this->table($this->get("/api/admin/postings/{$this->posting->id}/export")->assertOk()->streamedContent());
    }

    private function companyExport(?User $as = null): array
    {
        Sanctum::actingAs($as ?? $this->companyUserA);

        return $this->table($this->get("/api/company/postings/{$this->posting->id}/export")->assertOk()->streamedContent());
    }

    private function closeApplications(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    // ------------------------------------------------------------------ T8.1a

    public function test_T8_1a_admin_export_resume_links_are_signed_open_logged_out_and_expire_after_30_days(): void
    {
        $exportedAt = now();
        [$header, $rows, $sheet] = $this->adminExport();

        $linkCol = array_search('Resume Link', $header, true);
        $this->assertNotFalse($linkCol, 'Resume Link column present');
        $this->assertSame(count($header) - 1, $linkCol, 'resume link is the last column (D78)');

        $links = [];
        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            // Read the link before touching another cell: PhpSpreadsheet recycles its Cell object.
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($linkCol + 1).$row;
            $this->assertTrue($sheet->hyperlinkExists($coordinate), "row {$row} resume cell is a clickable hyperlink");
            $linkUrl = $sheet->getHyperlink($coordinate)->getUrl();
            $links[(string) $sheet->getCell([1, $row])->getValue()] = $linkUrl;
        }
        $this->assertCount(3, $links, 'admin export lists every application incl. withdrawn (D78)');
        $url = $links['26EX0002'];
        $this->assertStringContainsString('/api/resumes/signed/'.$this->appOf($this->bob)->resume_id, $url);

        // Expiry ≈ now + 30 days.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('signature', $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertEqualsWithDelta($exportedAt->copy()->addDays(30)->timestamp, (int) $query['expires'], 120, 'link must expire 30 days after export');

        // Logged out → 200 PDF.
        $this->app['auth']->forgetGuards();
        $ok = $this->get($url)->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $ok->headers->get('Content-Type'));
        $body = $ok->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $ok->streamedContent() : $ok->getContent();
        $this->assertStringStartsWith('%PDF', $body);

        // Tamper one character of the signature → 403.
        $sig = $query['signature'];
        $tamperedSig = substr($sig, 0, -1).(substr($sig, -1) === 'a' ? 'b' : 'a');
        $this->get(str_replace('signature='.$sig, 'signature='.$tamperedSig, $url))->assertStatus(403);
        // Tampering with the resume id or the expiry also fails.
        $this->get(str_replace('expires='.$query['expires'], 'expires='.($query['expires'] + 86400), $url))->assertStatus(403);
        $otherId = $this->appOf($this->alice)->resume_id;
        $this->get(str_replace('/signed/'.$this->appOf($this->bob)->resume_id.'?', '/signed/'.$otherId.'?', $url))->assertStatus(403);

        // Still valid on day 29, expired on day 31.
        Carbon::setTestNow(now()->addDays(29));
        $this->get($url)->assertOk();
        Carbon::setTestNow(now()->addDays(2));
        $this->get($url)->assertStatus(403);
        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------ T8.1b

    public function test_T8_1b_admin_workbook_has_profile_answers_round_statuses_flags_and_no_formulas(): void
    {
        // A published round-1 result for Bob and a draft for Alice.
        $this->closeApplications();
        $r1 = $this->posting->rounds()->orderBy('sort_order')->first();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['26EX0002'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/publish")->assertOk();
        ApplicationRoundResult::create(['application_id' => $this->appOf($this->alice)->id, 'posting_round_id' => $r1->id, 'result' => 'waitlisted', 'attendance' => 'yes']);

        [$header, $rows, $sheet] = $this->adminExport();

        // Header is readable text, bold (styled) and contains every C3 profile column, both flags, answers and rounds.
        foreach ([
            'Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'CGPA', 'Ongoing Backlogs', 'Total Backlogs', '10th %', '12th %',
            'Gender', 'Date of Birth', 'Category', 'PwD', 'Home State', 'LinkedIn', 'GitHub',
            'Institute Email', 'Personal Email', 'Phone',
            'Application Status', 'Resume Label', 'Resume Verified', 'Unverified Resume Flag', 'Placed Elsewhere Flag',
            'Q1: Why us?', 'Q2: Languages', 'R1: Aptitude Test', 'R2: HR Interview', 'Resume Link',
        ] as $column) {
            $this->assertContains($column, $header, "admin export column '{$column}'");
        }
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold(), 'header row is bold');

        $byRoll = collect($rows)->keyBy('Roll No');
        $alice = $byRoll['26EX0001'];
        $bob = $byRoll['26EX0002'];
        $this->assertSame('YES', $alice['Unverified Resume Flag']);
        $this->assertSame('YES', $alice['Placed Elsewhere Flag']);
        $this->assertSame('alice.personal@qa.test', $alice['Personal Email']);
        $this->assertSame('OBC-NCL-QAMARK', $alice['Category']);
        $this->assertSame('Yes', $alice['PwD']);
        $this->assertSame('2004-05-06', $alice['Date of Birth']);
        $this->assertSame('Go, Rust', $alice['Q2: Languages']);
        $this->assertSame('-2+3', $bob['Q1: Why us?']);
        $this->assertSame('Selected', $bob['R1: Aptitude Test'], 'published round status');
        $this->assertSame('Waitlisted (appeared) (draft)', $alice['R1: Aptitude Test'], 'admin sees drafts, marked as draft');
        $this->assertSame('Withdrawn', $byRoll['26EX0003']['Application Status']);
        $this->assertEquals(8.0, $bob['CGPA']);

        // Formula injection: every user-controlled text cell is a literal string, never a formula.
        $rowOf = fn (string $roll) => array_search($roll, array_column($rows, 'Roll No'), true) + 2;
        $aliceRow = $rowOf('26EX0001');
        $bobRow = $rowOf('26EX0002');
        foreach ([
            ['Name', $aliceRow, self::EVIL],
            ['Home State', $aliceRow, '+cmd|\' /C calc\'!A0'],
            ['LinkedIn', $aliceRow, '@SUM(1+1)'],
            ['Phone', $aliceRow, '+919876543210'],
            ['Q1: Why us?', $aliceRow, self::EVIL],
            ['Q1: Why us?', $bobRow, '-2+3'],
        ] as [$column, $row, $expected]) {
            $cell = $sheet->getCell([array_search($column, $header, true) + 1, $row]);
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), "{$column} row {$row} must be a string cell");
            $this->assertFalse($cell->isFormula(), "{$column} row {$row} must not be a formula");
            $this->assertSame($expected, $cell->getValue());
        }
    }

    // ------------------------------------------------------------------ T8.2a

    public function test_T8_2a_company_exports_own_posting_any_time_and_gets_404_for_another_company(): void
    {
        $this->assertTrue($this->posting->fresh()->acceptsApplications(), 'precondition: before the deadline');

        Sanctum::actingAs($this->companyUserA);
        $own = $this->get("/api/company/postings/{$this->posting->id}/export")->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $own->headers->get('Content-Type'));

        $postingB = $this->float($this->companyB, 'Beta Analyst');
        Sanctum::actingAs($this->companyUserA);
        $denied = $this->get("/api/company/postings/{$postingB->id}/export", ['Accept' => 'application/json'])->assertNotFound();
        $this->assertStringNotContainsString('Beta Analyst', $denied->getContent());

        Sanctum::actingAs($this->companyUserB);
        $this->get("/api/company/postings/{$this->posting->id}/export", ['Accept' => 'application/json'])->assertNotFound();
        $this->get("/api/company/postings/{$postingB->id}/export")->assertOk();
    }

    // ------------------------------------------------------------------ T8.2b

    public function test_T8_2b_company_columns_are_limited_and_contact_only_when_shared(): void
    {
        // A draft result must never reach the company.
        $this->closeApplications();
        $r1 = $this->posting->rounds()->orderBy('sort_order')->first();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['26EX0002'], 'result' => 'selected'])->assertOk();

        $forbiddenValues = ['+919876543210', '9000000002', 'alice.personal@qa.test', 'bob.personal@qa.test', 'OBC-NCL-QAMARK', '2004-05-06', 'Wanda', '26EX0003', 'used_unverified_resume', 'placed_elsewhere'];
        $allowedApiKeys = ['application_id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'current_cgpa', 'ongoing_backlogs', 'total_backlogs', 'tenth_percent', 'twelfth_percent', 'answers', 'resume_url', 'rounds'];

        // ---- sharing OFF
        Sanctum::actingAs($this->companyUserA);
        $api = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk();
        $this->assertCount(2, $api->json('applicants'), 'withdrawn applicant excluded');
        foreach ($api->json('applicants') as $applicant) {
            $this->assertEqualsCanonicalizing($allowedApiKeys, array_keys($applicant), 'company applicant keys (sharing off)');
            $this->assertEmpty($applicant['rounds'], 'no draft round results for companies');
        }
        foreach ($forbiddenValues as $needle) {
            $this->assertStringNotContainsString($needle, $api->getContent(), "company applicants API leaks '{$needle}'");
        }

        [$header, $rows] = $this->companyExport();
        $extra = array_values(array_filter($header, fn ($h) => ! in_array($h, self::COMPANY_BASE_COLUMNS, true) && ! preg_match('/^[QR]\d+: /', $h)));
        $this->assertSame([], $extra, 'company export has columns outside Q10.2: '.implode(', ', $extra));
        $this->assertSame(['26EX0001', '26EX0002'], array_column($rows, 'Roll No'));
        $this->assertNull($rows[1]['R1: Aptitude Test'], 'draft result is blank in the company export');
        $flat = json_encode($rows);
        foreach ($forbiddenValues as $needle) {
            $this->assertStringNotContainsString($needle, $flat, "company export leaks '{$needle}'");
        }

        // ---- admin turns sharing ON for this posting
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}", ['share_contact_details' => true])->assertOk();

        Sanctum::actingAs($this->companyUserA);
        $api = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk();
        $bob = collect($api->json('applicants'))->firstWhere('roll_no', '26EX0002');
        $this->assertSame('9000000002', $bob['phone']);
        $this->assertSame('bob.personal@qa.test', $bob['personal_email']);
        foreach (['OBC-NCL-QAMARK', '2004-05-06', 'Wanda', 'used_unverified_resume', 'placed_elsewhere'] as $needle) {
            $this->assertStringNotContainsString($needle, $api->getContent());
        }
        foreach ($api->json('applicants') as $applicant) {
            $this->assertEmpty(array_diff(array_keys($applicant), array_merge($allowedApiKeys, ['phone', 'personal_email', 'institute_email'])));
        }

        [$header, $rows] = $this->companyExport();
        $this->assertContains('Phone', $header);
        $this->assertContains('Personal Email', $header);
        foreach (['Category', 'PwD', 'Date of Birth', 'Gender', 'Unverified Resume Flag', 'Placed Elsewhere Flag', 'Application Status'] as $never) {
            $this->assertNotContains($never, $header);
        }
        $this->assertSame('bob.personal@qa.test', collect($rows)->firstWhere('Roll No', '26EX0002')['Personal Email']);
        $this->assertNotContains('26EX0003', array_column($rows, 'Roll No'));

        // Sharing is per posting: another posting of the same company stays private.
        $second = $this->float($this->companyA, 'QA Second');
        $this->applyTo($second, $this->bob, 'approved');
        Sanctum::actingAs($this->companyUserA);
        $this->assertStringNotContainsString('9000000002', $this->getJson("/api/company/postings/{$second->id}/applicants")->getContent());

        // After publishing, the company sees the outcome.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/publish")->assertOk();
        Sanctum::actingAs($this->companyUserA);
        $api = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk();
        $this->assertSame('selected', collect($api->json('applicants'))->firstWhere('roll_no', '26EX0002')['rounds'][$r1->id]['result']);
    }

    // ------------------------------------------------------------------ T8.3

    private function csv(string $uri): array
    {
        Sanctum::actingAs($this->admin);
        $content = $this->get($uri)->assertOk()->streamedContent();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $parsed = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row !== [null]) {
                $parsed[] = $row;
            }
        }
        fclose($handle);

        return $parsed;
    }

    private function acceptedForms(): array
    {
        $formData = [
            'jobTitle' => 'CSV SDE', 'internshipTitle' => 'CSV Intern', 'graduatingBatch' => '2027', 'minTenthPercent' => '60', 'minTwelfthPercent' => '65',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '0', 'maxTotalBacklogs' => '1']]]],
        ];
        $jnf = Jnf::create(['company_id' => $this->companyA->id, 'job_title' => 'CSV SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => $formData]);
        $inf = Inf::create(['company_id' => $this->companyA->id, 'internship_title' => 'CSV Intern', 'internship_description' => 'x', 'status' => 'accepted', 'form_data' => $formData]);

        return [$jnf, $inf];
    }

    public function test_T8_3a_phase1_csv_keeps_every_original_column_in_order_and_only_adds_columns(): void
    {
        [$jnf, $inf] = $this->acceptedForms();

        foreach ([
            ['jnf', "/api/admin/jnfs/{$jnf->id}/csv", self::PHASE1_JNF_HEADERS, $jnf->id],
            ['inf', "/api/admin/infs/{$inf->id}/csv", self::PHASE1_INF_HEADERS, $inf->id],
        ] as [$type, $uri, $phase1, $id]) {
            $rows = $this->csv($uri);
            $this->assertCount(2, $rows, "{$type}: one header + one data row");
            [$header, $data] = $rows;
            $this->assertCount(count($header), $data, "{$type}: data row width equals header width");

            // Nothing removed / renamed.
            $missing = array_values(array_diff($phase1, $header));
            $this->assertSame([], $missing, "{$type}: Phase 1 columns missing/renamed: ".implode(', ', $missing));
            // Relative order of the original columns unchanged.
            $this->assertSame($phase1, array_values(array_intersect($header, $phase1)), "{$type}: Phase 1 columns reordered");
            // Only the approved M4 additions.
            $added = array_values(array_diff($header, $phase1));
            $this->assertEqualsCanonicalizing(['min_tenth_percent', 'min_twelfth_percent', 'branch_backlog_caps'], $added, "{$type}: unexpected new columns");

            $row = array_combine($header, $data);
            $this->assertSame((string) $id, $row[$type.'_id']);
            $this->assertSame('accepted', $row['status']);
            $this->assertSame('2027', $row['graduating_batch'], "{$type}: values stay under their own header");
            $this->assertSame('60', $row['min_tenth_percent']);
            $this->assertSame('65', $row['min_twelfth_percent']);
            $this->assertNotSame('', $row['branch_backlog_caps']);
        }
    }

    /**
     * Strict reading of "keep Phase 1's one-row CSV untouched" (Q8.3) + "only ADDITIVE columns": every original column
     * keeps its POSITION (new columns appended), so positional consumers (Excel column letters, scripts) keep working.
     */
    public function test_T8_3b_phase1_csv_original_columns_keep_their_positions(): void
    {
        [$jnf, $inf] = $this->acceptedForms();

        $jnfHeader = $this->csv("/api/admin/jnfs/{$jnf->id}/csv")[0];
        $infHeader = $this->csv("/api/admin/infs/{$inf->id}/csv")[0];

        $this->assertSame(self::PHASE1_JNF_HEADERS, array_slice($jnfHeader, 0, count(self::PHASE1_JNF_HEADERS)),
            'JNF CSV: new columns were inserted before graduating_batch, shifting 21 original columns right');
        $this->assertSame(self::PHASE1_INF_HEADERS, array_slice($infHeader, 0, count(self::PHASE1_INF_HEADERS)),
            'INF CSV: new columns were inserted before graduating_batch, shifting 10 original columns right');
    }

    // ------------------------------------------------------------------ T8.4

    public function test_T8_4_cycle_student_export_lists_every_enrolled_student_across_chunks(): void
    {
        // 3 from setUp + 202 more = 205 enrolments → crosses the 200-row chunk boundary of studentsWorkbook().
        for ($i = 10; $i < 212; $i++) {
            $this->student(sprintf('26EX%04d', $i));
        }
        $suspendedAccount = $this->student('26EX9001');
        $suspendedAccount->user->update(['is_active' => false]);
        $suspendedEnrolment = $this->student('26EX9002');
        CycleEnrollment::where('student_profile_id', $suspendedEnrolment->id)->update(['status' => 'suspended']);
        $outsider = StudentProfile::factory()->create(['roll_no' => '26EX9999', 'institute_email' => '26ex9999@students.qa.test', 'user_id' => User::factory()->state(['role' => 'student', 'email' => '26ex9999@students.qa.test'])]);

        Sanctum::actingAs($this->admin);
        $response = $this->get("/api/admin/placement-cycles/{$this->cycle->id}/students/export")->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        [$header, $rows, $sheet] = $this->table($response->streamedContent());

        foreach (['Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'Gender', 'CGPA', 'Ongoing Backlogs', 'Total Backlogs', '10th %', '12th %',
            'Category', 'PwD', 'Home State', 'Institute Email', 'Personal Email', 'Phone', 'Account', 'Enrolment',
            'Applications (live)', 'Offers', 'Best CTC (annual, INR)', 'Best Stipend (monthly, INR)', 'Active Blocks'] as $column) {
            $this->assertContains($column, $header);
        }

        $expected = CycleEnrollment::where('placement_cycle_id', $this->cycle->id)->count();
        $this->assertSame(207, $expected);
        $rolls = array_column($rows, 'Roll No');
        $this->assertCount($expected, $rolls, 'every enrolled student exported exactly once');
        $this->assertSame($rolls, array_values(array_unique($rolls)), 'no duplicate rows across chunks');
        $this->assertNotContains('26EX9999', $rolls, 'non-enrolled student not exported');

        $byRoll = collect($rows)->keyBy('Roll No');
        $this->assertSame('Suspended', $byRoll['26EX9001']['Account']);
        $this->assertSame('Suspended', $byRoll['26EX9002']['Enrolment']);
        $this->assertEquals(1, $byRoll['26EX0002']['Applications (live)']);
        $this->assertEquals(0, $byRoll['26EX0003']['Applications (live)'], 'withdrawn application is not live');

        // Formula-ish names stay text in this workbook too.
        $nameCell = $sheet->getCell([array_search('Name', $header, true) + 1, array_search('26EX0001', $rolls, true) + 2]);
        $this->assertSame(DataType::TYPE_STRING, $nameCell->getDataType());
        $this->assertFalse($nameCell->isFormula());
    }
}
