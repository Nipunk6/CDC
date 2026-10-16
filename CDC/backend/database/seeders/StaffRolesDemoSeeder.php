<?php

namespace Database\Seeders;

use App\Models\CycleEnrollment;
use App\Models\PlacementCycle;
use App\Models\StaffRole;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StaffAccessService;
use App\Services\StudentAccountService;
use App\Support\ProgrammeCatalogue;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Staff roles demo (local development only). Writes rows directly; sends no mail.
 *
 *   php artisan db:seed --class=StaffRolesDemoSeeder
 *
 * Roles:  "Company Admin" (everything under Companies & Forms) and "Dept FIC" (read-only students of their department).
 * Logins (password Staff@2026):
 *   company.admin@cdc-demo.test            Company Admin
 *   fic.<department>@cdc-demo.test         Dept FIC limited to that B.Tech department (e.g. fic.mining-engineering@…)
 * Students: every department gets at least three demo students (23XY roll numbers, password Student@2026).
 * Safe to re-run: existing roles, users and students are kept.
 */
class StaffRolesDemoSeeder extends Seeder
{
    private const BTECH = 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)';

    private const PASSWORD = 'Staff@2026';

    public function run(StaffAccessService $access, StudentAccountService $accounts): void
    {
        $companyAdmin = $this->role($access, 'Company Admin', 'Runs the Companies & Forms section: JNF and INF reviews, companies, policy documents, alumni outreach.', 'info', [
            'form_reviews' => 'write', 'companies' => 'write', 'policy_documents' => 'write', 'alumni_outreach' => 'read',
        ]);
        $fic = $this->role($access, 'Dept FIC', 'Faculty-in-Charge of a department: sees the students, resumes and branch-change requests of their own department only. Read only.', 'success', [
            'students' => 'read', 'resumes' => 'read', 'branch_changes' => 'read',
        ]);

        $this->staff('company.admin@cdc-demo.test', 'Demo Company Admin', 'Company Relations Officer', $companyAdmin, null);

        $options = $access->departmentOptions();
        foreach (ProgrammeCatalogue::branchesFor(self::BTECH) as $department) {
            // The same department under another programme's spelling ("&" vs "and") belongs to the same FIC.
            $same = array_values(array_filter($options, fn ($b) => self::key($b) === self::key($department)));
            $this->staff('fic.'.Str::slug($department).'@cdc-demo.test', 'FIC '.$department, 'Faculty-in-Charge, '.$department, $fic, $same ?: [$department]);
            $this->students($accounts, $department, 3);
        }

        $this->command?->info('Staff roles demo ready. Staff password: '.self::PASSWORD);
    }

    /** @param  array<string, string>  $levels */
    private function role(StaffAccessService $access, string $name, string $description, string $color, array $levels): StaffRole
    {
        $role = StaffRole::query()->firstOrCreate(['name' => $name], ['description' => $description, 'color' => $color]);
        if ($role->wasRecentlyCreated) {
            $access->setRoleLevels($role, $levels);
        }

        return $role;
    }

    /** @param  list<string>|null  $departments */
    private function staff(string $email, string $name, string $designation, StaffRole $role, ?array $departments): void
    {
        $words = explode(' ', $name, 2);
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'first_name' => $words[0],
            'last_name' => $words[1] ?? null,
            'designation' => $designation,
            'mobile' => '+91 9'.str_pad((string) (abs(crc32($email)) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
            'password' => Hash::make(self::PASSWORD),
            'role' => 'admin',
            'is_super_admin' => false,
            'is_active' => true,
            'staff_role_id' => $role->id,
            'staff_role_enabled' => true,
            'staff_departments' => $departments,
            'profile_completed_at' => now(),
        ]);
        if ($user->activated_at === null) {
            $user->forceFill(['activated_at' => now()])->save(); // no invitation to accept: the demo password works
        }
    }

    /** Top a B.Tech department up to `$minimum` demo students. */
    private function students(StudentAccountService $accounts, string $branch, int $minimum): void
    {
        $have = StudentProfile::query()->where('programme', self::BTECH)->where('branch', $branch)->count();
        if ($have >= $minimum) {
            return;
        }

        $faker = FakerFactory::create('en_IN');
        $faker->seed(crc32($branch));
        $password = Hash::make('Student@2026');
        $cycles = PlacementCycle::query()->whereIn('name', ['Full Time 2026-27', 'Internship 2026-27'])->pluck('id');

        for ($i = $have; $i < $minimum; $i++) {
            $next = (int) StudentProfile::query()->where('roll_no', 'like', '23XY%')->max(\Illuminate\Support\Facades\DB::raw('CAST(SUBSTRING(roll_no, 5) AS UNSIGNED)'));
            $roll = sprintf('23XY%04d', max($next + 1, 1001));
            $gender = $faker->randomElement(['male', 'female']);
            $name = $faker->firstName($gender).' '.$faker->lastName();

            $student = $accounts->create([
                'roll_no' => $roll,
                'full_name' => $name,
                'institute_email' => strtolower($roll).'@students.cdc-demo.test',
                'personal_email' => Str::slug($name, '.').'.'.strtolower($roll).'@personal.cdc-demo.test',
                'phone' => '9'.$faker->numerify('#########'),
                'programme' => self::BTECH,
                'branch' => $branch,
                'graduating_batch' => 2027,
                'gender' => $gender,
                'current_cgpa' => $faker->randomFloat(2, 6.2, 9.6),
                'tenth_percent' => $faker->randomFloat(2, 78, 99),
                'twelfth_percent' => $faker->randomFloat(2, 72, 98),
                'home_state' => $faker->randomElement(['Jharkhand', 'Bihar', 'Uttar Pradesh', 'Odisha', 'West Bengal', 'Rajasthan']),
            ], $password);
            foreach ($cycles as $cycleId) {
                CycleEnrollment::query()->firstOrCreate(['placement_cycle_id' => $cycleId, 'student_profile_id' => $student->id], ['status' => 'active']);
            }
        }
    }

    private static function key(string $branch): string
    {
        return strtolower(trim(str_replace('&', 'and', preg_replace('/\s+/', ' ', $branch))));
    }
}
