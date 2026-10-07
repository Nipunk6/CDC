<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 2 demo data (spec M10.4). Writes rows directly — no emails, no notifications, no queue jobs.
 *
 * Enable by uncommenting it in DatabaseSeeder, or run: php artisan db:seed --class=Phase2DemoSeeder
 *
 * Demo logins (local development only):
 *   students  roll number (e.g. 23JE0101) / Student@2026
 *   companies hr@nimbus.demo, hr@vertex.demo, hr@helix.demo / Company@2026
 *   admin     admin@cdc-demo.test / Admin@2026 (demo super admin for local browser checks, D107)
 */
class Phase2DemoSeeder extends Seeder
{
    private const BTECH = 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)';

    private const MTECH = 'M.Tech (2 Year) - GATE';

    private const MBA = 'MBA (2 Year) - CAT';

    private const BTECH_BRANCHES = [
        'Computer Science & Engineering' => 22, 'Mathematics & Computing' => 12, 'Electronics & Communication Engineering' => 14,
        'Electrical Engineering' => 12, 'Mechanical Engineering' => 12, 'Chemical Engineering' => 8, 'Civil Engineering' => 8,
        'Mining Engineering' => 6, 'Engineering Physics' => 6,
    ];

    private \Faker\Generator $faker;

    private ?int $adminId = null;

    public function run(): void
    {
        // Safe to re-run: the demo data is created once, all-or-nothing (D88).
        if (User::query()->where('email', 'hr@nimbus.demo')->exists()) {
            $this->command?->warn('Phase 2 demo data is already present — nothing to do.');

            return;
        }

        DB::transaction(fn () => $this->seedDemo());
    }

    private function seedDemo(): void
    {
        $this->faker = FakerFactory::create('en_IN');
        $this->faker->seed(2026);
        mt_srand(2026);
        User::query()->firstOrCreate(
            ['email' => 'admin@cdc-demo.test'],
            ['name' => 'Demo CDC Admin', 'password' => Hash::make('Admin@2026'), 'role' => 'admin', 'is_super_admin' => true, 'is_active' => true]
        );
        $this->adminId = User::query()->where('role', 'admin')->value('id');

        $ft = PlacementCycle::create([
            'name' => 'Full Time 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]], ['programme' => self::MTECH, 'batches' => [2027]], ['programme' => self::MBA, 'batches' => [2027]]],
            'description' => 'Main placement season for the 2027 graduating batch.', 'created_by' => $this->adminId,
        ]);
        $intern = PlacementCycle::create([
            'name' => 'Internship 2026-27', 'type' => 'internship', 'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027, 2028]]],
            'description' => 'Summer internships.', 'created_by' => $this->adminId,
        ]);

        $students = $this->students();
        foreach ($students as $student) {
            foreach ([$ft, $intern] as $cycle) {
                CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active', 'enrolled_by' => $this->adminId]);
            }
        }

        [$nimbus, $vertex, $helix] = [
            $this->company('Nimbus Systems', 'hr@nimbus.demo', 'Software', 'Ananya Rao'),
            $this->company('Vertex Analytics', 'hr@vertex.demo', 'Analytics', 'Rahul Menon'),
            $this->company('Helix Labs', 'hr@helix.demo', 'Research', 'Kavita Iyer'),
        ];

        $sde = $this->float($this->jnf($nimbus, 'Software Development Engineer', 'Bengaluru', 2400000, 10, ['aptitude_test', 'technical_interview', 'hr_interview']), $ft, now()->subDays(10), roundDays: [-9, -5, -2], questions: [
            ['question' => 'Preferred location', 'qtype' => 'mcq_single', 'options' => ['Bengaluru', 'Hyderabad', 'Pune'], 'required' => true],
            ['question' => 'Link to your best project', 'qtype' => 'text', 'required' => false],
        ]);
        $analyst = $this->float($this->jnf($vertex, 'Data Analyst', 'Gurugram', 1400000, 6, ['written_test', 'group_discussion', 'hr_interview'], 7.0), $ft, now()->addDays(6), roundDays: [8, 10, 12], questions: [
            ['question' => 'Tools you are comfortable with', 'qtype' => 'mcq_multi', 'options' => ['SQL', 'Python', 'Excel', 'Tableau'], 'required' => true],
        ]);
        $research = $this->float($this->inf($helix, 'Summer Research Intern', 'Remote', 60000, 8), $intern, now()->addDays(9), roundDays: [11, 13], questions: []);

        // Students apply to the open drives first; the SDE drive then places some of them, so their other live
        // applications in the FT cycle carry the placed-elsewhere flag, exactly as publishing would set it.
        $this->applyToOpenPosting($analyst, $students);
        $this->applyToOpenPosting($research, $students, 25);
        $this->runSdeDrive($sde, $students);
        $this->flagPlacedElsewhere($ft);
        $this->flagPlacedElsewhere($intern);

        CampusEvent::create([
            'title' => 'Nimbus Systems Pre-Placement Talk', 'event_type' => 'ppt', 'company_id' => $nimbus->id,
            'starts_at' => now()->addDays(2)->setTime(11, 30), 'venue' => 'NLHC 101',
            'description' => '<p>Meet the engineering leadership and learn about the SDE role.</p>',
            'audience_type' => 'all', 'published_at' => now()->subDay(), 'created_by' => $this->adminId,
        ]);
        CampusEvent::create([
            'title' => 'Resume Writing Workshop', 'event_type' => 'workshop', 'starts_at' => now()->addDays(5)->setTime(17, 0),
            'venue' => 'Penman Auditorium', 'audience_type' => 'branches',
            'audience_filter' => ['branches' => [['programme' => self::BTECH, 'branch' => null]]],
            'published_at' => now()->subHours(3), 'created_by' => $this->adminId,
        ]);

        $this->seedSupersetParity($ft);
    }

    /**
     * Demo rows for the Superset parity screens (SUPERSET_PARITY_PROGRESS.md), so each new page has something to show.
     */
    private function seedSupersetParity(PlacementCycle $ft): void
    {
        // S3: one Excel Template, like the CDC's own "IIT ISM DHANBAD" format.
        \App\Models\ExportTemplate::create([
            'name' => 'CDC Standard', 'type' => 'STUDENT_LIST', 'created_by' => $this->adminId,
            'columns' => [
                ['key' => 'sno', 'label' => 'S.No.'], ['key' => 'name', 'label' => 'Name'], ['key' => 'roll_no', 'label' => 'Roll No'],
                ['key' => 'tenth_percent', 'label' => 'Class 10 %'], ['key' => 'cgpa', 'label' => 'Current Course Score'],
                ['key' => 'applied_at', 'label' => 'Applied At'], ['key' => 'current_stage', 'label' => 'Current Stage'],
                ['key' => 'application_status', 'label' => 'Application Status'], ['key' => 'ctc_offered', 'label' => 'CTC offered'],
                ['key' => 'ctc_currency', 'label' => 'CTC Currency'], ['key' => 'ctc_interval', 'label' => 'CTC Interval'],
                ['key' => 'answers', 'label' => 'Additional Questions'], ['key' => 'resume_label', 'label' => 'Attached Resume'],
                ['key' => 'resume_link', 'label' => 'Resume Link'], ['key' => 'last_edited', 'label' => 'Last Edited'],
                ['key' => 'cycle_offers', 'label' => 'Offers (FT 2026-27)', 'cycle_id' => $ft->id],
            ],
        ]);

        // S4.5 / S4.6: academic extras for the first few students and two internal notes on the first one.
        $demoStudents = StudentProfile::query()->orderBy('id')->limit(6)->get();
        foreach ($demoStudents as $i => $student) {
            $batch = (int) $student->graduating_batch;
            $student->update([
                'current_semester' => 7,
                'course_start_date' => ($batch - 4).'-07-25',
                'course_end_date' => $batch.'-05-31',
                'lateral_entry' => $i === 5,
                'tenth_board' => $i % 2 === 0 ? 'CBSE' : 'ICSE',
                'tenth_passing_year' => $batch - 7,
                'twelfth_board' => $i % 2 === 0 ? 'CBSE' : 'Bihar School Examination Board',
                'twelfth_passing_year' => $batch - 5,
            ]);
        }
        $pg = StudentProfile::query()->whereIn('programme', [self::MTECH, self::MBA])->orderBy('id')->limit(2)->get();
        foreach ($pg as $i => $student) {
            $student->update([
                'previous_degree' => 'B.Tech', 'previous_degree_score' => $i === 0 ? 8.1 : 76.5, 'previous_degree_score_type' => $i === 0 ? 'cgpa' : 'percentage',
            ]);
        }
        if ($first = $demoStudents->first()) {
            foreach (['Spoke to the student about pending documents; follow up next week.', 'Asked about branch change; told to raise a request through the portal.'] as $body) {
                \App\Models\StudentNote::create(['student_profile_id' => $first->id, 'author_id' => $this->adminId, 'body' => $body]);
            }
        }

        // S7: one published notice (FT placement) and one published PPO-consent survey with a few responses.
        $enrolled = StudentProfile::query()->whereHas('cycleEnrollments', fn ($q) => $q->where('placement_cycle_id', $ft->id)->where('status', 'active'))->orderBy('id')->limit(3)->get();
        $notice = \App\Models\Notice::create([
            'title' => 'Placement week: reporting instructions',
            'body' => '<p>All registered students must carry their institute ID card and two printed copies of their resume.</p><p>Report to NLHC by 8:30 AM on interview days.</p>',
            'published_at' => now()->subHours(6), 'created_by' => $this->adminId,
        ]);
        $notice->audiences()->create(['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $ft->id]]);
        if ($enrolled->isNotEmpty()) {
            \App\Models\NoticeRead::create(['notice_id' => $notice->id, 'student_profile_id' => $enrolled->first()->id, 'read_at' => now()->subHours(2)]);
        }

        $sdePosting = JobPosting::query()->where('placement_cycle_id', $ft->id)->orderBy('id')->first();
        $survey = \App\Models\Survey::create([
            'title' => 'Nimbus Systems || PPO Consent', 'survey_type' => 'ppo_consent',
            'welcome_text' => '<p>Please tell the CDC whether you accept the pre-placement offer. Your answer is for the CDC\'s records only.</p>',
            'concluding_text' => '<p>Thank you. The CDC will contact you if anything else is needed.</p>',
            'status' => 'published', 'allow_edits' => true, 'deadline_at' => now()->addDays(5)->setTime(18, 0),
            'job_posting_id' => $sdePosting?->id, 'published_at' => now()->subDay(), 'created_by' => $this->adminId,
        ]);
        $survey->audiences()->create(['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $ft->id]]);
        $accept = $survey->questions()->create(['qtype' => 'yes_no', 'question' => 'Do you accept the PPO', 'required' => true, 'sort_order' => 1]);
        $city = $survey->questions()->create(['qtype' => 'dropdown', 'question' => 'Preferred joining location', 'options' => ['Bengaluru', 'Hyderabad', 'Pune'], 'sort_order' => 2]);
        $rating = $survey->questions()->create(['qtype' => 'rating', 'question' => 'How was the internship experience?', 'settings' => ['max' => 5], 'sort_order' => 3]);
        $survey->questions()->create(['qtype' => 'text', 'question' => 'Anything the CDC should know?', 'help_text' => 'Optional', 'sort_order' => 4]);
        foreach ($enrolled as $i => $student) {
            $survey->responses()->create([
                'student_profile_id' => $student->id,
                // Single-submission survey: one response per student, guarded by single_key (fix L21).
                'single_key' => $survey->allow_multiple ? null : \App\Models\SurveyResponse::singleKey($survey->id, $student->id),
                'answers' => [(string) $accept->id => $i === 2 ? 'no' : 'yes', (string) $city->id => ['Bengaluru', 'Pune', 'Hyderabad'][$i], (string) $rating->id => 5 - $i],
                'submitted_at' => now()->subHours(20 - $i * 5),
            ]);
        }

        // S8.2 / S8.3: Users directory fields on the demo admin, and a Draft placement students cannot see yet.
        User::query()->where('email', 'admin@cdc-demo.test')->update([
            'first_name' => 'Demo', 'last_name' => 'CDC Admin', 'designation' => 'Placement Officer', 'mobile' => '+91 9000000000',
        ]);
        PlacementCycle::create([
            'name' => 'Internship 2027-28 (2029 Passout Batch)', 'type' => 'internship', 'starts_on' => '2027-03-01', 'ends_on' => '2028-02-29',
            'status' => 'open', 'is_draft' => true, 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2029]]],
            'description' => 'Draft: being set up, not visible to students yet.', 'created_by' => $this->adminId,
        ]);

        // S5: invitation status. Most demo students have signed in (Accepted); the last four by id are three Sent
        // and one Revoked, so the Send Invitations page shows every chip.
        $studentUsers = User::query()->where('role', 'student')->orderBy('id')->pluck('id');
        User::query()->whereIn('id', $studentUsers)->update([
            'invited_at' => now()->subDays(20), 'last_invited_at' => now()->subDays(20), 'invite_count' => 1, 'activated_at' => now()->subDays(15),
        ]);
        $pending = $studentUsers->slice(-4)->values();
        User::query()->whereIn('id', $pending)->update(['activated_at' => null, 'last_invited_at' => now()->subDays(2), 'invite_count' => 2]);
        User::query()->where('id', $pending->last())->update(['invite_revoked_at' => now()->subDay()]);
    }

    /** @return list<StudentProfile> */
    private function students(): array
    {
        $password = Hash::make('Student@2026');
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        $plan = [];
        foreach (self::BTECH_BRANCHES as $branch => $count) {
            for ($i = 0; $i < $count; $i++) {
                $plan[] = [self::BTECH, $branch, 'JE', 23];
            }
        }
        foreach (['Computer Science and Engineering', 'Data Analytics', 'VLSI Design (Electronics and Communication Engineering)'] as $branch) {
            for ($i = 0; $i < 4; $i++) {
                $plan[] = [self::MTECH, $branch, 'MT', 25];
            }
        }
        foreach (['MBA - Finance', 'MBA - Marketing'] as $branch) {
            for ($i = 0; $i < 4; $i++) {
                $plan[] = [self::MBA, $branch, 'MB', 25];
            }
        }

        $students = [];
        $counters = [];
        foreach ($plan as [$programme, $branch, $code, $year]) {
            if (! \App\Support\ProgrammeCatalogue::has($programme, $branch)) {
                continue; // catalogue changed: skip rather than seed an invalid student
            }
            $counters[$code] = ($counters[$code] ?? 100) + $this->faker->numberBetween(1, 7);
            $roll = sprintf('%d%s%04d', $year, $code, $counters[$code]);
            $gender = $this->faker->randomElement(['male', 'male', 'female']);
            $name = $this->faker->firstName($gender).' '.$this->faker->lastName();
            // Reserved .test domain: roll-number addresses at iitism.ac.in may belong to REAL students (D90).
            $email = strtolower($roll).'@students.cdc-demo.test';
            $ongoing = $this->faker->boolean(8) ? 1 : 0;

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'student', 'is_active' => true]);
            $student = StudentProfile::create([
                'user_id' => $user->id, 'roll_no' => $roll, 'full_name' => $name, 'institute_email' => $email,
                'personal_email' => Str::slug($name, '.').'@personal.cdc-demo.test', 'phone' => '9'.$this->faker->numerify('#########'),
                'programme' => $programme, 'branch' => $branch, 'graduating_batch' => 2027,
                'current_cgpa' => $this->cgpa(), 'ongoing_backlogs' => $ongoing, 'total_backlogs' => $ongoing + ($this->faker->boolean(10) ? 1 : 0),
                'gender' => $gender, 'date_of_birth' => $this->faker->dateTimeBetween('2003-01-01', '2005-06-30')->format('Y-m-d'),
                'tenth_percent' => $this->faker->randomFloat(2, 78, 99), 'twelfth_percent' => $this->faker->randomFloat(2, 72, 98),
                'category' => $this->faker->randomElement(['GEN', 'GEN', 'OBC', 'SC', 'ST', 'EWS']), 'pwd' => $this->faker->boolean(2),
                'home_state' => $this->faker->randomElement(['Jharkhand', 'Bihar', 'Uttar Pradesh', 'Rajasthan', 'Maharashtra', 'West Bengal', 'Telangana', 'Odisha']),
            ]);

            $path = "resumes/{$roll}/1_".Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $pdf);
            $student->resumes()->create([
                'slot' => 1, 'label' => $programme === self::MBA ? 'Business' : 'Software', 'file_path' => $path, 'file_size' => strlen($pdf),
                'status' => $this->faker->boolean(88) ? 'approved' : 'pending',
                'reviewed_by' => $this->adminId, 'reviewed_at' => now()->subDays(3),
            ]);

            $students[] = $student;
        }

        return $students;
    }

    /** Box–Muller normal CGPA, mean 7.9, sd 0.8, clamped to 6.0–9.8. */
    private function cgpa(): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();
        $z = sqrt(-2 * log($u)) * cos(2 * M_PI * $v);

        return round(min(9.8, max(6.0, 7.9 + 0.8 * $z)), 2);
    }

    private function company(string $name, string $email, string $sector, string $hr): Company
    {
        $company = Company::create([
            'name' => $name, 'industry' => $sector, 'sector' => $sector, 'website' => 'https://'.Str::slug($name).'.example',
            'hr_name' => $hr, 'hr_email' => $email, 'hr_phone' => '98'.$this->faker->numerify('########'),
            'company_description' => "<p>{$name} builds products used by millions of people.</p>",
            'primary_contact' => ['name' => $hr, 'designation' => 'Campus Recruiter', 'email' => $email, 'mobile' => '98'.$this->faker->numerify('########')],
        ]);
        User::create(['name' => $hr, 'email' => $email, 'password' => Hash::make('Company@2026'), 'role' => 'company', 'company_id' => $company->id, 'is_active' => true]);

        return $company;
    }

    private function eligibility(float $cgpa = 6.5): array
    {
        $rows = [];
        foreach ([self::BTECH => array_keys(self::BTECH_BRANCHES), self::MTECH => ['Computer Science and Engineering', 'Data Analytics'], self::MBA => ['MBA - Finance']] as $programme => $branches) {
            $rows[] = [
                'programme' => $programme, 'expanded' => false, 'graduatingBatch' => '2027', 'graduatingBatches' => ['2027'],
                'branches' => array_map(fn ($b) => ['branch' => $b, 'selected' => true, 'cgpa' => (string) $cgpa, 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '0', 'maxTotalBacklogs' => '1'], $branches),
            ];
        }

        return $rows;
    }

    private function baseForm(Company $company, array $rounds, float $cgpa): array
    {
        return [
            'companyProfile' => ['name' => $company->name, 'website' => $company->website, 'sector' => $company->sector, 'companyDescription' => $company->company_description],
            'workMode' => 'hybrid', 'joiningMonth' => '2027-07', 'skills' => ['Problem solving', 'Communication'],
            'eligibility' => $this->eligibility($cgpa), 'globalCgpa' => (string) $cgpa, 'globalBacklogs' => true, 'genderFilter' => 'all',
            'graduatingBatch' => '2027', 'minTenthPercent' => '75', 'minTwelfthPercent' => '', 'currency' => 'INR',
            'selectionRounds' => array_map(fn ($type, $i) => ['id' => (string) ($i + 1), 'type' => $type, 'mode' => 'offline', 'enabled' => true], $rounds, array_keys($rounds)),
            'declarations' => array_fill_keys(['aipc', 'shortlistCriteria', 'infoVerified', 'consentLogo', 'confirmAccuracy', 'resultsViaCdc'], true),
            'signatory' => ['name' => $company->hr_name, 'designation' => 'Campus Recruiter', 'date' => now()->toDateString()],
        ];
    }

    private function jnf(Company $company, string $title, string $location, int $ctc, int $vacancies, array $rounds, float $cgpa = 6.5): Jnf
    {
        $data = $this->baseForm($company, $rounds, $cgpa) + [
            'jobTitle' => $title, 'jobDesignation' => $title, 'jobLocation' => $location, 'expectedHires' => (string) $vacancies, 'minimumHires' => '2',
            'jobDescription' => "<p>Join {$company->name} as a {$title}.</p>",
            'salarySameForAll' => false,
            'programmeSalaries' => [
                ['programme' => self::BTECH, 'ctcAnnual' => (string) $ctc, 'baseSalary' => (string) (int) ($ctc * 0.8), 'takeHome' => '', 'enabled' => true],
                ['programme' => self::MTECH, 'ctcAnnual' => (string) (int) ($ctc * 1.1), 'baseSalary' => '', 'takeHome' => '', 'enabled' => true],
                ['programme' => self::MBA, 'ctcAnnual' => (string) (int) ($ctc * 0.9), 'baseSalary' => '', 'takeHome' => '', 'enabled' => true],
            ],
            'salaryComponents' => ['joiningBonus' => '100000'],
        ];

        return Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => strip_tags($data['jobDescription']), 'job_location' => $location,
            'ctc_min' => $ctc, 'ctc_max' => (int) ($ctc * 1.1), 'vacancies' => $vacancies, 'status' => 'accepted', 'form_data' => $data,
        ]);
    }

    private function inf(Company $company, string $title, string $location, int $stipend, int $vacancies): Inf
    {
        $data = $this->baseForm($company, ['resume', 'technical_interview'], 7.0) + [
            'internshipTitle' => $title, 'internshipDesignation' => 'Research Intern', 'internshipLocation' => $location, 'expectedHires' => (string) $vacancies,
            'duration' => '10', 'internshipDescription' => "<p>Work with the {$company->name} research team.</p>", 'stipendSameForAll' => true,
            'programmeStipends' => [['programme' => self::BTECH, 'baseStipend' => (string) $stipend, 'hra' => '0', 'otherPerks' => '', 'total' => (string) $stipend, 'enabled' => true]],
            'ppoProvision' => true, 'ppoCtc' => '1800000',
        ];
        $data['eligibility'] = [$data['eligibility'][0]];

        return Inf::create([
            'company_id' => $company->id, 'internship_title' => $title, 'internship_description' => strip_tags($data['internshipDescription']),
            'internship_location' => $location, 'stipend' => $stipend, 'internship_duration_weeks' => 10, 'vacancies' => $vacancies, 'status' => 'accepted', 'form_data' => $data,
        ]);
    }

    /**
     * @param  list<int>  $roundDays  day offsets from today for each round (past for finished drives, after the deadline for open ones)
     */
    private function float(Jnf|Inf $form, PlacementCycle $cycle, \DateTimeInterface $deadline, array $roundDays, array $questions): JobPosting
    {
        $posting = JobPosting::create([
            'postable_type' => $form::class, 'postable_id' => $form->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => $deadline, 'status' => 'open', 'share_contact_details' => false,
            'floated_by' => $this->adminId, 'floated_at' => now()->subDays(14),
            'eligibility_snapshot' => array_intersect_key($form->form_data, array_flip(JobPosting::SNAPSHOT_KEYS)),
        ]);

        $rounds = array_values(array_filter($form->form_data['selectionRounds'], fn ($r) => $r['enabled']));
        foreach ($rounds as $index => $round) {
            $posting->rounds()->create([
                'name' => JobPosting::ROUND_LABELS[$round['type']], 'round_type' => $round['type'], 'sort_order' => $index + 1,
                'scheduled_at' => now()->addDays($roundDays[$index] ?? (3 + 2 * $index))->setTime(10, 0),
                'status' => $index === 0 ? 'ongoing' : 'pending', 'is_final' => $index === count($rounds) - 1,
            ]);
        }
        foreach ($questions as $index => $question) {
            $posting->questions()->create($question + ['sort_order' => $index + 1, 'options' => $question['options'] ?? null]);
        }

        return $posting;
    }

    private function apply(JobPosting $posting, StudentProfile $student, \DateTimeInterface $at): Application
    {
        $resume = $student->resumes()->first();
        $answers = $posting->questions->map(fn ($q) => [
            'question_id' => $q->id,
            'answer' => match ($q->qtype) {
                'mcq_single' => $q->options[0],
                'mcq_multi' => array_slice($q->options, 0, 2),
                default => 'https://github.com/'.Str::slug($student->full_name),
            },
        ])->all();

        return Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'used_unverified_resume' => $resume->status !== 'approved', 'answers' => $answers, 'applied_at' => $at,
        ]);
    }

    /** @param list<StudentProfile> $students */
    private function eligibleFor(JobPosting $posting, array $students): array
    {
        $service = app(\App\Services\EligibilityService::class);

        return array_values(array_filter($students, fn ($s) => $service->check($s->fresh(['user', 'cycleEnrollments']), $posting)['eligible']));
    }

    /**
     * The showcase drive: 60 applications → online test → interview (+1 addendum) → final: 8 offers, blocks, 2 waitlisted.
     *
     * @param  list<StudentProfile>  $students
     */
    private function runSdeDrive(JobPosting $posting, array $students): void
    {
        $posting->load(['rounds', 'questions']);
        $pool = array_slice($this->eligibleFor($posting, $students), 0, 60);
        $applications = collect($pool)->map(fn ($s, $i) => $this->apply($posting, $s, now()->subDays(13)->addHours($i)))->values();
        $posting->update(['status' => 'in_process']);

        [$test, $interview, $final] = $posting->rounds->all();
        $ranked = $applications->sortByDesc(fn ($a) => (float) $a->studentProfile->current_cgpa)->values();

        // Round 1: top 25 cleared, everyone else not selected.
        $cleared = $ranked->take(25);
        foreach ($ranked as $a) {
            $this->result($a, $test, $cleared->contains('id', $a->id) ? 'selected' : 'rejected', now()->subDays(8), attendance: 'yes');
        }
        $test->update(['status' => 'completed']);

        // Round 2: 12 cleared + 1 addendum (added after publishing), 2 absent.
        $toFinal = $cleared->take(12);
        foreach ($cleared as $i => $a) {
            $this->result($a, $interview, $toFinal->contains('id', $a->id) ? 'selected' : 'rejected', now()->subDays(4), attendance: $i >= 23 ? 'no' : 'yes');
        }
        $addendum = $cleared->get(13);
        ApplicationRoundResult::query()->where('application_id', $addendum->id)->where('posting_round_id', $interview->id)
            ->update(['result' => 'selected', 'is_addendum' => true, 'remark' => 'Addendum requested by Nimbus Systems', 'published_at' => now()->subDays(3)]);
        $toFinal->push($addendum);
        $interview->update(['status' => 'completed']);

        // Final: 8 offers, 2 waitlisted, the rest not selected.
        $types = ['fulltime', 'fulltime', 'fulltime', 'fulltime', 'fulltime', 'intern_fulltime', 'fulltime', 'intern_performance_ppo'];
        $policy = app(\App\Services\BlockingPolicy::class);
        foreach ($toFinal->values() as $i => $a) {
            if ($i < 8) {
                $this->result($a, $final, 'selected', now()->subDay(), attendance: 'yes');
                $comp = $posting->compensationFor($a->studentProfile->programme);
                $offer = Offer::create([
                    'application_id' => $a->id, 'student_profile_id' => $a->student_profile_id, 'company_id' => $posting->company()->id,
                    'job_posting_id' => $posting->id, 'placement_cycle_id' => $posting->placement_cycle_id, 'offer_type' => $types[$i],
                    'ctc_annual' => $comp['ctc_annual'], 'stipend_monthly' => $types[$i] === 'intern_performance_ppo' ? 80000 : null,
                    'currency' => 'INR', 'announced_by' => $this->adminId, 'announced_at' => now()->subDay(),
                ]);
                if ($block = $policy->suggest($types[$i])) {
                    // One row per cycle the block reaches, as publishing does (QA F-004).
                    foreach ($policy->targets($a->studentProfile, $posting->placementCycle, $block['scope']) as $target) {
                        PlacementBlock::create([
                            'student_profile_id' => $a->student_profile_id, 'placement_cycle_id' => $target['placement_cycle_id'],
                            'scope' => $target['scope'], 'reason' => 'offer', 'offer_id' => $offer->id, 'active' => true, 'blocked_by' => $this->adminId,
                        ]);
                    }
                }
            } elseif ($i < 10) {
                $this->result($a, $final, 'waitlisted', now()->subDay(), attendance: 'yes');
            } else {
                $this->result($a, $final, 'rejected', now()->subDay(), attendance: 'yes');
            }
        }
        $final->update(['status' => 'completed']);
        $posting->update(['status' => 'completed']);
    }

    /** @param list<StudentProfile> $students */
    private function applyToOpenPosting(JobPosting $posting, array $students, int $max = 30): void
    {
        $posting->load('questions');
        foreach (array_slice($this->eligibleFor($posting, $students), 0, $max) as $i => $student) {
            $this->apply($posting, $student, now()->subDays(5)->addHours($i * 3));
        }
    }

    private function flagPlacedElsewhere(PlacementCycle $cycle): void
    {
        // Internship cycles hold only internships, so an internships-only block covers everything there too.
        $blocked = PlacementBlock::query()->where('placement_cycle_id', $cycle->id)->where('active', true)
            ->when($cycle->type !== 'internship', fn ($q) => $q->where('scope', 'all'))
            ->pluck('offer_id', 'student_profile_id');

        Application::query()
            ->whereIn('student_profile_id', $blocked->keys())
            ->where('status', 'applied')
            ->whereDoesntHave('offer')
            ->whereHas('jobPosting', fn ($q) => $q->where('placement_cycle_id', $cycle->id)->whereIn('status', ['open', 'in_process']))
            ->update(['placed_elsewhere_flag' => true]);
    }

    private function result(Application $a, $round, string $result, \DateTimeInterface $publishedAt, ?string $attendance = null): void
    {
        ApplicationRoundResult::create([
            'application_id' => $a->id, 'posting_round_id' => $round->id, 'attendance' => $attendance, 'result' => $result,
            'published_at' => $publishedAt, 'decided_by' => $this->adminId,
        ]);
    }
}
