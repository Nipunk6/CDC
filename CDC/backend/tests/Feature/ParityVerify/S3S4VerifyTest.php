<?php

namespace Tests\Feature\ParityVerify;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\ExportTemplate;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ExportFieldCatalogue;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Independent QA probes for Superset parity S3 (Excel Templates) and S4 (students directory / student page).
 * A failing probe is a finding; do not "fix" the probe to make it pass.
 */
class S3S4VerifyTest extends TestCase
{
    use RefreshDatabase;

    private const MTECH = 'M.Tech';

    private const NOTE = 'PROBE-NOTE-7f3c internal remark about counselling';

    private User $admin;

    private User $companyUser;

    private Company $company;

    private PlacementCycle $cycleA;

    private PlacementCycle $cycleB;

    private JobPosting $postingA;

    private JobPosting $postingB;

    /** @var array<string, StudentProfile> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $this->company->id, 'email' => 'hr@acme.test']);

        $allowed = [['programme' => StudentProfileFactory::BTECH, 'batches' => [2026, 2027]]];
        $this->cycleA = PlacementCycle::create(['name' => 'FT A', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);
        $this->cycleB = PlacementCycle::create(['name' => 'Intern B', 'type' => 'fulltime', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open', 'allowed_programmes' => $allowed]);

        // roll, gender, programme, branch, batch, cgpa, 10th, 12th, ongoing, total, phone, personal
        $rows = [
            ['22JE0001', 'male', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2027, 7.00, 80.00, 90.00, 0, 0, '9811111111', 'a1@gmail.com'],
            ['22JE0002', 'female', StudentProfileFactory::BTECH, 'Mining Engineering', 2026, 6.99, null, 70.00, 1, 1, '9822222222', null],
            ['22JE0003', 'other', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2027, 8.50, 79.99, null, 0, 2, null, 'third@yahoo.com'],
            ['22MT0004', 'male', self::MTECH, 'Computer Science & Engineering', 2028, null, 85.00, 85.00, 2, 2, null, null],
            ['22JE0005', 'female', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2027, 10.00, 100.00, 100.00, 0, 0, null, null],
        ];
        foreach ($rows as [$roll, $gender, $programme, $branch, $batch, $cgpa, $tenth, $twelfth, $ongoing, $total, $phone, $personal]) {
            $this->s[$roll] = StudentProfile::factory()->create([
                'roll_no' => $roll, 'institute_email' => strtolower($roll).'@iitism.ac.in', 'gender' => $gender, 'programme' => $programme,
                'branch' => $branch, 'graduating_batch' => $batch, 'current_cgpa' => $cgpa, 'tenth_percent' => $tenth, 'twelfth_percent' => $twelfth,
                'ongoing_backlogs' => $ongoing, 'total_backlogs' => $total, 'phone' => $phone, 'personal_email' => $personal,
            ]);
        }
        // Invitation states: accepted = S1, S4, S5; revoked = S2; sent = S3.
        foreach (['22JE0001', '22MT0004', '22JE0005'] as $roll) {
            $this->s[$roll]->user->forceFill(['invited_at' => now(), 'activated_at' => now()])->save();
        }
        $this->s['22JE0002']->user->forceFill(['invited_at' => now(), 'invite_revoked_at' => now()])->save();
        $this->s['22JE0003']->user->forceFill(['invited_at' => now()])->save();

        foreach (['22JE0001', '22JE0002', '22JE0003', '22JE0005'] as $roll) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycleA->id, 'student_profile_id' => $this->s[$roll]->id, 'status' => 'active']);
        }
        foreach (['22JE0001', '22MT0004'] as $roll) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycleB->id, 'student_profile_id' => $this->s[$roll]->id, 'status' => 'active']);
        }

        Sanctum::actingAs($this->admin);
        $this->postingA = $this->float($this->cycleA, 'SDE', [['type' => 'technical_interview', 'enabled' => true]], []);
        $this->postingB = $this->float($this->cycleB, 'Intern SDE', [
            ['type' => 'aptitude_test', 'enabled' => true],
            ['type' => 'hr_interview', 'enabled' => true],
        ], [['question' => 'Languages you know', 'qtype' => 'mcq_multi', 'options' => ['Go', 'Rust']]]);

        // Placed: S1 in B (Full-Time), S5 in A (Internship). S2 holds only a PPO offer in A (not placed, D80).
        $this->offer('22JE0001', $this->postingB, 'fulltime');
        $this->offer('22JE0005', $this->postingA, 'intern');
        $this->offer('22JE0002', $this->postingA, 'ppo_offered');

        // Blocks: S3 blocked in A, S4 blocked in B; a lifted block on S5 must not count.
        PlacementBlock::create(['student_profile_id' => $this->s['22JE0003']->id, 'placement_cycle_id' => $this->cycleA->id, 'scope' => 'all', 'reason' => 'manual', 'active' => true]);
        PlacementBlock::create(['student_profile_id' => $this->s['22MT0004']->id, 'placement_cycle_id' => $this->cycleB->id, 'scope' => 'all', 'reason' => 'manual', 'active' => true]);
        PlacementBlock::create(['student_profile_id' => $this->s['22JE0005']->id, 'placement_cycle_id' => $this->cycleA->id, 'scope' => 'all', 'reason' => 'manual', 'active' => false]);

        // Stage results for S1 on posting B: stage 1 published (selected, appeared), stage 2 draft.
        $app = Application::where('student_profile_id', $this->s['22JE0001']->id)->where('job_posting_id', $this->postingB->id)->sole();
        $app->update(['answers' => [['question_id' => $this->postingB->questions()->first()->id, 'answer' => ['Go', 'Rust']]], 'placed_elsewhere_flag' => true]);
        [$r1, $r2] = $this->postingB->rounds()->get()->all();
        ApplicationRoundResult::create(['application_id' => $app->id, 'posting_round_id' => $r1->id, 'attendance' => 'yes', 'result' => 'selected', 'published_at' => now()]);
        ApplicationRoundResult::create(['application_id' => $app->id, 'posting_round_id' => $r2->id, 'result' => 'rejected']);

        // A withdrawn application on posting B (admin exports include it, company exports must not).
        $resume = $this->s['22MT0004']->resumes()->create(['slot' => 1, 'label' => 'MT CV', 'file_path' => 'resumes/y.pdf', 'file_size' => 1, 'status' => 'pending']);
        Application::create(['job_posting_id' => $this->postingB->id, 'student_profile_id' => $this->s['22MT0004']->id, 'resume_id' => $resume->id, 'status' => 'withdrawn', 'used_unverified_resume' => true, 'applied_at' => now()->subHour()]);
    }

    private function float(PlacementCycle $cycle, string $title, array $rounds, array $questions): JobPosting
    {
        $jnf = Jnf::create([
            'company_id' => $this->company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => $title,
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [
                    ['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                    ['branch' => 'Mining Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                ]]],
                'selectionRounds' => $rounds,
            ],
        ]);
        $payload = [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ];
        if ($questions) {
            $payload['questions'] = $questions;
        }
        $this->postJson("/api/admin/postings", $payload)->assertCreated();

        return JobPosting::query()->latest('id')->firstOrFail();
    }

    private function offer(string $roll, JobPosting $posting, string $type): void
    {
        $resume = $this->s[$roll]->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
        $application = Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $this->s[$roll]->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(),
        ]);
        Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $this->s[$roll]->id, 'company_id' => $this->company->id,
            'job_posting_id' => $posting->id, 'placement_cycle_id' => $posting->placement_cycle_id, 'offer_type' => $type,
            'ctc_annual' => 1200000, 'currency' => 'INR', 'announced_at' => now(),
        ]);
    }

    private function load(string $content): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'probe');
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    private function sheetOf($response): Worksheet
    {
        return $this->load($response->streamedContent());
    }

    private function rowsOf(Worksheet $sheet): array
    {
        return $sheet->toArray(null, false, false, false);
    }

    private function headerRow(Worksheet $sheet, int $row = 1): array
    {
        return array_values(array_filter($sheet->rangeToArray('A'.$row.':'.$sheet->getHighestColumn().$row)[0], fn ($v) => $v !== null));
    }

    private function template(array $columns, string $name = 'Probe'): ExportTemplate
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/export-templates', ['name' => $name])->assertCreated()->json('template.id');
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => $columns])->assertOk();

        return ExportTemplate::findOrFail($id);
    }

    /** @return list<string> */
    private function listRolls(string $query): array
    {
        Sanctum::actingAs($this->admin);

        return collect($this->getJson('/api/admin/students?'.$query)->assertOk()->json('students'))->pluck('roll_no')->sort()->values()->all();
    }

