<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for the lead's verification fixes (2026-10-07): M2 (company form_data can never carry
 * `allowedStudentCategories`, and the clean-up migration's rule), and L13 (Placement Matrix counts placing offers only).
 */
class VerifyFixLeadTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);

        return User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
    }

    public function test_company_store_update_and_autosave_drop_allowed_student_categories(): void
    {
        Sanctum::actingAs($this->companyUser());
        $payload = fn (array $extra = []) => [
            'job_title' => 'Engineer', 'job_description' => 'Build things.', 'status' => 'draft',
            'form_data' => json_encode(['jobTitle' => 'Engineer', 'allowedStudentCategories' => [1, 2], 'genderFilter' => 'all'] + $extra),
        ];

        $id = $this->postJson('/api/company/jnfs', $payload())->assertCreated()->json('jnf.id');
        $this->assertArrayNotHasKey('allowedStudentCategories', Jnf::find($id)->form_data);
        $this->assertSame('all', Jnf::find($id)->form_data['genderFilter']);

        $this->putJson("/api/company/jnfs/{$id}", $payload(['x' => 1]))->assertOk();
        $this->assertArrayNotHasKey('allowedStudentCategories', Jnf::find($id)->form_data);

        $this->postJson('/api/company/jnfs/autosave', ['id' => $id] + $payload())->assertOk();
        $this->assertArrayNotHasKey('allowedStudentCategories', Jnf::find($id)->form_data);

        $infId = $this->postJson('/api/company/infs/autosave', [
            'internship_title' => 'Intern', 'internship_description' => 'Learn.',
            'form_data' => json_encode(['internshipTitle' => 'Intern', 'allowedStudentCategories' => [3]]),
        ])->assertCreated()->json('inf.id');
        $this->assertArrayNotHasKey('allowedStudentCategories', Inf::find($infId)->form_data);
    }

    public function test_cleanup_migration_keeps_only_admin_set_categories(): void
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $cycle = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => []]);
        // $formCategories = what the company's form carried (null = no key). Before the fix the snapshot copied the
        // form's list whenever the form carried one, so a snapshot equal to the form value is the company's (D127).
        $make = function (string $title, array $categories, ?array $formCategories) use ($company, $cycle) {
            $formData = $formCategories === null ? ['jobTitle' => $title] : ['allowedStudentCategories' => $formCategories];
            $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'form_data' => $formData]);

            return JobPosting::create([
                'postable_type' => Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay(),
                'status' => 'open', 'offer_type' => 'fulltime', 'floated_at' => now(), 'eligibility_snapshot' => ['genderFilter' => 'all', 'allowedStudentCategories' => $categories],
            ]);
        };
        $copied = $make('Copied from the company', [7], [7]);
        // An audit row that merely echoes the company's value must not save it (the pre-fix float audit read it back).
        AuditLog::create(['action' => 'posting.float', 'subject_type' => JobPosting::class, 'subject_id' => $copied->id, 'after' => ['allowed_student_categories' => [7]]]);
        $chosen = $make('Chosen by the CDC', [5], null);
        $chosenOverForm = $make('CDC value differs from the form', [5], [8]);

        (require database_path('migrations/2026_10_07_000037_strip_company_supplied_student_categories.php'))->up();

        $this->assertArrayNotHasKey('allowedStudentCategories', $copied->fresh()->eligibility_snapshot);
        $this->assertSame([5], $chosen->fresh()->eligibility_snapshot['allowedStudentCategories']);
        $this->assertSame([5], $chosenOverForm->fresh()->eligibility_snapshot['allowedStudentCategories']);
        $this->assertSame(0, DB::table('jnfs')->where('form_data', 'like', '%allowedStudentCategories%')->count());
    }
}
