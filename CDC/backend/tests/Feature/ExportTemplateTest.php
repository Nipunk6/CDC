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
 * Superset parity S3: Excel Templates and the downloads that use them.
 */
class ExportTemplateTest extends TestCase
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

    private function sheetOf($response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    private function headers(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row = 1): array
    {
        return array_values(array_filter($sheet->rangeToArray('A'.$row.':'.$sheet->getHighestColumn().$row)[0], fn ($v) => $v !== null));
    }

    private function template(array $columns, string $name = 'CDC Format'): \App\Models\ExportTemplate
    {
        $id = $this->postJson('/api/admin/export-templates', ['name' => $name])->assertCreated()->json('template.id');
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => $columns])->assertOk();

        return \App\Models\ExportTemplate::findOrFail($id);
    }

    public function test_template_library_crud_is_audited_and_validated(): void
    {
        $created = $this->postJson('/api/admin/export-templates', ['name' => '  Wipro Format '])->assertCreated();
        $created->assertJsonPath('template.name', 'Wipro Format')->assertJsonPath('template.type', 'STUDENT_LIST')
            ->assertJsonPath('template.columns.0.key', 'name');
        $id = $created->json('template.id');

        $this->postJson('/api/admin/export-templates', ['name' => ''])->assertStatus(422);
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'nope']]])->assertStatus(422);
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'cycle_offers']]])->assertStatus(422);

        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [
            ['key' => 'roll_no', 'label' => 'Roll No'], ['key' => 'roll_no', 'label' => 'again'], ['key' => 'tenth_percent', 'label' => 'Class 10 %'], ['key' => 'cgpa', 'label' => ''],
        ]])->assertOk()->assertJsonCount(3, 'template.columns')->assertJsonPath('template.columns.2.label', 'CGPA');

        $this->patchJson("/api/admin/export-templates/{$id}", ['name' => 'Renamed'])->assertOk();
        $copy = $this->postJson("/api/admin/export-templates/{$id}/duplicate")->assertCreated()->json('template');
        $this->assertSame('Renamed (copy)', $copy['name']);
        $this->assertCount(3, $copy['columns']);

        $this->getJson('/api/admin/export-templates')->assertOk()->assertJsonCount(2, 'templates');
        $this->getJson('/api/admin/export-templates/fields')->assertOk()->assertJsonFragment(['key' => 'ctc_currency'])->assertJsonFragment(['key' => 'last_edited'])->assertJsonFragment(['key' => 'sno']);
        $this->deleteJson("/api/admin/export-templates/{$id}")->assertOk();

        foreach (['export_template.create', 'export_template.update', 'export_template.duplicate', 'export_template.delete'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }
    }

    public function test_custom_template_download_matches_the_template_exactly_and_is_formula_safe(): void
    {
        $template = $this->template([
            ['key' => 'sno', 'label' => 'S.No.'],
            ['key' => 'name', 'label' => '=cmd|/c calc'],
            ['key' => 'roll_no', 'label' => 'Roll No'],
            ['key' => 'tenth_percent', 'label' => 'Class 10 %'],
            ['key' => 'ctc_currency', 'label' => 'CTC Currency'],
            ['key' => 'last_edited', 'label' => 'Last Edited'],
        ]);

        $sheet = $this->sheetOf($this->get("/api/admin/postings/{$this->posting->id}/export?template={$template->id}")->assertOk());
        $this->assertSame(['S.No.', '=cmd|/c calc', 'Roll No', 'Class 10 %', 'CTC Currency', 'Last Edited'], $this->headers($sheet));
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('B1')->getDataType());
        $this->assertEquals(1, $sheet->getCell('A2')->getValue());
        $this->assertSame('22JE0001', $sheet->getCell('C2')->getValue());
        $this->assertSame(6, $sheet->getHighestDataRow() - 1);
        $this->assertSame($template->id, AuditLog::where('action', 'posting.export')->latest('id')->first()->after['template_id']);

        // Default template still gives today's headers.
        $default = $this->sheetOf($this->get("/api/admin/postings/{$this->posting->id}/export")->assertOk());
        $this->assertSame('Roll No', $default->getCell('A1')->getValue());
        $this->assertSame('10th %', collect($this->headers($default))->first(fn ($h) => str_starts_with($h, '10th')));
    }

    public function test_company_view_never_gets_admin_fields_even_if_a_template_lists_them(): void
    {
        $template = $this->template([
            ['key' => 'roll_no', 'label' => 'Roll'],
            ['key' => 'placed_elsewhere_flag', 'label' => 'PE'],
            ['key' => 'unverified_resume_flag', 'label' => 'UV'],
            ['key' => 'mobile', 'label' => 'Phone'],
            ['key' => 'gender', 'label' => 'Gender'],
            ['key' => 'stages', 'label' => 'Stages'],
        ]);
        $rows = Application::query()->where('job_posting_id', $this->posting->id)->with(['studentProfile', 'roundResults', 'resume', 'offer'])->get()
            ->map(fn ($a) => ['student' => $a->studentProfile, 'application' => $a]);

        $this->posting->update(['share_contact_details' => false]);
        $response = app(\App\Services\ExportService::class)->templateWorkbook($template, $rows, $this->posting->fresh(), 'company', 'x.xlsx');
        $headers = $this->headers($this->sheetOf($this->createTestResponse($response, null)));
        $this->assertSame('Roll', $headers[0]);
        foreach (['PE', 'UV', 'Phone', 'Gender'] as $hidden) {
            $this->assertNotContains($hidden, $headers);
        }
        $this->assertTrue(collect($headers)->contains(fn ($h) => str_starts_with($h, 'R1:')));

        $this->posting->update(['share_contact_details' => true]);
        $response = app(\App\Services\ExportService::class)->templateWorkbook($template, $rows, $this->posting->fresh(), 'company', 'x.xlsx');
        $this->assertContains('Phone', $this->headers($this->sheetOf($this->createTestResponse($response, null))));
    }

    public function test_eligible_list_download_default_and_custom_admin_only(): void
    {
        $this->students[5]->user->forceFill(['is_active' => true])->save();
        Application::query()->whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0006'))->update(['status' => 'withdrawn']);

        $sheet = $this->sheetOf($this->get("/api/admin/postings/{$this->posting->id}/eligible/export")->assertOk());
        $headers = $this->headers($sheet);
        $this->assertSame('S.No.', $headers[0]);
        $this->assertSame('Applied', end($headers));
        $values = collect($sheet->toArray())->flatten();
        $this->assertSame(5, $values->filter(fn ($v) => $v === 'Applied')->count() - 1); // minus the header
        $this->assertTrue($values->contains('Not applied'));
        $this->assertTrue(AuditLog::where('action', 'posting.eligible_export')->exists());

        $template = $this->template([['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'applied', 'label' => 'Status']]);
        $this->assertSame(['Roll', 'Status'], $this->headers($this->sheetOf($this->get("/api/admin/postings/{$this->posting->id}/eligible/export?template={$template->id}")->assertOk())));

        foreach ([$this->students[0]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/postings/{$this->posting->id}/eligible/export")->assertForbidden();
            $this->getJson('/api/admin/export-templates')->assertForbidden();
            $this->postJson('/api/admin/export-templates', ['name' => 'x'])->assertForbidden();
            $this->getJson("/api/company/postings/{$this->posting->id}/export?template={$template->id}")->assertStatus($user->role === 'company' ? 200 : 403);
        }
    }

    public function test_shortlist_and_placement_downloads_accept_a_template(): void
    {
        $this->closeApplications();
        $cycle = $this->posting->placementCycle;
        $template = $this->template([['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'stage_decision', 'label' => 'Decision'], ['key' => 'cycle_enrolment', 'label' => 'Enrolled?', 'cycle_id' => $cycle->id]]);
        $r1 = $this->round(0);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();

        $sheet = $this->sheetOf($this->get("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/shortlist/export?template={$template->id}")->assertOk());
        $this->assertStringContainsString('(Template: CDC Format)', $sheet->getCell('A1')->getValue());
        $this->assertSame(['Roll', 'Decision', 'Enrolled?'], $this->headers($sheet, 3));
        $this->assertSame('Shortlisted (draft)', $sheet->getCell('B4')->getValue());
        $this->assertSame('Enrolled', $sheet->getCell('C4')->getValue());

        $cycleSheet = $this->sheetOf($this->get("/api/admin/placement-cycles/{$cycle->id}/students/export?template={$template->id}")->assertOk());
        $this->assertSame(['Roll', 'Decision', 'Enrolled?'], $this->headers($cycleSheet));
        $this->get("/api/admin/postings/{$this->posting->id}/export?template=999999")->assertNotFound();
    }
}