    /** @return list<string> roll numbers from the Download as Excel default template (column B). */
    private function exportRolls(string $query): array
    {
        Sanctum::actingAs($this->admin);
        $rows = $this->rowsOf($this->sheetOf($this->get('/api/admin/students/export?'.$query)->assertOk()));
        array_shift($rows);

        return collect($rows)->pluck(1)->filter()->sort()->values()->all();
    }

    /** The ExportService as it was at git HEAD (before the parity work), for byte-for-byte default-layout comparison. */
    private function headExports(): object
    {
        if (! class_exists(\App\Services\HeadExportServiceProbe::class, false)) {
            $root = dirname(base_path(), 2);
            $src = shell_exec('git -C '.escapeshellarg($root).' show HEAD:CDC/backend/app/Services/ExportService.php 2>/dev/null');
            if (! $src) {
                $this->markTestSkipped('git HEAD not readable');
            }
            eval('?>'.str_replace('class ExportService', 'class HeadExportServiceProbe', $src));
        }

        return new \App\Services\HeadExportServiceProbe();
    }

    // ================================================================== S3

    public function test_p01_company_http_export_ignores_template_param_and_matches_head_company_layout(): void
    {
        $template = $this->template([['key' => 'gender', 'label' => 'Gender'], ['key' => 'placed_elsewhere_flag', 'label' => 'PE'], ['key' => 'mobile', 'label' => 'Phone']]);

        $head = $this->headExports();
        foreach ([false, true] as $share) {
            $this->postingB->update(['share_contact_details' => $share]);
            Sanctum::actingAs($this->companyUser);
            $now = $this->rowsOf($this->sheetOf($this->get("/api/company/postings/{$this->postingB->id}/export?template={$template->id}")->assertOk()));
            $then = $this->rowsOf($this->load($this->createTestResponse($head->applicantsWorkbook($this->postingB->fresh(), 'company'), null)->streamedContent()));
            $this->assertSame($then, $now, 'company export differs from HEAD (share='.var_export($share, true).')');
            $this->assertNotContains('PE', $now[0]);
            $this->assertNotContains('Placed Elsewhere Flag', $now[0]);
            $this->assertNotContains('Gender', $now[0]);
        }
    }

