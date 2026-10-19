<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyContactChange;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\User;
use App\Services\CompanyHistoryService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Company History (owner decisions 2026-10-20, P3-D55): years by placement start (July–June), "came" = a job profile
 * opened for students (cancelled ones do not count), accepted-but-not-opened forms shown separately, the HR contact
 * saved on each job profile when it opens, and a log of contact changes.
 */
class CompanyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, Company> */
    private array $co = [];

    /** @var array<string, PlacementCycle> */
    private array $cycles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);

        foreach (['Acme' => 'acme', 'Bolt' => 'bolt', 'Crane' => 'crane', 'Delta' => 'delta'] as $name => $slug) {
            $this->co[$slug] = Company::create(['name' => $name, 'sector' => 'IT', 'hr_name' => "{$name} HR", 'hr_designation' => 'Lead', 'hr_email' => "hr@{$slug}.test", 'hr_phone' => '9800000000']);
        }
        $base = ['ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]];
        $this->cycles['ft25'] = PlacementCycle::create(['name' => 'FT 2025-26', 'type' => 'fulltime', 'starts_on' => '2025-07-01'] + $base);
        $this->cycles['ft26'] = PlacementCycle::create(['name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-08-01'] + $base);
        $this->cycles['in26'] = PlacementCycle::create(['name' => 'Intern 2026-27', 'type' => 'internship', 'starts_on' => '2027-01-05'] + $base);

        Sanctum::actingAs($this->admin);
        // Acme: FT in 2025-26 and 2026-27, plus an internship in 2026-27.
        $this->open($this->jnf('acme', 'SDE'), 'ft25');
        $this->open($this->jnf('acme', 'SDE II'), 'ft26');
        $this->open($this->inf('acme', 'Summer Intern'), 'in26');
        // Bolt: came in 2025-26 only. Crane: only a cancelled job profile, plus an accepted JNF never opened. Delta: nothing.
        $this->open($this->jnf('bolt', 'Analyst'), 'ft25');
        $cancelled = $this->open($this->jnf('crane', 'Ops'), 'ft26');
        $this->patchJson("/api/admin/postings/{$cancelled->id}/cancel", ['reason' => 'Company withdrew'])->assertOk();
        $this->jnf('crane', 'Never opened');
    }

    private function form(): array
    {
        return [
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
            'signatory' => ['name' => 'Signing Officer', 'designation' => 'Director'],
        ];
    }

    private function jnf(string $company, string $title): Jnf
    {
        return Jnf::create(['company_id' => $this->co[$company]->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 1, 'form_data' => ['jobTitle' => $title] + $this->form()]);
    }

    private function inf(string $company, string $title): Inf
    {
        return Inf::create(['company_id' => $this->co[$company]->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => 'accepted', 'form_data' => ['internshipTitle' => $title] + $this->form()]);
    }

    private function open(Jnf|Inf $form, string $cycle): JobPosting
    {
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => $form instanceof Jnf ? 'jnf' : 'inf', 'form_id' => $form->id, 'placement_cycle_id' => $this->cycles[$cycle]->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated()->json('posting.id');

        return JobPosting::findOrFail($id);
    }

    private function names(array $query = []): array
    {
        $url = '/api/admin/company-history'.($query ? '?'.http_build_query($query) : '');

        return array_column($this->getJson($url)->assertOk()->json('companies'), 'name');
    }

    public function test_academic_year_runs_july_to_june(): void
    {
        $this->assertSame('2025-26', CompanyHistoryService::academicYear($this->cycles['ft25']));
        $this->assertSame('2026-27', CompanyHistoryService::academicYear($this->cycles['ft26']));
        $this->assertSame('2026-27', CompanyHistoryService::academicYear($this->cycles['in26']), 'a January start belongs to the year that began in July');
    }

    public function test_overview_lists_visits_and_filters(): void
    {
        $response = $this->getJson('/api/admin/company-history')->assertOk();
        $this->assertSame(['2026-27', '2025-26'], $response->json('years'));
        $rows = collect($response->json('companies'))->keyBy('name');

        $this->assertSame(['2026-27', '2025-26'], array_column($rows['Acme']['visits'], 'year'));
        $this->assertSame(['FT 2026-27', 'Intern 2026-27'], array_column($rows['Acme']['visits'][0]['placements'], 'name'));
        $this->assertSame(3, $rows['Acme']['job_profiles']);
        $this->assertSame('2025-26', $rows['Acme']['first_year']);
        $this->assertSame('hr@acme.test', $rows['Acme']['hr']['email']);
        $this->assertSame([], $rows['Crane']['visits'], 'a cancelled job profile does not count as a visit');
        $this->assertSame(1, $rows['Crane']['accepted_not_opened']);

        $this->assertSame(['Acme'], $this->names(['year' => '2026-27']));
        $this->assertSame(['Acme', 'Bolt'], $this->names(['year' => '2025-26']));
        $this->assertSame(['Acme'], $this->names(['type' => 'internship']));
        $this->assertSame(['Bolt'], $this->names(['status' => 'not_returned']), 'came in 2025-26, not in the latest year');
        $this->assertSame([], $this->names(['status' => 'not_returned', 'type' => 'internship']));
        $this->assertSame(['Crane', 'Delta'], $this->names(['status' => 'never']));
        $this->assertSame(['Bolt'], $this->names(['q' => 'hr@bolt']));
        $this->getJson('/api/admin/company-history?year=2026')->assertStatus(422);
    }

    public function test_detail_groups_job_profiles_and_keeps_the_hr_contact_from_opening(): void
    {
        // The HR contact changes after the job profiles opened: their saved copy stays as it was.
        $this->putJson("/api/admin/companies/{$this->co['acme']->id}", ['name' => 'Acme', 'hr_name' => 'New Person', 'hr_email' => 'new.person@gmail.com', 'hr_phone' => '9811111111'])->assertOk();

        $detail = $this->getJson("/api/admin/companies/{$this->co['acme']->id}/history")->assertOk();
        $this->assertSame(['2026-27', '2025-26'], array_column($detail->json('history'), 'year'));
        $this->assertSame(['years' => ['2026-27', '2025-26'], 'job_profiles' => 3, 'applicants' => 0, 'offers' => 0], $detail->json('summary'));

        $sde = $detail->json('history.1.placements.0.job_profiles.0');
        $this->assertSame('SDE', $sde['title']);
        $this->assertSame('Acme HR', $sde['hr_at_opening']['name']);
        $this->assertSame('hr@acme.test', $sde['hr_at_opening']['email']);
        $this->assertSame(['name' => 'Signing Officer', 'designation' => 'Director'], $sde['signatory']);
        $this->assertSame('New Person', $detail->json('contacts.hr.name'), 'current contacts show the new HR');

        $change = $detail->json('contact_changes.0');
        $this->assertSame('admin', $change['source']);
        $this->assertSame('Acme HR', $change['before']['hr_name']);
        $this->assertSame('New Person', $change['after']['hr_name']);
        $this->assertArrayNotHasKey('hr_designation', $change['after'], 'only changed fields are logged');

        // A cancelled job profile is listed in its year but the year does not count as a visit.
        $crane = $this->getJson("/api/admin/companies/{$this->co['crane']->id}/history")->assertOk();
        $this->assertFalse($crane->json('history.0.counted'));
        $this->assertSame('cancelled', $crane->json('history.0.placements.0.job_profiles.0.status'));
        $this->assertSame('Never opened', $crane->json('accepted_not_opened.0.title'));
    }

    public function test_contact_changes_are_logged_from_the_company_profile_and_not_when_nothing_changes(): void
    {
        // The HR email must have a real domain on these forms (`email:rfc,dns`).
        $this->co['bolt']->update(['hr_email' => 'bolt.hr.cdc@gmail.com']);
        $this->putJson("/api/admin/companies/{$this->co['bolt']->id}", ['name' => 'Bolt Ltd', 'hr_name' => 'Bolt HR', 'hr_email' => 'bolt.hr.cdc@gmail.com', 'hr_phone' => '9800000000'])->assertOk();
        $this->assertSame(0, CompanyContactChange::count(), 'renaming the company is not a contact change');

        $this->co['bolt']->update([
            'website' => 'https://bolt.test', 'head_talent_contact' => ['name' => 'Head', 'designation' => 'VP', 'email' => 'head@gmail.com', 'mobile' => '9822222222'],
            'primary_contact' => ['name' => 'Poc', 'designation' => 'Mgr', 'email' => 'poc@gmail.com', 'mobile' => '9833333333'],
        ]);
        $recruiter = User::factory()->create(['role' => 'company', 'company_id' => $this->co['bolt']->id, 'email' => 'bolt.hr.cdc@gmail.com']);
        Sanctum::actingAs($recruiter);
        $this->putJson('/api/company/profile', [
            'name' => 'Bolt Ltd', 'website' => 'https://bolt.test', 'sector' => 'IT',
            'hr_name' => 'Bolt HR', 'hr_designation' => 'Lead', 'hr_email' => 'bolt.hr.cdc@gmail.com', 'hr_phone' => '9844444444',
            'head_name' => 'Head', 'head_designation' => 'VP', 'head_email' => 'head@gmail.com', 'head_mobile' => '9822222222',
            'poc1_name' => 'Poc', 'poc1_designation' => 'Mgr', 'poc1_email' => 'poc@gmail.com', 'poc1_mobile' => '9833333333',
        ])->assertOk();

        $change = CompanyContactChange::sole();
        $this->assertSame('company', $change->source);
        $this->assertSame($recruiter->id, $change->changed_by);
        $this->assertSame(['hr_phone' => '9800000000'], array_intersect_key($change->before, ['hr_phone' => 1]));
        $this->assertSame('9844444444', $change->after['hr_phone']);
    }

    public function test_export_and_permissions(): void
    {
        $response = $this->get('/api/admin/company-history/export?year=2025-26');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'ch').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        $this->assertStringContainsString('year 2025-26', $rows[0][0]);
        $this->assertSame('Company', $rows[2][0]);
        $names = array_values(array_filter(array_column(array_slice($rows, 3), 0), fn ($v) => $v !== null && ! str_starts_with((string) $v, 'Downloaded on')));
        $this->assertSame(['Acme', 'Bolt'], $names);

        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($super);
        $roleId = $this->postJson('/api/admin/roles', ['name' => 'No companies', 'levels' => ['students' => 'read']])->assertCreated()->json('role.id');
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'staff_role_id' => $roleId]));
        $this->getJson('/api/admin/company-history')->assertForbidden();
        $this->getJson("/api/admin/companies/{$this->co['acme']->id}/history")->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $this->co['acme']->id]));
        $this->getJson('/api/admin/company-history')->assertForbidden();
        $this->assertNotNull($super);
    }
}
