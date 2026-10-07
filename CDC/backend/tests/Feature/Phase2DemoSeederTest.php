<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Seeders\Phase2DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_every_screen_without_sending_mail(): void
    {
        Mail::fake();
        Storage::fake('local');
        User::factory()->create(['role' => 'admin']);

        $this->seed(Phase2DemoSeeder::class);

        $this->assertSame(120, StudentProfile::count());
        $this->assertSame(3, JobPosting::count());
        $sde = JobPosting::where('status', 'completed')->sole();
        $this->assertSame(60, $sde->applications()->count());
        $this->assertSame(8, Offer::count());
        $this->assertSame(1, Offer::where('offer_type', 'intern_performance_ppo')->count());
        // Every demo student is in both cycles, so each offer's block reaches both (QA F-004 owner rule).
        $this->assertSame(16, PlacementBlock::count());
        $this->assertSame(2, PlacementBlock::where('scope', 'internships_only')->count());
        $this->assertSame(2, ApplicationRoundResult::where('result', 'waitlisted')->count());
        $this->assertSame(1, ApplicationRoundResult::where('is_addendum', true)->count());
        $this->assertGreaterThan(0, Application::where('placed_elsewhere_flag', true)->count());
        $this->assertTrue(StudentProfile::where('roll_no', 'like', '23JE%')->exists());
        // Demo students must never carry deliverable addresses (real roll-number mailboxes exist at iitism.ac.in).
        $this->assertSame(0, StudentProfile::where('institute_email', 'not like', '%@students.cdc-demo.test')->count());
        $this->assertSame(0, StudentProfile::where('personal_email', 'not like', '%.cdc-demo.test')->count());
        $this->assertSame(0, \App\Models\User::where('role', 'student')->where('email', 'not like', '%.cdc-demo.test')->count());
        // Running it again changes nothing.
        $this->seed(Phase2DemoSeeder::class);
        $this->assertSame(120, StudentProfile::count());
        // FT, Internship and the S8.3 Draft placement.
        $this->assertSame(3, \App\Models\PlacementCycle::count());
        $this->assertSame(1, \App\Models\PlacementCycle::where('is_draft', true)->count());

        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        // Blocked (placed) students are ineligible for the other FT posting.
        $placed = PlacementBlock::where('scope', 'all')->first()->studentProfile;
        $analyst = JobPosting::where('status', 'open')->whereHas('placementCycle', fn ($q) => $q->where('type', 'fulltime'))->sole();
        $this->assertFalse(app(EligibilityService::class)->check($placed->fresh(['user', 'cycleEnrollments']), $analyst)['eligible']);
    }
}