    public function test_p02_company_and_student_cannot_reach_templates_or_eligible_list(): void
    {
        $template = $this->template([['key' => 'roll_no', 'label' => 'Roll']]);
        $p = $this->postingA->id;

        foreach (['company' => $this->companyUser, 'student' => $this->s['22JE0001']->user] as $who => $user) {
            Sanctum::actingAs($user);
            foreach ([
                ['get', '/api/admin/export-templates'],
                ['get', '/api/admin/export-templates/fields'],
                ['get', "/api/admin/export-templates/{$template->id}"],
                ['post', '/api/admin/export-templates'],
                ['patch', "/api/admin/export-templates/{$template->id}"],
                ['post', "/api/admin/export-templates/{$template->id}/duplicate"],
                ['delete', "/api/admin/export-templates/{$template->id}"],
                ['get', "/api/admin/postings/{$p}/eligible/export"],
                ['get', "/api/admin/postings/{$p}/export?template={$template->id}"],
                ['get', '/api/admin/students/export'],
                ['get', "/api/admin/placement-cycles/{$this->cycleA->id}/students/export?template={$template->id}"],
            ] as [$method, $url]) {
                $status = $this->json(strtoupper($method), $url, ['name' => 'x'])->status();
                $this->assertContains($status, [403, 404], "{$who} {$method} {$url} => {$status}");
            }
            foreach (["/api/company/postings/{$p}/eligible/export", '/api/company/export-templates', "/api/company/postings/{$p}/export/eligible"] as $url) {
                $this->assertContains($this->getJson($url)->getStatusCode(), [403, 404], "{$who} {$url}");
            }
        }
        $this->assertSame(['name' => 'Probe'], ExportTemplate::findOrFail($template->id)->only(['name']));
    }

    public function test_p03_company_audience_drops_every_admin_and_contact_key_from_a_template_listing_all_fields(): void
    {
        $columns = [];
        foreach (ExportFieldCatalogue::fields() as $key => $field) {
            $columns[] = ['key' => $key, 'label' => 'L_'.$key] + (($field['cycle'] ?? false) ? ['cycle_id' => $this->cycleA->id] : []);
        }
        $template = $this->template($columns);
        $this->assertCount(count($columns), $template->columns);

        $rows = Application::query()->where('job_posting_id', $this->postingB->id)->where('status', 'applied')
            ->with(['studentProfile', 'roundResults', 'resume', 'offer'])->get()
            ->map(fn ($a) => ['student' => $a->studentProfile, 'application' => $a]);

        foreach ([false => ['company'], true => ['company', 'contact']] as $share => $allowed) {
            $this->postingB->update(['share_contact_details' => (bool) $share]);
            $response = app(\App\Services\ExportService::class)->templateWorkbook($template, $rows, $this->postingB->fresh(), 'company', 'x.xlsx');
            $headers = $this->headerRow($this->load($this->createTestResponse($response, null)->streamedContent()));
            $keys = collect($headers)->filter(fn ($h) => str_starts_with($h, 'L_'))->map(fn ($h) => substr($h, 2))->values()->all();
            $expected = collect(ExportFieldCatalogue::fields())->filter(fn ($f) => in_array($f['audience'], $allowed, true) && ! isset($f['expand']))->keys()->values()->all();
            $this->assertSame($expected, $keys, 'share='.var_export((bool) $share, true));
            foreach ($headers as $h) {
                $this->assertDoesNotMatchRegularExpression('/^L_(gender|placed_elsewhere_flag|unverified_resume_flag|cycle_|offer_|ctc_|applied$|application_status|stage_decision)/', $h);
            }
        }
    }

