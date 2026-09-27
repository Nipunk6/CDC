<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlacementCycleTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)';

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Full Time 2026-27',
            'type' => 'fulltime',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-05-31',
            'description' => 'Main placement season.',
            'allowed_programmes' => [
                ['programme' => self::BTECH, 'batches' => [2027]],
                ['programme' => 'MBA (2 Year) - CAT', 'batches' => [2027, 2028]],
            ],
        ], $overrides);
    }

    private function createCycle(array $overrides = []): PlacementCycle
    {
        return PlacementCycle::create($this->payload($overrides) + ['status' => 'open']);
    }

    /** The M2 student directory does not exist yet, so insert a bare stub row. */
    private function stubStudentProfile(): StudentProfile
    {
        $id = \DB::table('student_profiles')->insertGetId([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StudentProfile::findOrFail($id);
    }

    public function test_admin_can_create_a_placement_cycle_and_it_is_audited(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/placement-cycles', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('placement_cycle.name', 'Full Time 2026-27');
        $response->assertJsonPath('placement_cycle.type', 'fulltime');
        $response->assertJsonPath('placement_cycle.status', 'open');
        $response->assertJsonPath('placement_cycle.enrolled_students_count', 0);
        $response->assertJsonPath('placement_cycle.postings_count', 0);
        $response->assertJsonPath('placement_cycle.offers_count', 0);

        $cycle = PlacementCycle::sole();
        $this->assertSame($admin->id, $cycle->created_by);
        $this->assertCount(2, $cycle->allowed_programmes);

        $log = AuditLog::query()->where('action', 'cycle.create')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(PlacementCycle::class, $log->subject_type);
        $this->assertSame($cycle->id, $log->subject_id);
        $this->assertNull($log->before);
        $this->assertSame('Full Time 2026-27', $log->after['name']);
    }

    public function test_programme_must_exist_in_the_catalogue(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/placement-cycles', $this->payload([
            'allowed_programmes' => [['programme' => 'B.Tech Wizardry', 'batches' => [2027]]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allowed_programmes.0.programme']);
        $this->assertDatabaseCount('placement_cycles', 0);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/placement-cycles', $this->payload([
            'starts_on' => '2027-01-01',
            'ends_on' => '2026-01-01',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_on']);
    }

    public function test_batches_must_be_years(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/placement-cycles', $this->payload([
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => ['final year']]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allowed_programmes.0.batches.0']);
    }

    public function test_index_lists_cycles_with_counts(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();
        $this->createCycle(['name' => 'Internship 2026-27', 'type' => 'internship']);

        CycleEnrollment::create([
            'placement_cycle_id' => $cycle->id,
            'student_profile_id' => $this->stubStudentProfile()->id,
        ]);

        $response = $this->getJson('/api/admin/placement-cycles');

        $response->assertOk();
        $response->assertJsonCount(2, 'placement_cycles');
        $counts = collect($response->json('placement_cycles'))->pluck('enrolled_students_count', 'name');
        $this->assertSame(1, $counts['Full Time 2026-27']);
        $this->assertSame(0, $counts['Internship 2026-27']);
    }

    public function test_update_records_before_and_after(): void
    {
        $admin = $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $response = $this->patchJson("/api/admin/placement-cycles/{$cycle->id}", $this->payload([
            'name' => 'Full Time 2026-27 (revised)',
        ]));

        $response->assertOk();
        $response->assertJsonPath('placement_cycle.name', 'Full Time 2026-27 (revised)');

        $log = AuditLog::query()->where('action', 'cycle.update')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Full Time 2026-27', $log->before['name']);
        $this->assertSame('Full Time 2026-27 (revised)', $log->after['name']);
    }

    public function test_close_sets_status_and_rejects_a_second_close(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $this->patchJson("/api/admin/placement-cycles/{$cycle->id}/close")
            ->assertOk()
            ->assertJsonPath('placement_cycle.status', 'closed');

        $this->assertSame('closed', $cycle->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cycle.close']);

        $this->patchJson("/api/admin/placement-cycles/{$cycle->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This placement cycle is already closed.');
    }

    public function test_company_user_cannot_touch_placement_cycles(): void
    {
        $company = Company::create([
            'name' => 'Demo Co',
            'industry' => 'Technology',
            'website' => 'https://demo.example',
            'hr_name' => 'Demo HR',
            'hr_email' => 'demo.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $company->id]));

        $this->getJson('/api/admin/placement-cycles')->assertForbidden();
        $this->postJson('/api/admin/placement-cycles', $this->payload())->assertForbidden();
    }

    public function test_enrolling_unknown_roll_numbers_reports_every_row(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $response = $this->postJson("/api/admin/placement-cycles/{$cycle->id}/enroll", [
            'roll_nos' => ['22JE0459', '22je0460', '  ', '22JE0461'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('enrolled', 0);
        $response->assertJsonPath('already_enrolled', 0);
        $response->assertJsonCount(3, 'errors');

        $errors = collect($response->json('errors'));
        $this->assertEqualsCanonicalizing(['22JE0459', '22JE0460', '22JE0461'], $errors->pluck('roll_no')->all());
        $this->assertSame(
            ['No student found with this roll number.'],
            $errors->pluck('reason')->unique()->values()->all()
        );
        // Nothing changed, so no audit row is written.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cycle.enroll']);
        $this->assertDatabaseCount('cycle_enrollments', 0);
    }

    public function test_enroll_reports_duplicates_within_one_payload(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $response = $this->postJson("/api/admin/placement-cycles/{$cycle->id}/enroll", [
            'roll_nos' => ['22JE0459', '22JE0459'],
        ]);

        $response->assertOk();
        $reasons = collect($response->json('errors'))->pluck('reason');
        $this->assertTrue($reasons->contains('Duplicate roll number in this upload.'));
    }

    public function test_enroll_accepts_an_uploaded_csv_column(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $file = UploadedFile::fake()->createWithContent(
            'rolls.csv',
            "roll_no\n22JE0459\n22JE0460\n"
        );

        $response = $this->post("/api/admin/placement-cycles/{$cycle->id}/enroll", ['file' => $file]);

        $response->assertOk();
        // The header row is skipped, so exactly the two data rows are reported.
        $response->assertJsonCount(2, 'errors');
        $this->assertSame([2, 3], collect($response->json('errors'))->pluck('row')->all());
    }

    public function test_enroll_requires_roll_numbers_or_a_file(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        $this->postJson("/api/admin/placement-cycles/{$cycle->id}/enroll", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roll_nos', 'file']);
    }

    public function test_enrollments_list_is_paginated(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        CycleEnrollment::create([
            'placement_cycle_id' => $cycle->id,
            'student_profile_id' => $this->stubStudentProfile()->id,
        ]);

        $response = $this->getJson("/api/admin/placement-cycles/{$cycle->id}/enrollments");

        $response->assertOk();
        $response->assertJsonCount(1, 'enrollments');
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 50);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_unenroll_removes_the_row_and_404s_when_absent(): void
    {
        $admin = $this->actingAsAdmin();
        $cycle = $this->createCycle();
        $student = $this->stubStudentProfile();

        $this->deleteJson("/api/admin/placement-cycles/{$cycle->id}/enroll/{$student->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'This student is not enrolled in this cycle.');

        CycleEnrollment::create([
            'placement_cycle_id' => $cycle->id,
            'student_profile_id' => $student->id,
        ]);

        $this->deleteJson("/api/admin/placement-cycles/{$cycle->id}/enroll/{$student->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Student removed from this placement cycle.');

        $this->assertDatabaseCount('cycle_enrollments', 0);

        $log = AuditLog::query()->where('action', 'cycle.unenroll')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->before['student_profile_id']);
    }

    public function test_deleting_a_cycle_cascades_to_enrollments(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle();

        CycleEnrollment::create([
            'placement_cycle_id' => $cycle->id,
            'student_profile_id' => $this->stubStudentProfile()->id,
        ]);

        $cycle->delete();

        $this->assertDatabaseCount('cycle_enrollments', 0);
    }
}
