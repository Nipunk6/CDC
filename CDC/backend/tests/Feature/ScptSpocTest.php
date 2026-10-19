<?php

namespace Tests\Feature;

use App\Mail\SpocAssignmentMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\JobPostingSpoc;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\ScptMember;
use App\Models\StaffRole;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P10.1 SCPT registry and P10.2 SPOC assignment (owner decisions 2026-10-19, P3-D50): membership per cycle, several
 * SPOCs per job profile, eligibility shown as a warning, a mail on assign / remove / HR change, SPOC contact shown to
 * students, HR details only to the SPOC.
 */
class ScptSpocTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private Company $company;

    private PlacementCycle $cycle;

    private PlacementCycle $otherCycle;

    private JobPosting $posting;

    /** @var array<string, StudentProfile> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->company = Company::create([
            'name' => 'Acme', 'hr_name' => 'Riya Sen', 'hr_designation' => 'Talent Lead', 'hr_email' => 'riya@acme.test', 'hr_phone' => '9811111111',
        ]);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $this->company->id, 'email' => 'riya@acme.test']);

        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027, 2028]]]];
        $this->cycle = PlacementCycle::create(['name' => 'FT 2026-27', 'type' => 'fulltime'] + $base);
        $this->otherCycle = PlacementCycle::create(['name' => 'Intern 2026-27', 'type' => 'internship'] + $base);

        $jnf = Jnf::create([
            'company_id' => $this->company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        // eligible: CSE, enrolled. pre-final: CSE but a 2028 junior who is not enrolled. lowcgpa: CSE, enrolled, below the cut-off.
        // student: an ordinary student, not on any SCPT.
        $this->s['eligible'] = $this->student('22JE0001', ['full_name' => 'Aman Gupta', 'phone' => '9000000001'], true);
        $this->s['prefinal'] = $this->student('23JE0002', ['full_name' => 'Bela Roy', 'phone' => '9000000002', 'graduating_batch' => 2028], false);
        $this->s['lowcgpa'] = $this->student('22JE0003', ['full_name' => 'Chirag Das', 'phone' => '9000000003', 'current_cgpa' => 6.5], true);
        $this->s['student'] = $this->student('22JE0004', ['full_name' => 'Divya Iyer'], true);

        $this->as($this->admin)->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();
        Mail::fake(); // forget the opening mails
    }

    private function student(string $roll, array $attributes, bool $enrolled): StudentProfile
    {
        $student = StudentProfile::factory()->create(['roll_no' => $roll] + $attributes);
        if ($enrolled) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }

        return $student;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    private function addToScpt(array $rolls, ?PlacementCycle $cycle = null): void
    {
        $this->as($this->admin)->postJson('/api/admin/scpt', ['placement_cycle_id' => ($cycle ?? $this->cycle)->id, 'roll_nos' => $rolls])->assertOk();
    }

    private function member(string $key): ScptMember
    {
        return ScptMember::query()->where('placement_cycle_id', $this->cycle->id)->where('student_profile_id', $this->s[$key]->id)->sole();
    }

    /** @return \Illuminate\Support\Collection<int, SpocAssignmentMail> */
    private function spocMails(string $kind)
    {
        return Mail::queued(SpocAssignmentMail::class, fn (SpocAssignmentMail $m) => $m->kind === $kind)
            ->merge(Mail::sent(SpocAssignmentMail::class, fn (SpocAssignmentMail $m) => $m->kind === $kind));
    }

    public function test_scpt_members_are_added_per_cycle_listed_updated_and_removed_with_audit(): void
    {
        $this->as($this->admin)->postJson('/api/admin/scpt', [
            'placement_cycle_id' => $this->cycle->id, 'roll_nos' => ['22je0001', '23JE0002', 'NOPE9'], 'designation' => 'Coordinator',
        ])->assertOk()->assertJsonPath('added', 2)->assertJsonPath('errors.0.roll_no', 'NOPE9');
        // The same roll again is not added twice; the same student may be on another cycle's SCPT.
        $this->postJson('/api/admin/scpt', ['placement_cycle_id' => $this->cycle->id, 'roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('added', 0);
        $this->addToScpt(['22JE0001'], $this->otherCycle);
        $this->assertSame(3, ScptMember::count());

        $list = $this->getJson("/api/admin/scpt?placement_cycle_id={$this->cycle->id}")->assertOk()->json('members');
        $this->assertSame(['22JE0001', '23JE0002'], array_column(array_column($list, 'student'), 'roll_no'));
        $this->assertSame('Coordinator', $list[0]['designation']);
        $this->getJson('/api/admin/scpt')->assertStatus(422);

        $member = $this->member('eligible');
        $this->patchJson("/api/admin/scpt/{$member->id}", ['designation' => 'Head'])->assertOk()->assertJsonPath('member.designation', 'Head');
        $this->deleteJson("/api/admin/scpt/{$member->id}")->assertOk();
        $this->assertSame(2, ScptMember::count());

        $actions = AuditLog::where('user_id', $this->admin->id)->pluck('action')->all();
        $this->assertContains('scpt.add', $actions);
        $this->assertContains('scpt.update', $actions);
        $this->assertContains('scpt.remove', $actions);
    }

    public function test_candidates_list_the_cycle_scpt_with_eligibility_as_a_warning(): void
    {
        $this->addToScpt(['22JE0001', '23JE0002', '22JE0003']);
        $this->addToScpt(['22JE0004'], $this->otherCycle); // another cycle's SCPT is not offered

        $candidates = collect($this->as($this->admin)->getJson("/api/admin/postings/{$this->posting->id}/spocs")->assertOk()->json('candidates'))
            ->keyBy('student.roll_no');

        $this->assertSame(['22JE0001', '22JE0003', '23JE0002'], $candidates->keys()->sort()->values()->all());
        $this->assertTrue($candidates['22JE0001']['eligible']);
        $this->assertFalse($candidates['22JE0003']['eligible']);
        $this->assertNotEmpty($candidates['22JE0003']['reasons']);
        $this->assertFalse($candidates['23JE0002']['eligible']);
        $this->assertNull($candidates['22JE0001']['spoc_id']);

        // An eligible member can still be chosen: the eligibility is only a warning.
        $this->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$this->member('eligible')->id]])->assertOk()->assertJsonPath('added', 1);
    }

    public function test_assigning_several_spocs_mails_each_with_the_current_hr_details(): void
    {
        $this->addToScpt(['22JE0001', '23JE0002']);
        $ids = [$this->member('eligible')->id, $this->member('prefinal')->id];

        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => $ids])->assertOk()->assertJsonPath('added', 2);
        $this->assertSame(2, $this->posting->spocs()->count());

        $mails = $this->spocMails('assigned');
        $this->assertCount(2, $mails);
        $mail = $mails->first();
        $this->assertSame('Acme', $mail->companyName);
        $this->assertSame('SDE', $mail->role);
        $this->assertSame('riya@acme.test', $mail->hr['email']);
        $this->assertSame('9811111111', $mail->hr['phone']);
        $this->assertStringContainsString('riya@acme.test', $mail->render());
        $this->assertSame(2, PortalNotification::where('category', 'spoc')->count());
        $this->assertTrue(AuditLog::where('action', 'spoc.assign')->exists());

        // Assigning the same people again changes nothing and mails nobody.
        Mail::fake();
        $this->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => $ids])->assertOk()->assertJsonPath('added', 0);
        $this->assertCount(0, $this->spocMails('assigned'));

        // Only this cycle's SCPT may be SPOC.
        $this->addToScpt(['22JE0004'], $this->otherCycle);
        $foreign = ScptMember::where('placement_cycle_id', $this->otherCycle->id)->sole();
        $this->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$foreign->id]])->assertStatus(422);
    }

    public function test_students_see_spoc_contact_on_the_job_profile_and_only_the_spoc_sees_hr_details(): void
    {
        $this->addToScpt(['22JE0001']);
        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$this->member('eligible')->id]])->assertOk();

        // Any student who can see the job profile sees the SPOC's name, roll, phone and email, never the HR contact.
        $response = $this->as($this->s['student']->user)->getJson("/api/student/postings/{$this->posting->id}")->assertOk();
        $response->assertJsonPath('posting.spocs.0.name', 'Aman Gupta')
            ->assertJsonPath('posting.spocs.0.roll_no', '22JE0001')
            ->assertJsonPath('posting.spocs.0.phone', '9000000001')
            ->assertJsonPath('posting.spocs.0.email', $this->s['eligible']->institute_email);
        $this->assertStringNotContainsString('riya@acme.test', $response->getContent());
        $this->assertStringNotContainsString('9811111111', $response->getContent());

        // The SPOC's own tab lists the company, role and HR details.
        $this->as($this->s['eligible']->user)->getJson('/api/student/spoc')->assertOk()
            ->assertJsonPath('is_scpt', true)
            ->assertJsonPath('assignments.0.company.name', 'Acme')
            ->assertJsonPath('assignments.0.role', 'SDE')
            ->assertJsonPath('assignments.0.hr.name', 'Riya Sen')
            ->assertJsonPath('assignments.0.hr.email', 'riya@acme.test')
            ->assertJsonPath('assignments.0.hr.phone', '9811111111');

        // A student who is not on any SCPT gets an empty tab.
        $this->as($this->s['student']->user)->getJson('/api/student/spoc')->assertOk()
            ->assertExactJson(['is_scpt' => false, 'memberships' => [], 'assignments' => []]);

        // An SCPT member without assignments sees an empty list but is still SCPT.
        $this->addToScpt(['23JE0002']);
        $this->as($this->s['prefinal']->user)->getJson('/api/student/spoc')->assertOk()
            ->assertJsonPath('is_scpt', true)->assertJsonPath('assignments', []);
    }

    public function test_the_company_sees_its_spocs_name_phone_and_email_only(): void
    {
        $this->as($this->companyUser)->getJson("/api/company/postings/{$this->posting->id}")->assertOk()->assertJsonPath('posting.spocs', []);

        $this->addToScpt(['22JE0001']);
        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$this->member('eligible')->id]])->assertOk();

        $response = $this->as($this->companyUser)->getJson("/api/company/postings/{$this->posting->id}")->assertOk();
        $this->assertSame(
            [['name' => 'Aman Gupta', 'phone' => '9000000001', 'email' => $this->s['eligible']->institute_email]],
            $response->json('posting.spocs')
        );
        $this->assertStringNotContainsString('22JE0001', $response->getContent());
    }

    public function test_removing_a_spoc_or_the_member_mails_a_removal(): void
    {
        $this->addToScpt(['22JE0001', '23JE0002']);
        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$this->member('eligible')->id, $this->member('prefinal')->id]])->assertOk();
        Mail::fake();

        $spoc = JobPostingSpoc::where('scpt_member_id', $this->member('eligible')->id)->sole();
        $this->deleteJson("/api/admin/postings/{$this->posting->id}/spocs/{$spoc->id}")->assertOk();
        $this->assertCount(1, $this->spocMails('removed'));
        $this->assertTrue(AuditLog::where('action', 'spoc.remove')->exists());

        // Removing an SCPT member who is SPOC ends the assignment and tells them too.
        $this->deleteJson("/api/admin/scpt/{$this->member('prefinal')->id}")->assertOk();
        $this->assertSame(0, JobPostingSpoc::count());
        $this->assertCount(2, $this->spocMails('removed'));
    }

    public function test_spocs_are_mailed_again_when_the_hr_contact_changes(): void
    {
        $this->addToScpt(['22JE0001']);
        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [$this->member('eligible')->id]])->assertOk();
        Mail::fake();

        // Admin edit of the HR contact.
        $this->putJson("/api/admin/companies/{$this->company->id}", ['name' => 'Acme', 'hr_name' => 'Karan Mehta', 'hr_email' => 'karan@gmail.com', 'hr_phone' => '9822222222'])->assertOk();
        $mails = $this->spocMails('hr_updated');
        $this->assertCount(1, $mails);
        $this->assertSame('Karan Mehta', $mails->first()->hr['name']);
        $this->assertSame('karan@gmail.com', $mails->first()->hr['email']);

        // An edit that leaves the HR contact as it is mails nobody.
        Mail::fake();
        $this->putJson("/api/admin/companies/{$this->company->id}", ['name' => 'Acme Ltd', 'hr_name' => 'Karan Mehta', 'hr_email' => 'karan@gmail.com', 'hr_phone' => '9822222222'])->assertOk();
        $this->assertCount(0, $this->spocMails('hr_updated'));

        // SPOCs of a cancelled job profile are not told.
        $this->posting->update(['status' => 'cancelled']);
        $this->putJson("/api/admin/companies/{$this->company->id}", ['name' => 'Acme Ltd', 'hr_name' => 'Neha Rao', 'hr_email' => 'neha@gmail.com'])->assertOk();
        $this->assertCount(0, $this->spocMails('hr_updated'));
    }

    public function test_staff_without_the_scpt_permission_are_refused(): void
    {
        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $roleId = $this->as($super)->postJson('/api/admin/roles', ['name' => 'Readers', 'levels' => ['scpt' => 'read', 'job_profiles' => 'read']])->assertCreated()->json('role.id');
        $reader = User::factory()->create(['role' => 'admin', 'staff_role_id' => $roleId]);
        $none = User::factory()->create(['role' => 'admin', 'staff_role_id' => StaffRole::create(['name' => 'Nothing', 'slug' => 'nothing'])->id]);

        $this->as($reader)->getJson("/api/admin/scpt?placement_cycle_id={$this->cycle->id}")->assertOk();
        $this->getJson("/api/admin/postings/{$this->posting->id}/spocs")->assertOk();
        $this->postJson('/api/admin/scpt', ['placement_cycle_id' => $this->cycle->id, 'roll_nos' => ['22JE0001']])->assertForbidden();
        $this->postJson("/api/admin/postings/{$this->posting->id}/spocs", ['scpt_member_ids' => [1]])->assertForbidden();

        $this->as($none)->getJson("/api/admin/scpt?placement_cycle_id={$this->cycle->id}")->assertForbidden();
        $this->as($this->s['student']->user)->getJson("/api/admin/scpt?placement_cycle_id={$this->cycle->id}")->assertForbidden();
    }
}