    public function test_p04_formula_like_display_names_and_values_are_written_as_text(): void
    {
        $evil = '=HYPERLINK("http://evil.test","click")';
        $this->s['22JE0001']->update(['full_name' => '=1+2', 'personal_email' => '@SUM(A1)', 'phone' => '+919811111111']);
        $template = $this->template([
            ['key' => 'name', 'label' => $evil],
            ['key' => 'personal_email', 'label' => '+cmd|\' /C calc\'!A0'],
            ['key' => 'mobile', 'label' => '-2+3'],
            ['key' => 'roll_no', 'label' => '@roll'],
        ]);
        $this->assertSame($evil, $template->columns[0]['label']);

        Sanctum::actingAs($this->admin);
        foreach ([
            "/api/admin/postings/{$this->postingB->id}/export?template={$template->id}",
            "/api/admin/postings/{$this->postingA->id}/eligible/export?template={$template->id}",
            "/api/admin/students/export?search=22JE0001&template={$template->id}",
            "/api/admin/placement-cycles/{$this->cycleA->id}/students/export?template={$template->id}",
        ] as $url) {
            $sheet = $this->sheetOf($this->get($url)->assertOk());
            $header = $this->headerRow($sheet);
            $this->assertSame([$evil, '+cmd|\' /C calc\'!A0', '-2+3', '@roll'], $header, $url);
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $this->assertFalse($cell->isFormula(), "{$url} {$cell->getCoordinate()} is a formula");
                }
            }
            for ($c = 1; $c <= 4; $c++) {
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell([$c, 1])->getDataType(), "{$url} header {$c}");
            }
            $nameRow = collect($sheet->toArray(null, false, false, false))->search(fn ($r) => ($r[3] ?? null) === '22JE0001');
            if ($nameRow !== false) {
                $this->assertSame('=1+2', $sheet->getCell([1, $nameRow + 1])->getValue());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell([1, $nameRow + 1])->getDataType());
                $this->assertSame('@SUM(A1)', $sheet->getCell([2, $nameRow + 1])->getValue());
            }
        }
    }

    public function test_p05_template_columns_order_labels_and_dedupe_match_output_exactly(): void
    {
        $template = $this->template([
            ['key' => 'mobile', 'label' => 'Contact'],
            ['key' => 'sno', 'label' => ''],
            ['key' => 'name', 'label' => '  Full Name  '],
            ['key' => 'mobile', 'label' => 'Dup'],
            ['key' => 'last_edited', 'label' => 'Last Edited'],
            ['key' => 'ctc_currency', 'label' => 'CTC Currency'],
            ['key' => 'cycle_offers', 'label' => 'Offers in B', 'cycle_id' => $this->cycleB->id],
        ]);
        $this->assertSame([
            ['key' => 'mobile', 'label' => 'Contact'],
            ['key' => 'sno', 'label' => 'S.No.'],
            ['key' => 'name', 'label' => 'Full Name'],
            ['key' => 'last_edited', 'label' => 'Last Edited'],
            ['key' => 'ctc_currency', 'label' => 'CTC Currency'],
            ['key' => 'cycle_offers', 'label' => 'Offers in B', 'cycle_id' => $this->cycleB->id],
        ], $template->columns);

        Sanctum::actingAs($this->admin);
        $rows = $this->rowsOf($this->sheetOf($this->get("/api/admin/postings/{$this->postingB->id}/export?template={$template->id}")->assertOk()));
        $this->assertSame(['Contact', 'S.No.', 'Full Name', 'Last Edited', 'CTC Currency', 'Offers in B'], $rows[0]);
        $s1 = collect($rows)->first(fn ($r) => $r[0] === '9811111111');
        $this->assertNotNull($s1);
        $this->assertSame('INR', $s1[4]);
        $this->assertMatchesRegularExpression('/^\d{4} [A-Z][a-z]{2} \d{2} \d{2}:\d{2} (AM|PM)$/', (string) $s1[3]);
        $this->assertStringContainsString('Acme', (string) $s1[5]);
        $this->assertSame([1, 2], collect($rows)->slice(1)->pluck(1)->map(fn ($v) => (int) $v)->values()->all(), 'S.No. runs 1..n');
    }

    public function test_p06_template_validation_and_unknown_or_bad_template_ids(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/export-templates', ['name' => 'V'])->assertCreated()->json('template.id');
        $this->assertSame([['key' => 'name', 'label' => 'Name']], ExportTemplate::find($id)->columns);
        $this->assertSame('STUDENT_LIST', ExportTemplate::find($id)->type);
        $this->postJson('/api/admin/export-templates', ['name' => ''])->assertStatus(422);
        $this->postJson('/api/admin/export-templates', ['name' => 'x', 'type' => 'COMPANY_LIST'])->assertStatus(422);
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'password', 'label' => 'p']]])->assertStatus(422);
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'cycle_offers', 'label' => 'x']]])->assertStatus(422);
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => array_fill(0, 151, ['key' => 'name'])])->assertStatus(422);

        $before = AuditLog::where('action', 'export_template.update')->count();
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'name', 'label' => 'Name']]])->assertOk();
        $this->assertSame($before, AuditLog::where('action', 'export_template.update')->count(), 'no-op update must not audit');
        $this->patchJson("/api/admin/export-templates/{$id}", ['name' => 'Renamed'])->assertOk();
        $log = AuditLog::where('action', 'export_template.update')->latest('id')->first();
        $this->assertSame(['name' => 'V'], $log->before);
        $this->assertSame(['name' => 'Renamed'], $log->after);
        $copy = $this->postJson("/api/admin/export-templates/{$id}/duplicate")->assertCreated()->json('template.id');
        $this->deleteJson("/api/admin/export-templates/{$copy}")->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'export_template.delete')->count());
        $this->assertSame(1, AuditLog::where('action', 'export_template.duplicate')->count());

        foreach ([
            "/api/admin/postings/{$this->postingA->id}/export",
            "/api/admin/postings/{$this->postingA->id}/eligible/export",
            '/api/admin/students/export',
            "/api/admin/placement-cycles/{$this->cycleA->id}/students/export",
        ] as $url) {
            $this->get($url.'?template=99999')->assertNotFound();
            $this->getJson($url.'?template=abc')->assertStatus(422);
        }

        $fields = collect($this->getJson('/api/admin/export-templates/fields')->assertOk()->json('fields'))->keyBy('key');
        foreach (['sno' => 'S.No.', 'ctc_currency' => 'CTC Currency', 'last_edited' => 'Last Edited'] as $key => $label) {
            $this->assertSame($label, $fields[$key]['label']);
        }
        $this->assertSame('company', $fields['sno']['audience']);
        $this->assertSame('admin', $fields['cycle_best_ctc']['audience']);
    }

    public function test_p07_default_downloads_are_identical_to_head(): void
    {
        $head = $this->headExports();
        Sanctum::actingAs($this->admin);

        $now = $this->rowsOf($this->sheetOf($this->get("/api/admin/postings/{$this->postingB->id}/export")->assertOk()));
        $then = $this->rowsOf($this->load($this->createTestResponse($head->applicantsWorkbook($this->postingB->fresh(), 'admin'), null)->streamedContent()));
        $this->assertSame($then, $now, 'admin Download Applicants (Default Template) differs from HEAD');

        $now = $this->rowsOf($this->sheetOf($this->get("/api/admin/placement-cycles/{$this->cycleA->id}/students/export")->assertOk()));
        $then = $this->rowsOf($this->load($this->createTestResponse($head->studentsWorkbook($this->cycleA->fresh()), null)->streamedContent()));
        $this->assertSame($then, $now, 'placement Download as Excel (default) differs from HEAD');
    }

    public function test_p08_eligible_list_applied_column_and_audit(): void
    {
        Application::where('student_profile_id', $this->s['22JE0002']->id)->where('job_posting_id', $this->postingA->id)->update(['status' => 'withdrawn']);

        Sanctum::actingAs($this->admin);
        $rows = $this->rowsOf($this->sheetOf($this->get("/api/admin/postings/{$this->postingA->id}/eligible/export")->assertOk()));
        $this->assertSame(array_column(\App\Services\TemplateExports::ELIGIBLE_DEFAULT, 'label'), $rows[0]);
        $byRoll = collect($rows)->slice(1)->keyBy(1);
        $this->assertArrayNotHasKey('22JE0003', $byRoll->all(), 'blocked student must not be on the eligible list');
        $this->assertArrayNotHasKey('22MT0004', $byRoll->all(), 'student not enrolled in A must not be listed');
        $this->assertSame('Not applied', $byRoll['22JE0001'][13]);
        $this->assertSame('Not applied', $byRoll['22JE0002'][13], 'withdrawn application counts as Not applied');
        $expected = app(\App\Services\EligibilityService::class)->eligibleStudentsQuery($this->postingA)->pluck('roll_no')->sort()->values()->all();
        $this->assertSame($expected, $byRoll->keys()->sort()->values()->all());

        $log = AuditLog::where('action', 'posting.eligible_export')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(JobPosting::class, $log->subject_type);
    }

    // ================================================================== S4.1 filters

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function filterProbes(): array
    {
        $bt = urlencode(StudentProfileFactory::BTECH);

        return [
            'cgpa min inclusive, null excluded' => ['cgpa_min=7', ['22JE0001', '22JE0003', '22JE0005']],
            'cgpa max boundary' => ['cgpa_max=6.99', ['22JE0002']],
            'cgpa max 10 excludes null' => ['cgpa_max=10', ['22JE0001', '22JE0002', '22JE0003', '22JE0005']],
            'class x max inclusive, null excluded' => ['tenth_max=80', ['22JE0001', '22JE0003']],
            'class x min' => ['tenth_min=80', ['22JE0001', '22JE0005', '22MT0004']],
            'class xii range' => ['twelfth_min=85&twelfth_max=90', ['22JE0001', '22MT0004']],
            'ongoing backlogs at most 1' => ['ongoing_backlogs_max=1', ['22JE0001', '22JE0002', '22JE0003', '22JE0005']],
            'total backlogs at most 1' => ['total_backlogs_max=1', ['22JE0001', '22JE0002', '22JE0005']],
            'total backlogs at most 0' => ['total_backlogs_max=0', ['22JE0001', '22JE0005']],
            'programme multi (both)' => ["programmes[]={$bt}&programmes[]=M.Tech", ['22JE0001', '22JE0002', '22JE0003', '22JE0005', '22MT0004']],
            'programme single' => ['programmes[]=M.Tech', ['22MT0004']],
            'branch' => ['branches[]=Mining%20Engineering', ['22JE0002']],
            'batch multi' => ['batches[]=2026&batches[]=2028', ['22JE0002', '22MT0004']],
            'gender other' => ['genders[]=other', ['22JE0003']],
            'gender female' => ['genders[]=female', ['22JE0002', '22JE0005']],
            'placed (any placement)' => ['placement_status=placed', ['22JE0001', '22JE0005']],
            'not placed (PPO offered is not placed)' => ['placement_status=not_placed', ['22JE0002', '22JE0003', '22MT0004']],
            'placed in A' => ["placement_status=placed&cycle_id=__A__", ['22JE0005']],
            'not placed in A' => ["placement_status=not_placed&cycle_id=__A__", ['22JE0001', '22JE0002', '22JE0003', '22MT0004']],
            'placed in B' => ["placement_status=placed&cycle_id=__B__", ['22JE0001']],
            'blocked (lifted ignored)' => ['blocked_status=blocked', ['22JE0003', '22MT0004']],
            'blocked in A' => ['blocked_status=blocked&cycle_id=__A__', ['22JE0003']],
            'not blocked' => ['blocked_status=not_blocked', ['22JE0001', '22JE0002', '22JE0005']],
            'invitation accepted' => ['invitation_status=accepted', ['22JE0001', '22JE0005', '22MT0004']],
            'invitation revoked' => ['invitation_status=revoked', ['22JE0002']],
            'invitation sent' => ['invitation_status=sent', ['22JE0003']],
            'invitation invited (not registered)' => ['invitation_status=invited', ['22JE0002', '22JE0003']],
            'search mobile' => ['search=98111', ['22JE0001']],
            'search personal email' => ['search=a1%40gmail', ['22JE0001']],
            'search institute email' => ['search=22mt0004%40iitism', ['22MT0004']],
            'combined' => ["batches[]=2027&genders[]=male&genders[]=female&cgpa_min=7&blocked_status=not_blocked", ['22JE0001', '22JE0005']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filterProbes')]
    public function test_p09_each_filter_returns_exactly_the_matching_students_in_list_and_excel(string $query, array $expected): void
    {
        $query = str_replace(['__A__', '__B__'], [$this->cycleA->id, $this->cycleB->id], $query);
        $this->assertSame($expected, $this->listRolls($query), 'list: '.$query);
        $this->assertSame($expected, $this->exportRolls($query), 'Download as Excel: '.$query);
    }

    public function test_p10_search_wildcards_are_literal(): void
    {
        // A search for "%" or "_" should not match every student (LIKE wildcards should be escaped).
        $this->assertSame([], $this->listRolls('search=%25'), 'search "%" matched students');
        $this->assertSame([], $this->listRolls('search=_'), 'search "_" matched students');
    }

    public function test_p11_enrolled_list_filters_are_scoped_to_its_placement(): void
    {
        Sanctum::actingAs($this->admin);
        $rolls = fn (PlacementCycle $c, string $q) => collect($this->getJson("/api/admin/placement-cycles/{$c->id}/enrollments?{$q}")->assertOk()->json('enrollments'))
            ->pluck('student_profile.roll_no')->sort()->values()->all();

        $this->assertSame(['22JE0005'], $rolls($this->cycleA, 'placement_status=placed'), 'S1 is placed in B, not in A');
        $this->assertSame(['22JE0001', '22JE0002', '22JE0003'], $rolls($this->cycleA, 'placement_status=not_placed'));
        $this->assertSame(['22JE0003'], $rolls($this->cycleA, 'blocked_status=blocked'));
        $this->assertSame(['22MT0004'], $rolls($this->cycleB, 'blocked_status=blocked'));
        $this->assertSame(['22JE0001'], $rolls($this->cycleB, 'placement_status=placed'));
        $this->assertSame(['22JE0001', '22JE0005'], $rolls($this->cycleA, 'cgpa_min=7&genders[]=male&genders[]=female'));

        $page = $this->getJson("/api/admin/placement-cycles/{$this->cycleA->id}/enrollments?search=22JE")->assertOk();
        $this->assertSame(4, $page->json('meta.total'));
        $this->assertSame(1, $page->json('meta.last_page'));
    }

    public function test_p12_list_payload_has_contact_columns_and_pagination_meta(): void
    {
        Sanctum::actingAs($this->admin);
        $r = $this->getJson('/api/admin/students?per_page=2&page=2')->assertOk();
        $this->assertSame(['current_page' => 2, 'last_page' => 3, 'per_page' => 2, 'total' => 5], $r->json('meta'));
        $first = $this->getJson('/api/admin/students?search=22JE0001')->assertOk()->json('students.0');
        $this->assertSame('22je0001@iitism.ac.in', $first['institute_email']);
        $this->assertSame('9811111111', $first['phone']);
        $this->assertArrayHasKey('has_photo', $first);
    }

    public function test_p13_filtered_download_is_audited_with_filters_and_template(): void
    {
        $template = $this->template([['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'tenth_board', 'label' => 'Board']]);
        Sanctum::actingAs($this->admin);
        $rows = $this->rowsOf($this->sheetOf($this->get("/api/admin/students/export?genders[]=female&cgpa_min=7&template={$template->id}")->assertOk()));
        $this->assertSame([['Roll', 'Board'], ['22JE0005', null]], $rows);
        $log = AuditLog::where('action', 'student.export')->sole();
        $this->assertSame(1, $log->after['count']);
        $this->assertSame(['female'], $log->after['filters']['genders']);
        $this->assertEquals(7, $log->after['filters']['cgpa_min']);
        $this->assertSame($template->id, $log->after['template_id']);
    }

    // ================================================================== S4.5 notes

    public function test_p14_notes_never_appear_in_any_student_or_company_get_payload(): void
    {
        $student = $this->s['22JE0001'];
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/students/{$student->id}/notes", ['body' => self::NOTE])->assertCreated();
        $this->assertSame(1, AuditLog::where('action', 'student.note_create')->count());

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'api/'))
            ->reject(fn ($r) => in_array('role:admin', $r->gatherMiddleware(), true) || in_array('signed', $r->gatherMiddleware(), true))
            ->map(fn ($r) => str_replace(['{jobPosting}', '{studentProfile}'], [$this->postingB->id, $student->id], $r->uri()))
            ->reject(fn ($uri) => str_contains($uri, '{'))
            ->unique()->values();
        $this->assertGreaterThan(10, $routes->count());

        $checked = 0;
        foreach (['student' => $student->user, 'company' => $this->companyUser] as $who => $user) {
            Sanctum::actingAs($user);
            foreach ($routes as $uri) {
                $response = $this->get('/'.$uri, ['Accept' => 'application/json']);
                $this->assertLessThan(500, $response->getStatusCode(), "{$who} /{$uri}");
                $base = $response->baseResponse;
                $content = $base instanceof StreamedResponse ? $response->streamedContent() : (string) $response->getContent();
                if (str_starts_with($content, 'PK')) {
                    $content = json_encode($this->rowsOf($this->load($content)));
                }
                $this->assertStringNotContainsString(self::NOTE, $content, "{$who} /{$uri}");
                $this->assertStringNotContainsString('PROBE-NOTE', $content, "{$who} /{$uri}");
                $checked++;
            }
            foreach (["/api/admin/students/{$student->id}/notes", "/api/admin/students/{$student->id}"] as $url) {
                $this->assertContains($this->getJson($url)->getStatusCode(), [403, 404], "{$who} {$url}");
            }
            $this->assertContains($this->postJson("/api/admin/students/{$student->id}/notes", ['body' => 'x'])->getStatusCode(), [403, 404]);
        }
        $this->assertGreaterThan(20, $checked);
    }

    public function test_p15_note_delete_only_by_author_or_super_admin_and_audited(): void
    {
        $student = $this->s['22JE0002'];
        Sanctum::actingAs($this->admin);
        $noteId = $this->postJson("/api/admin/students/{$student->id}/notes", ['body' => 'n1'])->assertCreated()->json('note.id');
        $other = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);
        Sanctum::actingAs($other);
        $this->deleteJson("/api/admin/students/{$student->id}/notes/{$noteId}")->assertForbidden();
        $this->deleteJson("/api/admin/students/{$this->s['22JE0001']->id}/notes/{$noteId}")->assertNotFound();
        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        Sanctum::actingAs($super);
        $this->deleteJson("/api/admin/students/{$student->id}/notes/{$noteId}")->assertOk();
        $log = AuditLog::where('action', 'student.note_delete')->sole();
        $this->assertSame('n1', $log->before['body']);
    }

    // ================================================================== S4.6 academic extras

    public function test_p16_student_cannot_write_any_academic_extra(): void
    {
        $student = $this->s['22JE0001'];
        $student->update(['current_semester' => 5, 'tenth_board' => 'CBSE', 'tenth_passing_year' => 2019, 'previous_degree' => null]);
        $before = $student->fresh()->only(StudentProfile::ACADEMIC_EXTRAS);

        Sanctum::actingAs($student->user);
        $this->patchJson('/api/student/profile', [
            'current_semester' => 8, 'course_start_date' => '2020-01-01', 'course_end_date' => '2030-01-01', 'lateral_entry' => true,
            'tenth_board' => 'X', 'tenth_passing_year' => 2000, 'twelfth_board' => 'Y', 'twelfth_passing_year' => 2001,
            'previous_degree' => 'BSc', 'previous_degree_score' => 9.9, 'previous_degree_score_type' => 'cgpa',
            'current_cgpa' => 10, 'tenth_percent' => 100,
        ])->assertOk();
        $fresh = $student->fresh();
        $this->assertSame($before, $fresh->only(StudentProfile::ACADEMIC_EXTRAS));
        $this->assertEquals(7.0, (float) $fresh->current_cgpa);
        $this->assertEquals(80.0, (float) $fresh->tenth_percent);
        $this->getJson('/api/student/profile')->assertOk()->assertJsonPath('student.tenth_board', 'CBSE')->assertJsonPath('student.current_semester', 5);

        // Admin can write them, audited.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$student->id}", ['twelfth_board' => 'ISC', 'previous_degree_score' => 11, 'previous_degree_score_type' => 'cgpa'])->assertStatus(422);
        $this->patchJson("/api/admin/students/{$student->id}", ['twelfth_board' => 'ISC'])->assertOk();
        $this->assertSame('ISC', $student->fresh()->twelfth_board);
        $this->assertTrue(AuditLog::where('action', 'like', 'student.%')->where('subject_id', $student->id)->get()->contains(fn ($l) => ($l->after['twelfth_board'] ?? null) === 'ISC'));
    }

    // ================================================================== S4.8 enrolment suspend

    public function test_p17_suspended_enrolment_makes_student_ineligible_with_reason_and_is_audited(): void
    {
        $student = $this->s['22JE0001'];
        $enrollment = CycleEnrollment::where('placement_cycle_id', $this->cycleA->id)->where('student_profile_id', $student->id)->sole();

        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->firstWhere('id', $this->postingA->id);
        $this->assertTrue($card['eligibility']['eligible'], 'precondition: eligible before suspension');

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/placement-cycles/{$this->cycleA->id}/enrollments/{$enrollment->id}", ['status' => 'suspended'])->assertOk();
        $this->patchJson("/api/admin/placement-cycles/{$this->cycleA->id}/enrollments/{$enrollment->id}", ['status' => 'suspended'])->assertOk();
        $this->patchJson("/api/admin/placement-cycles/{$this->cycleB->id}/enrollments/{$enrollment->id}", ['status' => 'active'])->assertNotFound();
        $this->patchJson("/api/admin/placement-cycles/{$this->cycleA->id}/enrollments/{$enrollment->id}", ['status' => 'deleted'])->assertStatus(422);
        $logs = AuditLog::where('action', 'cycle.enrollment_status')->get();
        $this->assertCount(1, $logs, 'exactly one audit row (no-op not audited)');
        $this->assertSame('active', $logs[0]->before['status']);
        $this->assertSame('suspended', $logs[0]->after['status']);

        $reason = 'Your enrolment in this placement is suspended.';
        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->firstWhere('id', $this->postingA->id);
        $this->assertNotNull($card, 'job profile stays listed for a suspended enrolment');
        $this->assertFalse($card['eligibility']['eligible']);
        $this->assertContains($reason, $card['eligibility']['reasons']);
        $this->assertContains($reason, $this->getJson("/api/student/postings/{$this->postingA->id}")->assertOk()->json('eligibility.reasons')
            ?? $this->getJson("/api/student/postings/{$this->postingA->id}")->json('posting.eligibility.reasons') ?? []);
        $resume = $student->resumes()->first();
        $apply = $this->postJson("/api/student/postings/{$this->postingA->id}/apply", ['resume_id' => $resume->id])->assertStatus(422);
        $this->assertContains($reason, $apply->json('reasons'));
        // Other placement (B) untouched.
        $cardB = collect($this->getJson('/api/student/postings')->json('postings'))->firstWhere('id', $this->postingB->id);
        $this->assertNotContains($reason, $cardB['eligibility']['reasons'] ?? []);

        Sanctum::actingAs($this->admin);
        $rolls = collect($this->rowsOf($this->sheetOf($this->get("/api/admin/postings/{$this->postingA->id}/eligible/export")->assertOk())))->slice(1)->pluck(1)->all();
        $this->assertNotContains('22JE0001', $rolls, 'suspended student must leave the Eligible List');
        $page = $this->getJson("/api/admin/students/{$student->id}")->assertOk();
        $labels = collect($page->json('student.placements'))->pluck('status_label', 'cycle.name')->all();
        $this->assertSame('Suspended', $labels['FT A']);
        $this->assertSame('Placed (Intern SDE at Acme)', $labels['Intern B']);

        $this->patchJson("/api/admin/placement-cycles/{$this->cycleA->id}/enrollments/{$enrollment->id}", ['status' => 'active'])->assertOk();
        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->json('postings'))->firstWhere('id', $this->postingA->id);
        $this->assertTrue($card['eligibility']['eligible'], 'reactivated enrolment is eligible again');
        Sanctum::actingAs($this->admin);
        $labels = collect($this->getJson("/api/admin/students/{$student->id}")->json('student.placements'))->pluck('status_label', 'cycle.name')->all();
        $this->assertSame('Enrolled', $labels['FT A']);
    }

    public function test_p18_student_payloads_never_carry_applicant_counts(): void
    {
        Sanctum::actingAs($this->s['22JE0001']->user);
        foreach (['/api/student/postings', "/api/student/postings/{$this->postingA->id}", '/api/student/dashboard', '/api/student/applications'] as $url) {
            $json = $this->getJson($url)->assertOk()->getContent();
            foreach (['applications_count', 'applicants_count', 'applied_count', 'eligible_count', '"applicants"'] as $needle) {
                $this->assertStringNotContainsString($needle, $json, "{$url} carries {$needle}");
            }
        }
    }

    public function test_p19_pending_requests_and_mark_all_verified_race_guard(): void
    {
        $student = $this->s['22MT0004'];
        Sanctum::actingAs($this->admin);
        $this->assertSame(1, $this->getJson('/api/admin/students/pending-requests')->assertOk()->json('resumes'));

        $resume = $student->resumes()->first();
        // Stale timestamp: must be skipped, not verified.
        $r = $this->postJson("/api/admin/students/{$student->id}/resumes/verify-all", ['resumes' => [['id' => $resume->id, 'expected_updated_at' => now()->subYear()->toIso8601String()]]])->assertOk();
        $this->assertSame(0, $r->json('verified'));
        $this->assertSame('pending', $resume->fresh()->status);
        // Single decision path keeps the 409.
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved', 'expected_updated_at' => now()->subYear()->toIso8601String()])->assertStatus(409);
        // Another student's resume id cannot be verified through this student.
        $foreign = $this->s['22JE0001']->resumes()->first();
        $foreign->update(['status' => 'pending']);
        $r = $this->postJson("/api/admin/students/{$student->id}/resumes/verify-all", ['resumes' => [['id' => $foreign->id, 'expected_updated_at' => $foreign->fresh()->updated_at->toIso8601String()]]])->assertOk();
        $this->assertSame(0, $r->json('verified'));
        $this->assertSame('pending', $foreign->fresh()->status);
        // Fresh timestamp: verified, unverified flag cleared, audited.
        $r = $this->postJson("/api/admin/students/{$student->id}/resumes/verify-all", ['resumes' => [['id' => $resume->id, 'expected_updated_at' => $resume->fresh()->updated_at->toIso8601String()]]])->assertOk();
        $this->assertSame(1, $r->json('verified'));
        $this->assertSame('approved', $resume->fresh()->status);
        $this->assertFalse((bool) Application::where('resume_id', $resume->id)->first()->used_unverified_resume);
        $this->assertSame(1, AuditLog::where('action', 'resume.approve')->where('subject_id', $resume->id)->count());
    }

    public function test_p20_student_reports_are_admin_only_audited_and_formula_safe(): void
    {
        $student = $this->s['22JE0001'];
        PostingTitleProbe::rename($this->postingB, '=HYPERLINK("http://evil","x")');
        Sanctum::actingAs($this->admin);
        foreach (['placement-report' => 'student.placement_report', 'eligibility-report' => 'student.eligibility_report'] as $path => $action) {
            $sheet = $this->sheetOf($this->get("/api/admin/students/{$student->id}/{$path}")->assertOk());
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $this->assertFalse($cell->isFormula(), "{$path} {$cell->getCoordinate()}");
                }
            }
            $this->assertSame(1, AuditLog::where('action', $action)->where('subject_id', $student->id)->count());
        }
        foreach ([$this->companyUser, $student->user] as $user) {
            Sanctum::actingAs($user);
            $this->assertContains($this->get("/api/admin/students/{$student->id}/placement-report")->getStatusCode(), [403, 404]);
            $this->assertContains($this->get("/api/admin/students/{$student->id}/eligibility-report")->getStatusCode(), [403, 404]);
        }
    }
}

/** Test helper: set a job profile's title through its form data (titles come from the JNF). */
final class PostingTitleProbe
{
    public static function rename(JobPosting $posting, string $title): void
    {
        $form = $posting->postable;
        $data = $form->form_data;
        $data['jobTitle'] = $title;
        $form->forceFill(['form_data' => $data, 'job_title' => $title])->save();
    }
}
