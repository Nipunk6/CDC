<?php

namespace Tests\Feature\Security;

use App\Models\Application;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Security audit Part 4 — sessions & tokens. Tests assert the secure behaviour; failures are findings.
 */
#[Group('security')]
class SessionTokenTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_T4_1_sanctum_tokens_expire_after_seven_days(): void
    {
        $this->assertSame(60 * 24 * 7, config('sanctum.expiration'));
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('auth-token')->plainTextToken;
        $this->bearer($token)->getJson('/api/auth/user')->assertOk();

        DB::table('personal_access_tokens')->update(['created_at' => now()->subDays(7)->subMinute()]);
        $this->bearer($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_T4_2_logout_revokes_the_token_for_every_role(): void
    {
        foreach (['admin', 'company', 'student'] as $role) {
            $user = $role === 'student' ? StudentProfile::factory()->create()->user : User::factory()->create(['role' => $role]);
            $token = $user->createToken('auth-token')->plainTextToken;
            $this->bearer($token)->postJson('/api/auth/logout')->assertOk();
            $this->bearer($token)->getJson('/api/auth/user')->assertUnauthorized();
        }
    }

    /** Info: tokens are unlimited per user and there is no "log out everywhere". */
    public function test_T4_7_concurrent_tokens_are_unbounded(): void
    {
        $user = User::factory()->create(['role' => 'company']);
        for ($i = 0; $i < 25; $i++) {
            $user->createToken('auth-token');
        }
        $this->assertSame(25, $user->tokens()->count(), 'documented: no cap, no device list');
    }

    public function test_T4_8_signed_resume_url_cannot_be_retargeted_or_extended(): void
    {
        Storage::fake('local');
        $a = StudentProfile::factory()->create();
        $b = StudentProfile::factory()->create();
        Storage::disk('local')->put('resumes/a.pdf', '%PDF-1.4 a');
        Storage::disk('local')->put('resumes/b.pdf', '%PDF-1.4 b');
        $ra = Resume::create(['student_profile_id' => $a->id, 'slot' => 1, 'label' => 'A', 'file_path' => 'resumes/a.pdf', 'file_size' => 10, 'status' => 'approved']);
        $rb = Resume::create(['student_profile_id' => $b->id, 'slot' => 1, 'label' => 'B', 'file_path' => 'resumes/b.pdf', 'file_size' => 10, 'status' => 'approved']);

        $url = $ra->signedUrl();
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertOk();

        $this->get(str_replace("/signed/{$ra->id}?", "/signed/{$rb->id}?", $path))->assertForbidden(); // id swap
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->get(str_replace('expires='.$q['expires'], 'expires='.($q['expires'] + 86400 * 365), $path))->assertForbidden(); // extend
        $this->get(parse_url($url, PHP_URL_PATH))->assertForbidden(); // no signature

        $this->travel(31)->days();
        $this->get($path)->assertForbidden(); // expired
    }

    /**
     * SEC finding (Low): the resume link a company receives (applicant list/export) keeps opening the resume for
     * 30 days after the student withdrew — the signed URL is a bearer credential not tied to the application.
     *
     * Known open finding SEC-013: fails on purpose until fixed (group qa-open; remove the tag when fixed).
     */
    #[Group('qa-open')]
    public function test_T4_8_resume_link_handed_to_a_company_dies_when_the_application_is_withdrawn(): void
    {
        Storage::fake('local');
        $s = StudentProfile::factory()->create();
        Storage::disk('local')->put('resumes/s.pdf', '%PDF-1.4 s');
        $resume = Resume::create(['student_profile_id' => $s->id, 'slot' => 1, 'label' => 'S', 'file_path' => 'resumes/s.pdf', 'file_size' => 10, 'status' => 'approved']);
        $cycle = \App\Models\PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => []]);
        $company = \App\Models\Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $jnf = \App\Models\Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => []]);
        $posting = \App\Models\JobPosting::create(['postable_type' => \App\Models\Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay(), 'status' => 'open']);
        Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'withdrawn', 'applied_at' => now(), 'withdrawn_at' => now()]);

        $url = $resume->signedUrl(); // what the company's applicant list/export handed out
        $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertForbidden();
    }

    public function test_T4_8_private_disk_serve_route_requires_a_valid_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/22JE0001/1_x.pdf', '%PDF-1.4 secret');
        $this->get('/storage/resumes/22JE0001/1_x.pdf')->assertForbidden();
        $this->put('/storage/resumes/22JE0001/evil.pdf', [])->assertForbidden();
    }
}
