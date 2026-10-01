<?php
// QA Part 7 scale fixture. Run: MAIL_MAILER=log php artisan tinker ../qa/seed_scale.php  (from CDC/backend)
// Prereqs: ~2756 students 27SC#### imported via the real importer and enrolled in cycle "SCALE FT 2026-27".
// Creates: an approved resume per scale student (DB rows only), 32 accepted JNFs for company "Alpha Systems QA"
// (30 ordinary + 2 "wide" ones for the P2/P3 float timings — floated later through the API), no mail sent here.

use App\Models\{Jnf, Company, StudentProfile, PlacementCycle};
use Illuminate\Support\Facades\DB;

$company = Company::where('name', 'Alpha Systems QA')->firstOrFail();
$base = Jnf::find(3)->form_data;                         // QA-JNF built in the wizard
$cycle = PlacementCycle::where('name', 'SCALE FT 2026-27')->firstOrFail();
$now = now();

// 1) one approved resume per scale student (bulk insert, no files needed for timing; signed links still render)
$students = StudentProfile::where('roll_no', 'like', '27SC%')->get(['id', 'roll_no']);
$have = DB::table('resumes')->whereIn('student_profile_id', $students->pluck('id'))->pluck('student_profile_id')->flip();
$rows = [];
foreach ($students as $s) {
    if ($have->has($s->id)) continue;
    $rows[] = ['student_profile_id' => $s->id, 'slot' => 1, 'label' => 'Scale', 'file_path' => "resumes/{$s->roll_no}/1_scale.pdf",
        'file_size' => 193, 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now];
}
foreach (array_chunk($rows, 500) as $chunk) DB::table('resumes')->insert($chunk);

// 2) JNFs: "wide" eligibility = all 6 branches, CGPA 6.0, backlogs unlimited
$wide = $base;
foreach ($wide['eligibility'] as &$p) {
    foreach ($p['branches'] as &$b) {
        if (in_array($b['branch'], ['Computer Science & Engineering','Electronics & Communication Engineering','Mechanical Engineering','Electrical Engineering','Civil Engineering','Chemical Engineering'], true)) {
            $b['selected'] = true; $b['cgpa'] = '6.0'; $b['backlogsAllowed'] = true; unset($b['maxOngoingBacklogs'], $b['maxTotalBacklogs']);
        }
    }
}
unset($p, $b);
$wide['minTenthPercent'] = ''; $wide['minTwelfthPercent'] = '';

$ids = ['normal' => [], 'wide' => []];
for ($i = 1; $i <= 32; $i++) {
    $isWide = $i > 30;
    $fd = $isWide ? $wide : $base;
    $fd['jobTitle'] = ($isWide ? 'Scale Wide Role ' : 'Scale Role ').$i;
    $j = Jnf::create(['company_id' => $company->id, 'job_title' => $fd['jobTitle'], 'job_description' => 'Scale', 'status' => 'accepted', 'vacancies' => 5, 'form_data' => $fd]);
    $ids[$isWide ? 'wide' : 'normal'][] = $j->id;
}
file_put_contents(base_path('../qa/evidence/scale_jnf_ids.json'), json_encode($ids));
echo 'resumes inserted='.count($rows).' jnfs='.json_encode($ids).PHP_EOL;
