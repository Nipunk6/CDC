<?php

namespace Tests\Feature;

use App\Mail\BroadcastMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    private PostingRound $round;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);

        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        // 0-3 enrolled + applied; 4 enrolled, not applied; 5 is in another branch, not enrolled.
        for ($i = 0; $i < 6; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1), 'branch' => $i === 5 ? 'Electrical Engineering' : 'Computer Science & Engineering']);
            if ($i < 5) {
                CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $this->students[] = $s;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();
        $this->round = $this->posting->rounds()->orderBy('sort_order')->first();

        foreach (array_slice($this->students, 0, 4) as $i => $s) {
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $application = Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => 'applied', 'applied_at' => now(),
            ]);
            // 0: published selected, 1: published waitlisted, 2: DRAFT selected, 3: published rejected.
            $result = ['selected', 'waitlisted', 'selected', 'rejected'][$i];
            ApplicationRoundResult::create([
                'application_id' => $application->id, 'posting_round_id' => $this->round->id, 'result' => $result,
                'published_at' => $i === 2 ? null : now(),
            ]);
        }
    }

    private function createNotice(array $audiences, array $extra = []): Notice
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/notices', ['title' => 'Venue change', 'body' => '<p>Hall <b>A</b></p>', 'audiences' => $audiences] + $extra)
            ->assertCreated()->json('notice.id');

        return Notice::findOrFail($id);
    }

    private function studentIds(string $path): array
    {
        return collect($this->getJson($path)->assertOk()->json('notices'))->pluck('id')->all();
    }

    public function test_draft_then_publish_and_only_the_audience_sees_it(): void
    {
        $notice = $this->createNotice([['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Electrical Engineering']]]]]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notice.create']);

        // Draft: nobody sees it.
        Sanctum::actingAs($this->students[5]->user);
        $this->assertSame([], $this->studentIds('/api/student/notices'));

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'notice.publish']);
        Mail::assertNotQueued(BroadcastMail::class);

        Sanctum::actingAs($this->students[5]->user);
        $response = $this->getJson('/api/student/notices')->assertOk();
        $this->assertSame([$notice->id], collect($response->json('notices'))->pluck('id')->all());
        $this->assertSame(1, $response->json('unread_count'));
        $this->assertFalse($response->json('notices.0.is_read'));

        $this->postJson("/api/student/notices/{$notice->id}/read")->assertOk();
        $this->assertSame(0, $this->getJson('/api/student/notices')->json('unread_count'));

        Sanctum::actingAs($this->students[0]->user);
        $this->assertSame([], $this->studentIds('/api/student/notices'));
        $this->postJson("/api/student/notices/{$notice->id}/read")->assertNotFound();
    }

    public function test_cycle_and_applicant_audiences(): void
    {
        $cycleNotice = $this->createNotice([['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]]);
        $applicantNotice = $this->createNotice([['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]]]);
        $this->postJson("/api/admin/notices/{$cycleNotice->id}/publish")->assertOk();
        $this->postJson("/api/admin/notices/{$applicantNotice->id}/publish")->assertOk();
        $this->assertSame($this->posting->id, $applicantNotice->fresh()->job_posting_id);

        Sanctum::actingAs($this->students[4]->user); // enrolled, not applied
        $this->assertSame([$cycleNotice->id], $this->studentIds('/api/student/notices'));
        Sanctum::actingAs($this->students[0]->user);
        $this->assertEqualsCanonicalizing([$cycleNotice->id, $applicantNotice->id], $this->studentIds('/api/student/notices'));
        Sanctum::actingAs($this->students[5]->user);
        $this->assertSame([], $this->studentIds('/api/student/notices'));
    }

    public function test_stage_audience_targets_only_published_shortlisted_or_on_hold(): void
    {
        $notice = $this->createNotice([['audience_type' => 'round_results', 'audience_filter' => ['posting_round_id' => $this->round->id, 'results' => ['selected', 'waitlisted', 'rejected']]]]);
        // "rejected" is not targetable and is dropped.
        $this->assertSame(['selected', 'waitlisted'], $notice->audiences()->first()->audience_filter['results']);
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();

        $ids = $notice->fresh()->audienceQuery()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->students[0]->id, $this->students[1]->id], $ids); // never the draft (2) or rejected (3)

        Sanctum::actingAs($this->students[2]->user);
        $this->assertSame([], $this->studentIds('/api/student/notices'));
        Sanctum::actingAs($this->students[1]->user);
        $this->assertSame([$notice->id], $this->studentIds('/api/student/notices'));
    }

    public function test_publish_with_email_goes_in_bcc_batches_and_is_logged(): void
    {
        config(['mail.bulk_batch_size' => 2]);
        $notice = $this->createNotice([['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]]);
        $this->postJson("/api/admin/notices/{$notice->id}/publish", ['send_email' => true])->assertOk();

        Mail::assertQueued(BroadcastMail::class, 3); // 5 students, batches of 2
        foreach (array_slice($this->students, 0, 5) as $s) {
            Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($s->user->email) && ! $m->hasTo($s->user->email));
        }
        Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[5]->user->email));
        $this->assertSame(5, EmailLog::where('kind', 'notice')->count());
        $this->assertNotNull($notice->fresh()->emailed_at);
    }

    public function test_attachment_is_pdf_up_to_5mb_and_only_the_audience_downloads_it(): void
    {
        $notice = $this->createNotice([['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]]]);

        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('x.docx', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('schedule.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('schedule.pdf', $notice->fresh()->attachment_name);
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();

        Sanctum::actingAs($this->students[0]->user);
        $this->get("/api/student/notices/{$notice->id}/attachment")->assertOk();
        Sanctum::actingAs($this->students[4]->user);
        $this->getJson("/api/student/notices/{$notice->id}/attachment")->assertNotFound();
    }

    public function test_stage_email_targets_published_decisions_only_in_bcc(): void
    {
        $base = "/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}";
        $this->getJson("{$base}/message-audience")->assertOk()->assertJson(['selected' => 1, 'waitlisted' => 1]);

        $this->post("{$base}/email", ['subject' => 'Test venue', 'message' => '<p>Report at 9</p>', 'results' => ['selected'], 'attachment' => UploadedFile::fake()->create('a.pdf', 6000, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->postJson("{$base}/email", ['subject' => 'Test venue', 'message' => '<p>Report at 9</p>', 'results' => ['rejected']])->assertStatus(422);

        $this->post("{$base}/email", ['subject' => 'Test venue', 'message' => '<p>Report at 9</p>', 'results' => ['selected', 'waitlisted'], 'attachment' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk();

        Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[0]->user->email) && $m->hasBcc($this->students[1]->user->email)
            && ! $m->hasTo($this->students[0]->user->email) && $m->attachmentName === 'a.pdf');
        foreach ([2, 3, 4, 5] as $i) {
            Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[$i]->user->email));
        }
        $this->assertSame(2, EmailLog::where('kind', 'stage_email')->where('job_posting_id', $this->posting->id)->count());
        $this->assertTrue(AuditLog::where('action', 'stage.email')->exists());
    }

    public function test_stage_email_with_no_published_decisions_is_refused(): void
    {
        ApplicationRoundResult::query()->update(['published_at' => null]);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", ['subject' => 'x', 'message' => 'Hello', 'results' => ['selected', 'waitlisted']])
            ->assertStatus(422);
        Mail::assertNotQueued(BroadcastMail::class);
    }

    public function test_companies_and_students_cannot_use_admin_routes_and_companies_see_no_notices(): void
    {
        $notice = $this->createNotice([['audience_type' => 'all']]);
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();

        foreach ([$this->companyUser, $this->students[0]->user] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/notices')->assertForbidden();
            $this->postJson('/api/admin/notices', ['title' => 'x', 'audiences' => []])->assertForbidden();
            $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertForbidden();
            $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", [])->assertForbidden();
        }

        Sanctum::actingAs($this->companyUser);
        $this->getJson('/api/student/notices')->assertForbidden();
        $this->getJson('/api/company/notices')->assertNotFound();
    }

    public function test_publishing_twice_or_without_audience_is_refused_and_delete_is_audited(): void
    {
        $empty = $this->createNotice([]);
        $this->postJson("/api/admin/notices/{$empty->id}/publish")->assertStatus(422);

        $notice = $this->createNotice([['audience_type' => 'all']]);
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertStatus(422);

        $this->deleteJson("/api/admin/notices/{$notice->id}")->assertOk();
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notice.delete']);
    }
}
