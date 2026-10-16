<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\AuditLog;
use App\Models\StaffRole;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Support\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Staff roles: super admins build roles from the permission catalogue, assign one per admin, tick or untick single
 * permissions per person, turn a role off, move it to someone else, and invite staff by name + email + role.
 */
class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true, 'name' => 'Super Admin']);
    }

    private function role(string $name, array $levels): StaffRole
    {
        Sanctum::actingAs($this->super);
        $id = $this->postJson('/api/admin/roles', ['name' => $name, 'levels' => $levels])->assertCreated()->json('role.id');

        return StaffRole::findOrFail($id);
    }

    private function staff(?StaffRole $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => 'admin', 'staff_role_id' => $role?->id] + $attributes);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    public function test_every_admin_route_has_a_permission_rule(): void
    {
        $missing = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/admin/'))
            ->flatMap(fn (Route $r) => collect(array_diff($r->methods(), ['HEAD']))->map(fn ($m) => [$m, $r->uri()]))
            ->reject(fn ($pair) => StaffPermissions::ruleFor($pair[0], $pair[1]) !== null)
            ->map(fn ($pair) => implode(' ', $pair))
            ->values()->all();

        $this->assertSame([], $missing, 'Admin routes without a StaffPermissions rule: '.implode(', ', $missing));

        // Every permission named in a rule exists in the catalogue at that level.
        foreach (StaffPermissions::RULES as [, $pattern, $permission, $level]) {
            foreach ((array) $permission as $key) {
                if (in_array($key, [StaffPermissions::SUPER, StaffPermissions::ANY], true)) {
                    continue;
                }
                $this->assertTrue(StaffPermissions::allows($key, $level), "Rule {$pattern}: {$key} has no level {$level}");
            }
        }
    }

    public function test_a_role_limits_what_its_holder_can_read_and_write(): void
    {
        $viewer = $this->staff($this->role('Student viewer', ['students' => 'read', 'resumes' => 'verify']));

        $this->as($viewer)->getJson('/api/admin/students')->assertOk();
        $this->getJson('/api/admin/resumes')->assertOk();
        $this->postJson('/api/admin/students', [])->assertForbidden()->assertJsonPath('permission', 'students');
        $this->getJson('/api/admin/postings')->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();
        // Lookup lists other pages need stay open to every admin.
        $this->getJson('/api/admin/placement-cycles')->assertOk();
        // Users and roles are super admin only, also for Full Access.
        $this->getJson('/api/admin/roles')->assertForbidden();
        $this->as($this->staff(StaffRole::fullAccess()))->getJson('/api/admin/manage-admins')->assertForbidden();
        $this->getJson('/api/admin/settings')->assertOk();

        // No role: signs in, opens nothing.
        $this->as($this->staff(null))->getJson('/api/admin/students')->assertForbidden();
        $this->getJson('/api/admin/me/access')->assertOk()->assertJsonPath('access.enabled', false);
    }

    public function test_role_editing_applies_to_every_holder_and_roles_in_use_cannot_be_deleted(): void
    {
        $role = $this->role('Coordinator', ['students' => 'read']);
        $person = $this->staff($role);
        $this->as($person)->getJson('/api/admin/notices')->assertForbidden();

        $this->as($this->super)->patchJson("/api/admin/roles/{$role->id}", ['levels' => ['students' => 'read', 'notices' => 'read']])->assertOk();
        $this->as($person)->getJson('/api/admin/notices')->assertOk();

        $this->as($this->super)->deleteJson("/api/admin/roles/{$role->id}")->assertStatus(422);
        $this->deleteJson('/api/admin/roles/'.StaffRole::fullAccess()->id)->assertStatus(422);
        $this->postJson("/api/admin/roles/{$role->id}/duplicate")->assertCreated()->assertJsonPath('role.name', 'Coordinator (copy)');
        $this->patchJson("/api/admin/roles/{$role->id}", ['levels' => ['students' => 'write', 'profile_verification' => 'read']])
            ->assertStatus(422);
        $this->postJson('/api/admin/roles', ['name' => 'Coordinator'])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(1, AuditLog::where('action', 'role.update')->count());
    }

    public function test_ticks_and_unticks_for_one_person_override_their_role(): void
    {
        $role = $this->role('Officer', ['students' => 'write', 'notices' => 'write']);
        $person = $this->staff($role);

        $this->as($this->super)->putJson("/api/admin/manage-admins/{$person->id}/access", ['levels' => ['notices' => 'none', 'surveys' => 'read', 'students' => 'write']])
            ->assertOk()
            ->assertJsonPath('access.sources.notices', 'removed')
            ->assertJsonPath('access.sources.surveys', 'added')
            ->assertJsonPath('access.sources.students', 'role');
        $this->assertSame(2, UserPermissionOverride::where('user_id', $person->id)->count(), 'a level equal to the role keeps no override');

        $this->as($person)->getJson('/api/admin/notices')->assertForbidden();
        $this->getJson('/api/admin/surveys')->assertOk();

        $this->as($this->super)->putJson("/api/admin/manage-admins/{$person->id}/access", ['reset' => true])->assertOk();
        $this->as($person)->getJson('/api/admin/notices')->assertOk();
        $this->getJson('/api/admin/surveys')->assertForbidden();
    }

    public function test_turning_a_role_off_and_moving_it_to_someone_else(): void
    {
        $role = $this->role('Officer', ['students' => 'read']);
        $from = $this->staff($role);
        $to = $this->staff(null);

        $this->as($this->super)->patchJson("/api/admin/manage-admins/{$from->id}/role-status", ['enabled' => false])->assertOk();
        $this->as($from)->getJson('/api/admin/students')->assertForbidden();
        $this->as($this->super)->patchJson("/api/admin/manage-admins/{$from->id}/role-status", ['enabled' => true])->assertOk();
        $this->as($from)->getJson('/api/admin/students')->assertOk();

        $this->as($this->super)->putJson("/api/admin/manage-admins/{$from->id}/access", ['levels' => ['notices' => 'read']])->assertOk();
        $this->postJson("/api/admin/manage-admins/{$from->id}/transfer-role", ['to_user_id' => $to->id, 'copy_overrides' => true])->assertOk();

        $this->assertNull($from->fresh()->staff_role_id);
        $this->assertSame($role->id, $to->fresh()->staff_role_id);
        $this->as($from)->getJson('/api/admin/students')->assertForbidden();
        $this->as($to)->getJson('/api/admin/students')->assertOk();
        $this->getJson('/api/admin/notices')->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.role_transfer')->count());
    }

    public function test_a_department_limited_person_sees_only_their_departments_students(): void
    {
        $mining = \App\Models\StudentProfile::factory()->create(['roll_no' => '23XY7001', 'branch' => 'Mining Engineering']);
        $cse = \App\Models\StudentProfile::factory()->create(['roll_no' => '23XY7002', 'branch' => 'Computer Science & Engineering']);
        $mining->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/m.pdf', 'file_size' => 1, 'status' => 'pending']);
        $cseResume = $cse->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/c.pdf', 'file_size' => 1, 'status' => 'pending']);

        $fic = $this->staff($this->role('Dept FIC', ['students' => 'read', 'resumes' => 'read', 'branch_changes' => 'read']));
        $this->as($this->super)->putJson("/api/admin/manage-admins/{$fic->id}/departments", ['departments' => ['Mining Engineering']])->assertOk();
        $this->putJson("/api/admin/manage-admins/{$fic->id}/departments", ['departments' => ['Underwater Basket Weaving']])->assertStatus(422);

        $cse->user->forceFill(['invited_at' => now(), 'activated_at' => now()])->save();
        $this->as($fic);
        $list = $this->getJson('/api/admin/students')->assertOk();
        $this->assertSame(['23XY7001'], array_column($list->json('students'), 'roll_no'));
        // The header counts leave other departments out too.
        $list->assertJsonPath('invitation_summary.invited', 0)->assertJsonPath('invitation_summary.registered', 0);
        $this->getJson("/api/admin/students/{$mining->id}")->assertOk();
        $this->getJson("/api/admin/students/{$cse->id}")->assertNotFound();
        $this->getJson("/api/admin/students/{$cse->id}/notes")->assertNotFound();
        $this->getJson("/api/admin/resumes/{$cseResume->id}/file")->assertNotFound();
        $this->assertSame(['23XY7001'], array_column(array_column($this->getJson('/api/admin/resumes')->assertOk()->json('resumes'), 'student_profile'), 'roll_no'));
        $this->getJson('/api/admin/students/pending-requests')->assertOk()->assertJsonPath('resumes', 1);
        // Read only.
        $this->patchJson("/api/admin/students/{$mining->id}", ['phone' => '9000000000'])->assertForbidden();

        // Clearing the limit shows every department again.
        $this->as($this->super)->putJson("/api/admin/manage-admins/{$fic->id}/departments", ['departments' => null])->assertOk();
        $this->as($fic)->getJson("/api/admin/students/{$cse->id}")->assertOk();
    }

    public function test_the_demo_seeder_builds_company_admin_and_one_fic_per_department(): void
    {
        $this->seed(\Database\Seeders\StaffRolesDemoSeeder::class);
        $this->seed(\Database\Seeders\StaffRolesDemoSeeder::class); // safe to re-run

        $companyAdmin = User::where('email', 'company.admin@cdc-demo.test')->sole();
        $this->as($companyAdmin)->getJson('/api/admin/jnfs')->assertOk();
        $this->getJson('/api/admin/companies')->assertOk();
        $this->getJson('/api/admin/students')->assertForbidden();

        $btech = \App\Support\ProgrammeCatalogue::branchesFor('B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)');
        $this->assertSame(count($btech), User::where('email', 'like', 'fic.%@cdc-demo.test')->count());
        $this->assertSame(0, \App\Models\StudentProfile::where('roll_no', 'not like', '23XY%')->count());

        $fic = User::where('email', 'fic.mining-engineering@cdc-demo.test')->sole();
        $rows = $this->as($fic)->getJson('/api/admin/students')->assertOk()->json('students');
        $this->assertCount(3, $rows);
        $this->assertSame(['Mining Engineering'], array_values(array_unique(array_column($rows, 'branch'))));
        $this->getJson('/api/admin/jnfs')->assertForbidden();
    }

    public function test_nobody_changes_their_own_or_a_super_admins_access(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);

        $this->as($this->super)->patchJson("/api/admin/manage-admins/{$this->super->id}/role", ['staff_role_id' => null])->assertStatus(422);
        $this->patchJson("/api/admin/manage-admins/{$other->id}/role-status", ['enabled' => false])->assertStatus(422);

        $person = $this->staff(StaffRole::fullAccess());
        $this->as($person)->putJson("/api/admin/manage-admins/{$person->id}/access", ['levels' => ['settings' => 'write']])->assertForbidden();
    }

    public function test_invitation_by_name_email_and_role_then_the_person_completes_their_profile(): void
    {
        $role = $this->role('Student Admin', ['students' => 'write', 'resumes' => 'verify']);

        $this->as($this->super)->postJson('/api/admin/manage-admins', ['name' => 'Asha Verma', 'email' => 'asha.verma@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('staff_role_id');
        $this->postJson('/api/admin/manage-admins', ['name' => 'Asha Verma', 'email' => 'asha.verma@example.com', 'staff_role_id' => $role->id])
            ->assertCreated()
            ->assertJsonPath('user.staff_role.name', 'Student Admin')
            ->assertJsonPath('user.invitation_status', 'sent')
            ->assertJsonPath('user.profile_completed', false);

        $sent = fn (StaffInvitationMail $mail) => $mail->roleName === 'Student Admin'
            && collect($mail->permissions)->flatMap(fn ($g) => array_column($g['items'], 'label'))->contains('Resumes')
            && str_contains($mail->setPasswordUrl, '/auth/staff/set-password?');
        $this->assertTrue(Mail::sent(StaffInvitationMail::class, $sent)->isNotEmpty() || Mail::queued(StaffInvitationMail::class, $sent)->isNotEmpty());
        $this->assertStringContainsString('Read and verify', (new StaffInvitationMail('Asha', 'a@example.com', 'Super', 'Student Admin', null, false, [['group' => 'Students', 'items' => [['label' => 'Resumes', 'level' => 'Read and verify']]]], 'https://x', 'https://y'))->render());

        // The person sets a password from the link: the invitation is accepted.
        $asha = User::where('email', 'asha.verma@example.com')->sole();
        $token = Password::broker('invites')->createToken($asha);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => $asha->email, 'password' => 'Placement@4821', 'password_confirmation' => 'Placement@4821'])->assertOk();
        $this->assertNotNull($asha->fresh()->activated_at);

        // Until the profile is complete, only their own access and profile open.
        $this->as($asha->fresh())->getJson('/api/admin/students')->assertForbidden()->assertJsonPath('profile_incomplete', true);
        $this->getJson('/api/admin/me/access')->assertOk()->assertJsonPath('access.profile_completed', false);
        $this->putJson('/api/admin/me/profile', ['first_name' => 'Asha', 'designation' => 'Student Admin'])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $this->putJson('/api/admin/me/profile', ['first_name' => 'Asha', 'last_name' => 'Verma', 'designation' => 'Student Admin', 'mobile' => '+91 9876543210'])->assertOk();
        $this->as($asha->fresh())->getJson('/api/admin/students')->assertOk();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->assertSame(1, AuditLog::where('action', 'admin.profile_complete')->count());

        $this->as($this->super)->postJson("/api/admin/manage-admins/{$asha->id}/resend-invitation")->assertStatus(422);
    }
}
