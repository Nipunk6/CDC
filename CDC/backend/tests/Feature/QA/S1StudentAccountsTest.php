<?php

namespace Tests\Feature\QA;

use App\Mail\PostingFloatedMail;
use App\Mail\StudentInvitationMail;
use App\Mail\StudentProfileUpdatedMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\BranchChangeRequest;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\StudentAcademicSyncService;
use App\Services\StudentAccountService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * QA acceptance — Section 1 (Student accounts): T1.1a–T1.8.
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S1StudentAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    /** Column order required by spec M2.2. */
    private const SPEC_COLUMNS = [
        'roll_no', 'full_name', 'institute_email', 'programme', 'branch', 'graduating_batch', 'gender',
        'current_cgpa', 'ongoing_backlogs', 'total_backlogs', 'tenth_percent', 'twelfth_percent',
        'date_of_birth', 'personal_email', 'phone', 'category', 'pwd', 'home_state',
    ];

    // ------------------------------------------------------------------ helpers

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'roll_no' => '24qa0459',
            'full_name' => 'Aarav Sharma',
            'institute_email' => '24qa0459@iitism.ac.in',
            'programme' => self::BTECH,
            'branch' => 'Computer Science & Engineering',
            'graduating_batch' => 2027,
            'gender' => 'male',
            'current_cgpa' => 8.45,
            'ongoing_backlogs' => 0,
            'total_backlogs' => 0,
        ], $overrides);
    }

    /** One import row in spec column order. */
    private function row(string $roll, string $name, string $email, array $overrides = []): array
    {
        $row = array_combine(self::SPEC_COLUMNS, [
            $roll, $name, $email, self::BTECH, 'Mining Engineering', '2027', 'female',
            '7.5', '0', '0', '88', '87', '2004-05-06', '', '', 'GEN', 'no', 'Bihar',
        ]);

        return array_values(array_merge($row, $overrides));
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/[,"\n]/', $value) ? '"'.str_replace('"', '""', $value).'"' : $value;
    }

    private function csvFile(array $rows, bool $bom = false, string $name = 'students.csv'): UploadedFile
    {
        $lines = array_map(fn (array $cells) => implode(',', array_map(fn ($v) => $this->csvCell($v), $cells)), $rows);

        return UploadedFile::fake()->createWithContent($name, ($bom ? "\xEF\xBB\xBF" : '').implode("\n", $lines)."\n");
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  0-based; empty strings / nulls leave the cell empty
     * @param  array<string, string>  $formats  coordinate => number format code
     */
    private function xlsxFile(array $rows, array $formats = [], string $name = 'students.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $r => $cells) {
            foreach (array_values($cells) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).($r + 1), $value);
            }
        }
        foreach ($formats as $coordinate => $code) {
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($code);
        }

        $path = tempnam(sys_get_temp_dir(), 'qaxlsx');
        (new Xlsx($spreadsheet))->save($path);
        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function import(UploadedFile $file, bool $dryRun = false)
    {
        return $this->post('/api/admin/students/import'.($dryRun ? '?dry_run=1' : ''), ['file' => $file], ['Accept' => 'application/json']);
    }

    /** @return array<string, int> */
    private function writeCounts(): array
    {
        return [
            'users' => User::count(),
            'student_profiles' => StudentProfile::count(),
            'audit_logs' => AuditLog::count(),
            'email_logs' => EmailLog::count(),
            'password_reset_tokens' => DB::table('password_reset_tokens')->count(),
            'jobs' => DB::table('jobs')->count(),
        ];
    }

    private function cycle(string $name = 'FT 2026-27', string $type = 'fulltime', string $status = 'open'): PlacementCycle
    {
        return PlacementCycle::create([
            'name' => $name,
            'type' => $type,
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => $status,
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function enrol(StudentProfile $student, PlacementCycle $cycle): void
    {
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
    }

    private function formData(array $branches = ['Computer Science & Engineering']): array
    {
        return [
            'jobTitle' => 'Software Engineer',
            'companyProfile' => ['name' => 'Acme'],
            'eligibility' => [[
                'programme' => self::BTECH,
                'branches' => array_map(fn ($b) => ['branch' => $b, 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false], $branches),
            ]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'selectionRounds' => [['id' => '1', 'type' => 'hr_interview', 'enabled' => true]],
        ];
    }

    private function acceptedJnf(array $branches = ['Computer Science & Engineering'], string $companyName = 'Acme'): Jnf
    {
        $company = Company::create(['name' => $companyName, 'hr_name' => 'HR', 'hr_email' => uniqid('hr', true).'@acme.test']);

        return Jnf::create([
            'company_id' => $company->id,
            'job_title' => 'Software Engineer',
            'job_description' => 'x',
            'status' => 'accepted',
            'form_data' => $this->formData($branches),
        ]);
    }

    private function postingDirect(PlacementCycle $cycle, array $branches = ['Computer Science & Engineering'], string $status = 'open', $deadline = null, string $companyName = 'Acme'): JobPosting
    {
        $jnf = $this->acceptedJnf($branches, $companyName);

        return JobPosting::create([
            'postable_type' => Jnf::class,
            'postable_id' => $jnf->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => $deadline ?? now()->addDays(5),
            'status' => $status,
            'floated_at' => now(),
        ]);
    }

    private function floatViaApi(User $admin, PlacementCycle $cycle, array $branches = ['Computer Science & Engineering']): JobPosting
    {
        $jnf = $this->acceptedJnf($branches);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $jnf->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated();

        return JobPosting::where('postable_id', $jnf->id)->where('postable_type', Jnf::class)->sole();
    }

    private function resumeRow(StudentProfile $student, int $slot = 1, string $status = 'approved', string $label = 'Main'): Resume
    {
        return $student->resumes()->create(['slot' => $slot, 'label' => $label, 'file_path' => "resumes/{$student->roll_no}/{$slot}.pdf", 'file_size' => 10, 'status' => $status]);
    }

    // ------------------------------------------------------------------ T1.1a

    public function test_T1_1a_single_add_creates_student_invites_and_audits(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();

        $this->postJson('/api/admin/students', $this->studentPayload())
            ->assertCreated()
            ->assertJsonPath('student.roll_no', '24QA0459');

        $profile = StudentProfile::sole();
        $this->assertSame('student', $profile->user->role);
        $this->assertSame('24qa0459@iitism.ac.in', $profile->user->email);
        $this->assertTrue($profile->user->is_active);

        Mail::assertQueued(StudentInvitationMail::class, fn (StudentInvitationMail $m) => $m->hasTo('24qa0459@iitism.ac.in') && $m->rollNo === '24QA0459');
        $html = Mail::queued(StudentInvitationMail::class)->first()->render();
        $this->assertStringContainsString('24QA0459', $html);

        $log = AuditLog::where('action', 'student.create')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(StudentProfile::class, $log->subject_type);
        $this->assertSame($profile->id, $log->subject_id);
    }

    // ------------------------------------------------------------------ T1.1b

    private function scenarioRows(): array
    {
        $rows = [self::SPEC_COLUMNS];                                                                   // row 1
        for ($i = 1; $i <= 8; $i++) {                                                                   // rows 2-9
            $rows[] = $this->row(sprintf('24QA%04d', $i), 'Student '.chr(64 + $i), sprintf('24qa%04d@iitism.ac.in', $i));
        }
        $rows[] = $this->row('24QA0001', 'Dup Roll', 'dup.roll@iitism.ac.in');                          // row 10: dup in file
        $rows[] = $this->row('24QA0999', 'Existing Roll', 'existing.new@iitism.ac.in');                 // row 11: exists in DB
        $rows[] = $this->row('24QA0012', 'Bad Branch', '24qa0012@iitism.ac.in', ['branch' => 'Astrology']); // row 12
        $rows[] = $this->row('24QA0013', 'Bad Email', 'not-an-email');                                 // row 13
        $rows[] = array_fill(0, count(self::SPEC_COLUMNS), '');                                         // row 14: blank
        $rows[] = self::SPEC_COLUMNS;                                                                   // row 15: trailing header

        return $rows;
    }

    private function assertImportScenario(callable $makeFile): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        StudentProfile::factory()->create(['roll_no' => '24QA0999']);

        $before = $this->writeCounts();
        $dry = $this->import($makeFile(), true);
        $dry->assertOk()->assertJsonPath('valid_rows', 8)->assertJsonPath('created', 0);
        $this->assertSame($before, $this->writeCounts(), 'dry_run=1 must write nothing');
        Mail::assertNothingQueued();
        Mail::assertNothingSent();

        $errors = collect($dry->json('errors'));
        foreach ($errors as $error) {
            $this->assertIsInt($error['row']);
            $this->assertArrayHasKey('roll_no', $error);
            $this->assertNotSame('', trim((string) $error['reason']));
        }
        $rows = $errors->pluck('row')->unique()->sort()->values()->all();
        foreach ([10, 11, 12, 13] as $expected) {
            $this->assertContains($expected, $rows, "Row {$expected} should be reported. Reported: ".json_encode($dry->json('errors')));
        }
        $this->assertNotContains(14, $rows, 'A blank row must not be reported as an error.');
        $this->assertSame([], array_values(array_diff($rows, [10, 11, 12, 13, 15])), 'Unexpected rows reported: '.json_encode($rows));

        $row = fn (int $n) => $errors->where('row', $n)->values();
        $this->assertSame('24QA0001', $row(10)[0]['roll_no']);
        $this->assertStringContainsStringIgnoringCase('duplicate', $row(10)->pluck('reason')->implode(' '));
        $this->assertSame('24QA0999', $row(11)[0]['roll_no']);
        $this->assertContains('roll_no', $row(11)->pluck('field')->all());
        $this->assertSame('24QA0012', $row(12)[0]['roll_no']);
        $this->assertContains('branch', $row(12)->pluck('field')->all());
        $this->assertSame('24QA0013', $row(13)[0]['roll_no']);
        $this->assertContains('institute_email', $row(13)->pluck('field')->all());

        $real = $this->import($makeFile());
        $real->assertOk()->assertJsonPath('created', 8);
        $this->assertSame($before['users'] + 8, User::count());
        $this->assertSame($before['student_profiles'] + 8, StudentProfile::count());
        $this->assertEqualsCanonicalizing(
            array_map(fn ($i) => sprintf('24QA%04d', $i), range(1, 8)),
            StudentProfile::where('roll_no', 'like', '24QA000%')->pluck('roll_no')->all()
        );
        $this->assertSame('Student A', StudentProfile::where('roll_no', '24QA0001')->value('full_name'), 'The in-file duplicate must not overwrite the first row.');
        $this->assertSame(0, StudentProfile::whereIn('roll_no', ['24QA0012', '24QA0013', 'ROLL_NO'])->count());
        $this->assertEqualsCanonicalizing($rows, collect($real->json('errors'))->pluck('row')->unique()->values()->all(), 'real run must report the same bad rows as the dry run');
        Mail::assertQueued(StudentInvitationMail::class, 8);
        $this->assertSame(1, AuditLog::where('action', 'student.import')->where('user_id', $admin->id)->count());
        $this->assertSame(8, AuditLog::where('action', 'student.create')->where('user_id', $admin->id)->count());
    }

    public function test_T1_1b_bulk_import_csv_dry_run_then_real_run(): void
    {
        $this->assertImportScenario(fn () => $this->csvFile($this->scenarioRows()));
    }

    public function test_T1_1b_bulk_import_csv_with_utf8_bom(): void
    {
        $this->assertImportScenario(fn () => $this->csvFile($this->scenarioRows(), true));
    }

    public function test_T1_1b_bulk_import_xlsx(): void
    {
        $this->assertImportScenario(fn () => $this->xlsxFile($this->scenarioRows()));
    }

    public function test_T1_1b_template_download_matches_importer_column_order(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->assertSame(self::SPEC_COLUMNS, StudentAccountService::IMPORT_COLUMNS);

        $response = $this->get('/api/admin/students/import/template');
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'qatpl');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        $header = array_values(array_filter($spreadsheet->getActiveSheet()->toArray()[0], fn ($v) => $v !== null && $v !== ''));
        // Superset parity S4.6: the optional academic extras follow the spec columns at the end; S5.6: the headers
        // are human-readable (with hints) but keep that order.
        $this->assertSame(array_merge(self::SPEC_COLUMNS, StudentAccountService::IMPORT_EXTRA_COLUMNS), array_keys(StudentAccountService::IMPORT_HEADERS));
        $this->assertSame(array_values(StudentAccountService::IMPORT_HEADERS), $header);

        // Round trip: fill the downloaded template with one student and import it.
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($this->row('24QA0777', 'Template User', '24qa0777@iitism.ac.in') as $c => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 1).'2', $value);
        }
        (new Xlsx($spreadsheet))->save($path);
        $file = UploadedFile::fake()->createWithContent('filled_template.xlsx', file_get_contents($path));
        @unlink($path);

        $this->import($file)->assertOk()->assertJsonPath('created', 1)->assertJsonPath('errors', []);
        $this->assertSame('Template User', StudentProfile::where('roll_no', '24QA0777')->value('full_name'));
    }

    // ------------------------------------------------------------------ T1.1c

    public function test_T1_1c_import_edge_cases_are_normalised_or_rejected_with_reason(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $serial = (string) (int) ExcelDate::PHPToExcel(new \DateTime('2004-05-06'));

        $rows = [
            self::SPEC_COLUMNS,
            $this->row('24qa0101', 'Lower Roll', '24qa0101@iitism.ac.in'),                                                       // 2
            array_map(fn ($v) => "  {$v}  ", $this->row('24QA0102', 'Spaced Name', '24QA0102@IITISM.AC.IN', ['gender' => 'Female', 'total_backlogs' => '1', 'pwd' => 'yes'])), // 3
            $this->row('24QA0103', 'Comma Cgpa', '24qa0103@iitism.ac.in', ['current_cgpa' => '8,5']),                           // 4
            $this->row('24QA0104', 'Padded Cgpa', '24qa0104@iitism.ac.in', ['current_cgpa' => '8.50']),                         // 5
            $this->row('24QA0105', 'Pwd Yes', '24qa0105@iitism.ac.in', ['pwd' => 'Yes']),                                        // 6
            $this->row('24QA0106', 'Pwd True', '24qa0106@iitism.ac.in', ['pwd' => 'TRUE']),                                      // 7
            $this->row('24QA0107', 'Pwd One', '24qa0107@iitism.ac.in', ['pwd' => '1']),                                          // 8
            $this->row('24QA0108', 'Date Slash', '24qa0108@iitism.ac.in', ['date_of_birth' => '06/05/2004']),                   // 9
            $this->row('24QA0109', 'Date Serial', '24qa0109@iitism.ac.in', ['date_of_birth' => $serial]),                       // 10
            $this->row('24QA0110', 'Pwd No', '24qa0110@iitism.ac.in', ['pwd' => 'no']),                                          // 11
        ];

        $response = $this->import($this->csvFile($rows))->assertOk();
        $errors = collect($response->json('errors'));

        $lower = StudentProfile::where('roll_no', '24QA0101')->first();
        $this->assertNotNull($lower, 'lower-case roll must be stored upper-case');
        $this->assertSame(0, StudentProfile::where('roll_no', '24qa0101')->whereRaw('roll_no <> upper(roll_no)')->count());

        $spaced = StudentProfile::where('roll_no', '24QA0102')->first();
        $this->assertNotNull($spaced, 'surrounding spaces must be trimmed, not rejected: '.json_encode($errors->where('row', 3)->values()));
        $this->assertSame('Spaced Name', $spaced->full_name);
        $this->assertSame('24qa0102@iitism.ac.in', $spaced->institute_email);
        $this->assertSame('Mining Engineering', $spaced->branch);
        $this->assertSame('female', $spaced->gender);
        $this->assertSame(1, $spaced->total_backlogs);
        $this->assertTrue($spaced->pwd);
        $this->assertSame('Bihar', $spaced->home_state);

        // "8,5" → either 8.50 or a clear rejection; never 8 or 85.
        $comma = StudentProfile::where('roll_no', '24QA0103')->first();
        if ($comma) {
            $this->assertEquals('8.50', $comma->current_cgpa, '"8,5" was stored as '.$comma->current_cgpa);
        } else {
            $err = $errors->where('row', 4)->values();
            $this->assertNotEmpty($err, '"8,5" neither stored nor reported');
            $this->assertContains('current_cgpa', $err->pluck('field')->all());
            $this->assertStringContainsStringIgnoringCase('cgpa', $err->pluck('reason')->implode(' '));
        }

        $this->assertEquals('8.50', StudentProfile::where('roll_no', '24QA0104')->value('current_cgpa'));
        foreach (['24QA0105', '24QA0106', '24QA0107'] as $roll) {
            $this->assertTrue((bool) StudentProfile::where('roll_no', $roll)->value('pwd'), "{$roll} pwd should be true");
        }
        $this->assertFalse((bool) StudentProfile::where('roll_no', '24QA0110')->value('pwd'));

        $this->assertSame('2004-05-06', StudentProfile::where('roll_no', '24QA0108')->first()?->date_of_birth?->toDateString(), 'd/m/Y text date');
        $this->assertSame('2004-05-06', StudentProfile::where('roll_no', '24QA0109')->first()?->date_of_birth?->toDateString(), 'Excel serial as text');
    }

    public function test_T1_1c_xlsx_typed_cells_serial_dates_numbers_and_booleans(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $serial = ExcelDate::PHPToExcel(new \DateTime('2004-05-06'));

        $r2 = $this->row('24QA0201', 'Typed Cells', '24qa0201@iitism.ac.in');
        $r2[6] = 'F';            // gender
        $r2[7] = 8.5;            // CGPA as a number
        $r2[12] = $serial;       // DOB as a real Excel date cell
        $r2[16] = true;          // pwd as a boolean cell
        $r3 = $this->row('24QA0202', 'Text Date', '24qa0202@iitism.ac.in', ['date_of_birth' => '2004-05-06', 'current_cgpa' => '8,5']);

        $response = $this->import($this->xlsxFile([self::SPEC_COLUMNS, $r2, $r3], ['M2' => 'dd-mm-yyyy']))->assertOk();

        $typed = StudentProfile::where('roll_no', '24QA0201')->first();
        $this->assertNotNull($typed, json_encode($response->json('errors')));
        $this->assertSame('2004-05-06', $typed->date_of_birth->toDateString());
        $this->assertEquals('8.50', $typed->current_cgpa);
        $this->assertTrue($typed->pwd);
        $this->assertSame('female', $typed->gender);

        $text = StudentProfile::where('roll_no', '24QA0202')->first();
        if ($text) {
            $this->assertEquals('8.50', $text->current_cgpa);
            $this->assertSame('2004-05-06', $text->date_of_birth->toDateString());
        } else {
            $this->assertContains('current_cgpa', collect($response->json('errors'))->where('row', 3)->pluck('field')->all());
        }
    }

    /**
     * Extra probe: an unrecognised pwd value must not silently become "No".
     *
     * Known open finding QA F-037: fails on purpose until fixed (group qa-open; remove the tag when fixed).
     */
    #[Group('qa-open')]
    public function test_T1_1c_unrecognised_pwd_value_is_not_silently_coerced(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $response = $this->import($this->csvFile([self::SPEC_COLUMNS, $this->row('24QA0301', 'Odd Pwd', '24qa0301@iitism.ac.in', ['pwd' => 'maybe'])]))->assertOk();

        $created = StudentProfile::where('roll_no', '24QA0301')->first();
        $this->assertNull($created, 'pwd "maybe" was silently stored as pwd='.var_export($created?->pwd, true).' instead of being rejected');
        $this->assertContains('pwd', collect($response->json('errors'))->pluck('field')->all());
    }

    /** Extra probe: a two-digit-year Indian text date (dd-mm-yy) must not be silently misread. */
    public function test_T1_1c_two_digit_year_text_date_is_not_silently_misread(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $response = $this->import($this->csvFile([self::SPEC_COLUMNS, $this->row('24QA0302', 'Short Year', '24qa0302@iitism.ac.in', ['date_of_birth' => '06-05-04'])]))->assertOk();

        $created = StudentProfile::where('roll_no', '24QA0302')->first();
        if ($created) {
            $this->assertSame('2004-05-06', $created->date_of_birth?->toDateString(), '"06-05-04" (6 May 2004) was stored as '.$created->date_of_birth?->toDateString());
        } else {
            $this->assertContains('date_of_birth', collect($response->json('errors'))->pluck('field')->all());
        }
    }

    // ------------------------------------------------------------------ T1.2

    public function test_T1_2_login_with_roll_number_case_insensitive_and_no_enumeration(): void
    {
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0100']);
        $student->user->update(['password' => Hash::make('Secret123')]);

        foreach (['22JE0100', '22je0100', ' 22Je0100 '] as $roll) {
            $this->postJson('/api/auth/login', ['roll_no' => $roll, 'password' => 'Secret123'])
                ->assertOk()->assertJsonPath('user.role', 'student')->assertJsonPath('user.student_profile.roll_no', '22JE0100');
        }

        $wrongPassword = $this->postJson('/api/auth/login', ['roll_no' => '22JE0100', 'password' => 'Nope12345']);
        $unknownRoll = $this->postJson('/api/auth/login', ['roll_no' => '99XX9999', 'password' => 'Secret123']);
        $this->assertSame($wrongPassword->status(), $unknownRoll->status());
        $this->assertSame($wrongPassword->json(), $unknownRoll->json());
        $this->assertSame(422, $wrongPassword->status());
        $this->assertSame('Invalid credentials.', $wrongPassword->json('message'));

        // Suspended + wrong password does not reveal the suspension.
        $student->user->update(['is_active' => false]);
        $this->assertSame($wrongPassword->json(), $this->postJson('/api/auth/login', ['roll_no' => '22JE0100', 'password' => 'Nope12345'])->json());
        $student->user->update(['is_active' => true]);

        // Recorded API behaviour (cross-portal guard is in the frontend auth.ts):
        User::factory()->create(['role' => 'admin', 'email' => 'ad@qa.io', 'password' => Hash::make('Secret123')]);
        User::factory()->create(['role' => 'company', 'email' => 'co@qa.io', 'password' => Hash::make('Secret123')]);
        // an admin / company email typed into the roll-number field does not log in
        $this->postJson('/api/auth/login', ['roll_no' => 'ad@qa.io', 'password' => 'Secret123'])->assertStatus(422)->assertJsonPath('message', 'Invalid credentials.');
        $this->postJson('/api/auth/login', ['roll_no' => 'co@qa.io', 'password' => 'Secret123'])->assertStatus(422)->assertJsonPath('message', 'Invalid credentials.');
        // a student can also authenticate with their institute email through the email field (API allows it)
        $this->postJson('/api/auth/login', ['email' => $student->institute_email, 'password' => 'Secret123'])->assertOk()->assertJsonPath('user.role', 'student');
    }

    // ------------------------------------------------------------------ T1.3a

    public function test_T1_3a_invitation_has_roll_number_and_frontend_set_password_link_no_password(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://portal.qa.test/']);
        $this->actingAsAdmin();

        $this->postJson('/api/admin/students', $this->studentPayload())->assertCreated();
        $user = StudentProfile::sole()->user;

        /** @var StudentInvitationMail $mail */
        $mail = Mail::queued(StudentInvitationMail::class)->sole();
        $this->assertSame('24QA0459', $mail->rollNo);
        $this->assertStringStartsWith('https://portal.qa.test/auth/student/set-password?', $mail->setPasswordUrl);
        parse_str((string) parse_url($mail->setPasswordUrl, PHP_URL_QUERY), $query);
        $this->assertSame($user->email, $query['email']);
        $this->assertNotEmpty($query['token']);

        $html = $mail->render();
        $this->assertStringContainsString('24QA0459', $html);
        $this->assertStringContainsString(e($mail->setPasswordUrl), $html);

        // No plaintext password: no word of the mail verifies against the account's password hash.
        $words = array_unique(preg_split('/[\s<>"\'=&?\/:;,()]+/', strip_tags($html).' '.$html, -1, PREG_SPLIT_NO_EMPTY));
        foreach ($words as $word) {
            if (strlen($word) >= 8 && strlen($word) <= 72) {
                $this->assertFalse(Hash::check($word, $user->password), 'The invitation contains the account password.');
            }
        }

        // The link really sets the password; then roll-number login works.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/reset-password', ['token' => $query['token'], 'email' => $query['email'], 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertOk();
        $this->postJson('/api/auth/login', ['roll_no' => '24qa0459', 'password' => 'NewPass123'])->assertOk();
    }

    // ------------------------------------------------------------------ T1.3b

    public function test_T1_3b_invite_link_expiry_and_resend_invitation(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $this->assertSame(7 * 24 * 60, (int) config('auth.passwords.invites.expire'), 'Owner decision (QA F-011): invite links live 7 days');

        $this->postJson('/api/admin/students', $this->studentPayload())->assertCreated();
        $profile = StudentProfile::sole();
        parse_str((string) parse_url(Mail::queued(StudentInvitationMail::class)->sole()->setPasswordUrl, PHP_URL_QUERY), $first);

        DB::table('student_invite_tokens')->where('email', $profile->user->email)->update(['created_at' => now()->subDays(7)->subMinute()]);
        $this->postJson('/api/auth/reset-password', ['token' => $first['token'], 'email' => $first['email'], 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertStatus(422)->assertJsonPath('message', 'The password reset link is invalid or has expired.');

        // Resend exists, works and is audited.
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/students/{$profile->id}/resend-invitation")->assertOk();
        Mail::assertQueued(StudentInvitationMail::class, 2);
        $log = AuditLog::where('action', 'student.invite_resend')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($profile->id, $log->subject_id);

        parse_str((string) parse_url(Mail::queued(StudentInvitationMail::class)->last()->setPasswordUrl, PHP_URL_QUERY), $second);
        $this->assertNotSame($first['token'], $second['token']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/reset-password', ['token' => $second['token'], 'email' => $second['email'], 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertOk();

        // Self-service fallback quoted in E1: forgot password by roll number.
        $this->postJson('/api/auth/forgot-password', ['roll_no' => '24qa0459'])->assertOk();
    }

    // ------------------------------------------------------------------ T1.4a

    /** Known open finding QA F-021: fails on purpose until fixed (group qa-open; remove the tag when fixed). */
    #[Group('qa-open')]
    public function test_T1_4a_previous_cycle_data_visible_to_student_and_admin(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $student = StudentProfile::factory()->create();
        $old = $this->cycle('FT 2025-26', 'fulltime', 'closed');
        $new = $this->cycle('FT 2026-27');
        $this->enrol($student, $old);
        $this->enrol($student, $new);

        $oldPosting = $this->postingDirect($old, status: 'completed', deadline: now()->subMonths(6), companyName: 'OldCo');
        $round = PostingRound::create(['job_posting_id' => $oldPosting->id, 'name' => 'Final', 'round_type' => 'hr_interview', 'sort_order' => 1, 'status' => 'completed', 'is_final' => true]);
        $resume = $this->resumeRow($student);
        $oldApp = Application::create(['job_posting_id' => $oldPosting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()->subMonths(7)]);
        ApplicationRoundResult::create(['application_id' => $oldApp->id, 'posting_round_id' => $round->id, 'result' => 'selected', 'published_at' => now()->subMonths(5), 'decided_by' => $admin->id]);
        $offer = Offer::create(['application_id' => $oldApp->id, 'student_profile_id' => $student->id, 'company_id' => $oldPosting->company()->id, 'job_posting_id' => $oldPosting->id, 'placement_cycle_id' => $old->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1800000, 'announced_by' => $admin->id, 'announced_at' => now()->subMonths(5)]);
        PlacementBlock::create(['student_profile_id' => $student->id, 'placement_cycle_id' => $old->id, 'scope' => 'all', 'reason' => 'offer', 'offer_id' => $offer->id, 'active' => false, 'blocked_by' => $admin->id, 'unblocked_by' => $admin->id, 'unblocked_at' => now()->subMonths(4), 'remark' => 'Unblocked for next season']);

        $newPosting = $this->postingDirect($new, companyName: 'NewCo');
        Application::create(['job_posting_id' => $newPosting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);

        Sanctum::actingAs($student->user);
        $apps = collect($this->getJson('/api/student/applications')->assertOk()->json('applications'));
        $this->assertCount(2, $apps, 'student must see applications from both cycles');
        $past = $apps->firstWhere('id', $oldApp->id);
        $this->assertSame('FT 2025-26', $past['posting']['placement_cycle']['name']);
        $this->assertSame('fulltime', $past['offer']['offer_type']);
        $this->assertSame('selected', $past['trail'][0]['result']);

        $dashboard = $this->getJson('/api/student/dashboard')->assertOk();
        $this->assertContains('OldCo', collect($dashboard->json('offers'))->pluck('company')->all());
        $this->getJson('/api/student/profile')->assertOk()->assertJsonPath('student.roll_no', $student->roll_no);

        Sanctum::actingAs($admin);
        $detail = $this->getJson("/api/admin/students/{$student->id}")->assertOk();
        $this->assertCount(2, $detail->json('student.cycle_enrollments'));
        $this->assertEqualsCanonicalizing(['FT 2025-26', 'FT 2026-27'], collect($detail->json('student.cycle_enrollments'))->pluck('placement_cycle.name')->all());
        $this->assertCount(2, $detail->json('student.applications'));
        $this->assertCount(1, $detail->json('student.offers'));
        $this->assertSame('OldCo', $detail->json('student.offers.0.company_name'));

        $blocks = $detail->json('student.placement_blocks') ?? $detail->json('student.blocks') ?? $detail->json('placement_blocks') ?? $detail->json('blocks');
        $this->assertNotNull($blocks, 'GET /admin/students/{id} must include the blocks history (spec M2.2 show). Keys: '.implode(',', array_keys($detail->json('student'))));
        $this->assertCount(1, $blocks);
    }

    // ------------------------------------------------------------------ T1.4b

    public function test_T1_4b_student_cannot_edit_academic_or_identity_fields(): void
    {
        $student = StudentProfile::factory()->create(['current_cgpa' => 6.5, 'roll_no' => '22JE0500']);
        $other = User::factory()->create();
        $original = $student->fresh()->only(['roll_no', 'full_name', 'institute_email', 'programme', 'branch', 'graduating_batch', 'gender', 'current_cgpa', 'ongoing_backlogs', 'total_backlogs', 'user_id', 'tenth_percent', 'twelfth_percent', 'pwd', 'category']);
        $email = $student->user->email;
        Sanctum::actingAs($student->user);

        $this->patchJson('/api/student/profile', [
            'current_cgpa' => 9.9, 'branch' => 'Mining Engineering', 'roll_no' => '22JE9999', 'programme' => 'MBA (2 Year) - CAT',
            'gender' => 'female', 'graduating_batch' => 2030, 'ongoing_backlogs' => 5, 'total_backlogs' => 9, 'full_name' => 'Hacker',
            'institute_email' => 'hacker@evil.test', 'user_id' => $other->id, 'tenth_percent' => 100, 'twelfth_percent' => 100,
            'pwd' => true, 'category' => 'XX', 'is_active' => false, 'role' => 'admin',
            'personal_email' => 'me@personal.test', 'phone' => '9999999999', 'home_state' => 'Kerala',
            'linkedin_url' => 'https://linkedin.com/in/me', 'github_url' => 'https://github.com/me',
        ])->assertOk();

        $student->refresh();
        $this->assertSame($original, $student->only(array_keys($original)));
        $this->assertSame($email, $student->user->fresh()->email);
        $this->assertSame('student', $student->user->fresh()->role);
        $this->assertTrue($student->user->fresh()->is_active);
        $this->assertSame('me@personal.test', $student->personal_email);
        $this->assertSame('9999999999', $student->phone);
        $this->assertSame('Kerala', $student->home_state);
        $this->assertSame('https://linkedin.com/in/me', $student->linkedin_url);
        $this->assertSame('https://github.com/me', $student->github_url);
    }

    // ------------------------------------------------------------------ T1.5

    public function test_T1_5_admin_edits_cgpa_and_branch_audited_and_student_mailed(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $student = StudentProfile::factory()->create(['current_cgpa' => 7.1]);

        $this->patchJson("/api/admin/students/{$student->id}", ['current_cgpa' => 7.95, 'branch' => 'Mining Engineering'])->assertOk();

        $student->refresh();
        $this->assertEquals('7.95', $student->current_cgpa);
        $this->assertSame('Mining Engineering', $student->branch);

        $log = AuditLog::where('action', 'student.update')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->subject_id);
        $this->assertEquals('7.10', $log->before['current_cgpa']);
        $this->assertEquals('7.95', $log->after['current_cgpa']);
        $this->assertSame('Computer Science & Engineering', $log->before['branch']);
        $this->assertSame('Mining Engineering', $log->after['branch']);

        Mail::assertQueued(StudentProfileUpdatedMail::class, fn ($m) => $m->hasTo($student->user->email));
    }

    // ------------------------------------------------------------------ T1.6a

    public function test_T1_6a_branch_change_flow(): void
    {
        Mail::fake();
        $adminA = $this->admin();
        $adminB = $this->admin();
        $student = StudentProfile::factory()->create();

        Sanctum::actingAs($student->user);
        $this->postJson('/api/student/branch-change', ['requested_branch' => 'Mathematics & Computing', 'reason' => 'Senate approved after first year.'])->assertCreated();
        $this->postJson('/api/student/branch-change', ['requested_branch' => 'Mining Engineering', 'reason' => 'Second while the first is pending.'])->assertStatus(409);
        $this->getJson('/api/student/branch-change')->assertOk()->assertJsonPath('branch_change_requests.0.status', 'pending');

        $first = BranchChangeRequest::sole();
        Sanctum::actingAs($adminA);
        $this->getJson('/api/admin/branch-changes?status=pending')->assertOk()->assertJsonPath('meta.total', 1);

        Sanctum::actingAs($adminB);
        $this->patchJson("/api/admin/branch-changes/{$first->id}", ['status' => 'approved'])->assertOk();
        $this->assertSame('Mathematics & Computing', $student->fresh()->branch);
        $this->assertSame($adminB->id, $first->fresh()->decided_by);
        $this->assertSame($adminB->id, AuditLog::where('action', 'branch_change.approve')->sole()->user_id);
        $profileLog = AuditLog::where('action', 'student.update')->where('subject_id', $student->id)->sole();
        $this->assertSame($adminB->id, $profileLog->user_id);
        $this->assertSame('Computer Science & Engineering', $profileLog->before['branch']);
        $this->assertSame('Mathematics & Computing', $profileLog->after['branch']);
        Mail::assertQueued(StudentProfileUpdatedMail::class, fn ($m) => $m->hasTo($student->user->email) && str_contains($m->headline, 'approved'));

        Sanctum::actingAs(User::find($student->user_id));
        $this->getJson('/api/student/branch-change')->assertOk()->assertJsonPath('branch_change_requests.0.status', 'approved');
        $this->postJson('/api/student/branch-change', ['requested_branch' => 'Mining Engineering', 'reason' => 'Changing again for valid reasons.'])->assertCreated();
        $second = BranchChangeRequest::latest('id')->first();
        $this->assertSame('Mathematics & Computing', $second->current_branch);

        Sanctum::actingAs($adminA);
        $this->patchJson("/api/admin/branch-changes/{$second->id}", ['status' => 'rejected'])->assertStatus(422);
        $this->assertSame('pending', $second->fresh()->status);
        $this->patchJson("/api/admin/branch-changes/{$second->id}", ['status' => 'rejected', 'admin_remark' => 'Not approved by senate.'])->assertOk();
        $this->assertSame('Mathematics & Computing', $student->fresh()->branch);
        $this->assertSame($adminA->id, AuditLog::where('action', 'branch_change.reject')->sole()->user_id);
        Mail::assertQueued(StudentProfileUpdatedMail::class, fn ($m) => $m->hasTo($student->user->email) && str_contains($m->headline, 'rejected'));

        Sanctum::actingAs(User::find($student->user_id));
        $list = collect($this->getJson('/api/student/branch-change')->assertOk()->json('branch_change_requests'));
        $rejected = $list->firstWhere('id', $second->id);
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Not approved by senate.', $rejected['admin_remark']);
    }

    // ------------------------------------------------------------------ T1.6b

    public function test_T1_6b_branch_change_with_live_application_records_behaviour(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $cycle = $this->cycle();
        $student = StudentProfile::factory()->create();
        $this->enrol($student, $cycle);
        $resume = $this->resumeRow($student);

        $csePosting = $this->postingDirect($cycle, ['Computer Science & Engineering'], companyName: 'CseOnly');
        $miningPosting = $this->postingDirect($cycle, ['Mining Engineering'], companyName: 'MiningOnly');

        Sanctum::actingAs($student->user);
        $this->postJson("/api/student/postings/{$csePosting->id}/apply", ['resume_id' => $resume->id])->assertCreated();
        $this->postJson("/api/student/postings/{$miningPosting->id}/apply", ['resume_id' => $resume->id])->assertStatus(422);
        $this->postJson('/api/student/branch-change', ['requested_branch' => 'Mining Engineering', 'reason' => 'Senate approved branch change.'])->assertCreated();

        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/branch-changes/'.BranchChangeRequest::sole()->id, ['status' => 'approved'])->assertOk();

        // New eligibility uses the NEW branch.
        $eligibility = app(EligibilityService::class);
        $fresh = $student->fresh();
        $this->assertFalse($eligibility->check($fresh, $csePosting)['eligible']);
        $this->assertContains('Your branch is not eligible.', $eligibility->check($fresh, $csePosting)['reasons']);
        $this->assertTrue($eligibility->check($fresh, $miningPosting)['eligible']);
        $this->assertTrue($eligibility->eligibleStudentsQuery($miningPosting)->whereKey($student->id)->exists());
        $this->assertFalse($eligibility->eligibleStudentsQuery($csePosting)->whereKey($student->id)->exists());

        // A fresh User per "request" (the earlier instance has the pre-change studentProfile relation cached).
        Sanctum::actingAs(User::find($student->user_id));
        $this->postJson("/api/student/postings/{$miningPosting->id}/apply", ['resume_id' => $resume->id])->assertCreated();

        // RECORDED (NEEDS-OWNER-DECISION): the live application made under the old branch stays "applied",
        // is not flagged anywhere, and the student can still edit it.
        $old = Application::where('job_posting_id', $csePosting->id)->sole();
        $this->assertSame('applied', $old->status);
        $this->assertFalse($old->placed_elsewhere_flag);
        $this->patchJson("/api/student/applications/{$old->id}", ['answers' => []])->assertOk();
        Sanctum::actingAs($admin);
        $row = collect($this->getJson("/api/admin/postings/{$csePosting->id}/applications")->assertOk()->json('applications'))->firstWhere('id', $old->id);
        $this->assertSame('Mining Engineering', $row['student_profile']['branch'], 'admin sees the new branch on the old-branch posting with no ineligibility marker');
        $this->assertArrayNotHasKey('eligible', $row);
    }

    // ------------------------------------------------------------------ T1.6c

    public function test_T1_6c_academic_import_goes_through_the_sync_service(): void
    {
        $admin = $this->actingAsAdmin();
        $this->assertTrue(method_exists(StudentAcademicSyncService::class, 'apply'));

        $this->mock(StudentAcademicSyncService::class, function (MockInterface $mock) use ($admin): void {
            $mock->shouldReceive('apply')->once()->withArgs(function (array $rows, $user) use ($admin): bool {
                return count($rows) === 1 && $rows[0]['roll_no'] === '22JE0001' && $user?->id === $admin->id;
            })->andReturn(['updated' => 1, 'unchanged' => 0, 'errors' => []]);
        });

        $this->post('/api/admin/students/academics/import', [
            'file' => UploadedFile::fake()->createWithContent('acad.csv', "roll_no,current_cgpa,ongoing_backlogs,total_backlogs\n22JE0001,7.4,1,2\n"),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('updated', 1);
    }

    public function test_T1_6c_sync_service_audits_and_never_touches_non_academic_fields(): void
    {
        $admin = $this->actingAsAdmin();
        $a = StudentProfile::factory()->create(['roll_no' => '22JE0001', 'current_cgpa' => 7.0, 'phone' => '9000000001', 'home_state' => 'Bihar']);
        $b = StudentProfile::factory()->create(['roll_no' => '22JE0002', 'current_cgpa' => 8.0]);
        $snapshot = fn (StudentProfile $s) => $s->fresh()->only(['full_name', 'branch', 'programme', 'phone', 'home_state', 'institute_email', 'gender', 'graduating_batch', 'tenth_percent']);
        $aBefore = $snapshot($a);
        $bBefore = $snapshot($b);

        $csv = "roll_no,current_cgpa,ongoing_backlogs,total_backlogs,branch,full_name,phone\n"
            ."22JE0001,7.4,1,2,Mining Engineering,Hacked Name,1111111111\n"
            ."22JE0002,8.6,0,0,Mining Engineering,Hacked Name,2222222222\n";
        $this->post('/api/admin/students/academics/import', ['file' => UploadedFile::fake()->createWithContent('acad.csv', $csv)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('updated', 2);

        $this->assertEquals('7.40', $a->fresh()->current_cgpa);
        $this->assertSame(2, $a->fresh()->total_backlogs);
        $this->assertEquals('8.60', $b->fresh()->current_cgpa);
        $this->assertSame($aBefore, $snapshot($a));
        $this->assertSame($bBefore, $snapshot($b));

        $logs = AuditLog::where('action', 'student.academics_sync')->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->user_id === $admin->id));
        $logA = $logs->firstWhere('subject_id', $a->id);
        $this->assertEquals('7.00', $logA->before['current_cgpa']);
        $this->assertEquals('7.40', $logA->after['current_cgpa']);
        $this->assertSame(['current_cgpa', 'ongoing_backlogs', 'total_backlogs'], array_keys($logA->after));

        // Direct service call (the future institute-DB hook) with extra keys.
        app(StudentAcademicSyncService::class)->apply([
            ['roll_no' => '22je0001', 'current_cgpa' => '9.1', 'branch' => 'Mining Engineering', 'full_name' => 'X', 'phone' => '0'],
        ], $admin);
        $this->assertEquals('9.10', $a->fresh()->current_cgpa);
        $this->assertSame($aBefore, $snapshot($a));
    }

    // ------------------------------------------------------------------ T1.7

    public function test_T1_7_pending_resume_application_flag_lifecycle(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $cycle = $this->cycle();
        $student = StudentProfile::factory()->create();
        $this->enrol($student, $cycle);
        $posting = $this->floatViaApi($admin, $cycle);
        $pending = $this->resumeRow($student, 1, 'pending', 'Data');
        $this->resumeRow($student, 2, 'pending', 'Other');

        Sanctum::actingAs($student->user);
        $apply = $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $pending->id])->assertCreated();
        $apply->assertJsonPath('application.used_unverified_resume', true);
        $this->assertStringContainsStringIgnoringCase('not verified', $apply->json('message'));
        $application = Application::sole();
        $this->assertTrue($application->used_unverified_resume);

        $this->getJson('/api/student/applications')->assertOk()->assertJsonPath('applications.0.used_unverified_resume', true);
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonPath('unverified_applications', 1);
        $this->getJson("/api/student/postings/{$posting->id}")->assertOk()->assertJsonPath('posting.application.used_unverified_resume', true);

        Sanctum::actingAs($admin);
        $this->getJson("/api/admin/postings/{$posting->id}/applications")->assertOk()->assertJsonPath('applications.0.used_unverified_resume', true);
        $this->getJson("/api/admin/postings/{$posting->id}/pipeline")->assertOk()->assertJsonPath('applications.0.used_unverified_resume', true);
        $this->getJson("/api/admin/students/{$student->id}")->assertOk()->assertJsonPath('student.applications.0.used_unverified_resume', true);

        // Approving a DIFFERENT resume does not clear it.
        $this->patchJson('/api/admin/resumes/'.$student->resumes()->where('slot', 2)->value('id'), ['status' => 'approved'])->assertOk();
        $this->assertTrue($application->fresh()->used_unverified_resume);

        // Approving the attached resume clears it everywhere.
        $this->patchJson("/api/admin/resumes/{$pending->id}", ['status' => 'approved'])->assertOk();
        $this->assertFalse($application->fresh()->used_unverified_resume);
        $this->getJson("/api/admin/postings/{$posting->id}/applications")->assertJsonPath('applications.0.used_unverified_resume', false);
        $this->getJson("/api/admin/postings/{$posting->id}/pipeline")->assertJsonPath('applications.0.used_unverified_resume', false);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/applications')->assertJsonPath('applications.0.used_unverified_resume', false);
        $this->getJson('/api/student/dashboard')->assertJsonPath('unverified_applications', 0);
    }

    /**
     * NEEDS-OWNER-DECISION (recorded behaviour): B3 sets the flag at apply time when the chosen resume is not approved and
     * clears it when the resume becomes approved; it does not say what happens when an APPROVED resume attached to a live
     * application is later REJECTED. Today the application stays unflagged, so admins see no warning for it.
     */
    public function test_T1_7_extra_rejecting_an_already_approved_attached_resume_records_behaviour(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $cycle = $this->cycle();
        $student = StudentProfile::factory()->create();
        $this->enrol($student, $cycle);
        $posting = $this->floatViaApi($admin, $cycle);
        $approved = $this->resumeRow($student, 1, 'approved', 'Verified');

        Sanctum::actingAs(User::find($student->user_id));
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $approved->id])->assertCreated()
            ->assertJsonPath('application.used_unverified_resume', false);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/resumes/{$approved->id}", ['status' => 'rejected', 'admin_remark' => 'Fake internship listed'])->assertOk();

        $this->assertSame('rejected', $approved->fresh()->status);
        $this->assertFalse(Application::sole()->used_unverified_resume, 'recorded: the live application is NOT re-flagged when its resume is rejected');
        $this->getJson("/api/admin/postings/{$posting->id}/pipeline")->assertJsonPath('applications.0.used_unverified_resume', false)
            ->assertJsonPath('applications.0.resume.status', 'rejected');
    }

    // ------------------------------------------------------------------ T1.8

    public function test_T1_8_suspended_student_holding_a_token_gets_the_suspended_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('qa')->plainTextToken;
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0800']);
        $student->user->update(['password' => Hash::make('Secret123')]);
        $studentToken = $this->postJson('/api/auth/login', ['roll_no' => '22JE0800', 'password' => 'Secret123'])->assertOk()->json('token');

        $this->bearer($studentToken)->getJson('/api/student/profile')->assertOk();
        $this->bearer($adminToken)->patchJson("/api/admin/students/{$student->id}/suspend")->assertOk();

        foreach (['/api/student/profile', '/api/student/resumes', '/api/student/postings', '/api/student/dashboard'] as $endpoint) {
            $response = $this->bearer($studentToken)->getJson($endpoint);
            $this->assertSame(403, $response->status(), "{$endpoint}: suspended student with a live token got {$response->status()} ".json_encode($response->json()));
            $response->assertJsonPath('message', 'Account suspended. Contact CDC.');
        }
    }

    public function test_T1_8_suspend_reactivate_login_audit_and_audience_exclusion(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('qa')->plainTextToken;
        $cycle = $this->cycle();
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0801']);
        $student->user->update(['password' => Hash::make('Secret123')]);
        $peer = StudentProfile::factory()->create();
        $this->enrol($student, $cycle);
        $this->enrol($peer, $cycle);
        $studentToken = $this->postJson('/api/auth/login', ['roll_no' => '22JE0801', 'password' => 'Secret123'])->assertOk()->json('token');

        $this->bearer($adminToken)->patchJson("/api/admin/students/{$student->id}/suspend")->assertOk();
        $this->assertFalse($student->user->fresh()->is_active);

        // Access denied with the old token (status recorded by the stricter test above).
        $this->assertContains($this->bearer($studentToken)->getJson('/api/student/profile')->status(), [401, 403]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson('/api/auth/login', ['roll_no' => '22JE0801', 'password' => 'Secret123'])
            ->assertStatus(403)->assertJsonPath('message', 'Account suspended. Contact CDC.');

        // Excluded from eligibility and the E2 audience.
        $posting = $this->floatViaApi($admin, $cycle);
        $eligibility = app(EligibilityService::class);
        $this->assertFalse($eligibility->eligibleStudentsQuery($posting)->whereKey($student->id)->exists());
        $this->assertTrue($eligibility->eligibleStudentsQuery($posting)->whereKey($peer->id)->exists());
        $this->assertContains('Your account is suspended.', $eligibility->check($student->fresh(), $posting)['reasons']);
        Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($peer->user->email));
        Mail::assertNotQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($student->user->email) || $m->hasTo($student->user->email));
        $this->assertDatabaseMissing('email_logs', ['user_id' => $student->user->id, 'template' => 'emails.posting-floated']);

        // Reactivate restores access.
        $this->bearer($adminToken)->patchJson("/api/admin/students/{$student->id}/reactivate")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $newToken = $this->postJson('/api/auth/login', ['roll_no' => '22JE0801', 'password' => 'Secret123'])->assertOk()->json('token');
        $this->bearer($newToken)->getJson('/api/student/profile')->assertOk();
        $this->assertTrue($eligibility->check($student->fresh()->load('user'), $posting)['eligible']);

        $suspend = AuditLog::where('action', 'student.suspend')->sole();
        $reactivate = AuditLog::where('action', 'student.reactivate')->sole();
        foreach ([$suspend, $reactivate] as $log) {
            $this->assertSame($admin->id, $log->user_id);
            $this->assertSame($student->id, $log->subject_id);
        }
        $this->assertSame(['is_active' => true], $suspend->before);
        $this->assertSame(['is_active' => false], $suspend->after);
        $this->assertSame(['is_active' => true], $reactivate->after);
    }
}
