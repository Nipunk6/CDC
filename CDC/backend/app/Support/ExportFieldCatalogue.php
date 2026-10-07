<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Offer;
use App\Models\StudentProfile;
use Illuminate\Support\Str;

/**
 * Every field an Excel Template can use (Superset parity S3.4), for the STUDENT_LIST type.
 *
 * Each field has a default label, a group (for the picker), an audience and a resolver. Audiences:
 *  - `company`: safe for a company export of its own job profile (D78 field set);
 *  - `contact`: contact details, company-safe only when the job profile shares contact details;
 *  - `admin`: CDC only — never written into a company export, even if a template lists it.
 * Resolvers receive the row context built by TemplateExportService:
 *   ['student' => StudentProfile, 'application' => ?Application, 'posting' => ?JobPosting, 'applied' => ?bool,
 *    'decision' => ?array, 'enrollment' => ?CycleEnrollment, 'index' => int, 'cycle' => ?array, 'company_view' => bool]
 * Fields marked `expand` produce one column per question / stage of the job profile being exported.
 */
final class ExportFieldCatalogue
{
    /**
     * @return array<string, array{label: string, group: string, audience: string, value?: callable, expand?: string, cycle?: bool}>
     */
    public static function fields(): array
    {
        $s = fn (callable $f) => fn (array $c) => $f($c['student']);
        $app = fn (callable $f) => fn (array $c) => $c['application'] ? $f($c['application']) : null;
        $num = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $ist = fn ($date) => $date?->timezone('Asia/Kolkata')->format('Y M d h:i A');

        return [
            // Identity
            'sno' => ['label' => 'S.No.', 'group' => 'Identity', 'audience' => 'company', 'value' => fn (array $c) => $c['index']],
            'name' => ['label' => 'Name', 'group' => 'Identity', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->full_name)],
            'roll_no' => ['label' => 'Roll No', 'group' => 'Identity', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->roll_no)],
            'gender' => ['label' => 'Gender', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => ucfirst((string) $p->gender))],
            'date_of_birth' => ['label' => 'Date of Birth', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->date_of_birth?->format('Y-m-d'))],
            'social_category' => ['label' => 'Social Category', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->category)],
            'pwd' => ['label' => 'PwD', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->pwd ? 'Yes' : 'No')],
            'home_state' => ['label' => 'Home State', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->home_state)],
            'linkedin' => ['label' => 'LinkedIn', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->linkedin_url)],
            'github' => ['label' => 'GitHub', 'group' => 'Identity', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->github_url)],

            // Academics
            'programme' => ['label' => 'Programme', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->programme)],
            'branch' => ['label' => 'Branch', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->branch)],
            'batch' => ['label' => 'Passout Batch', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->graduating_batch)],
            'cgpa' => ['label' => 'CGPA', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $num($p->current_cgpa))],
            'ongoing_backlogs' => ['label' => 'Ongoing Backlogs', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->ongoing_backlogs)],
            'total_backlogs' => ['label' => 'Total Backlogs', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $p->total_backlogs)],
            'tenth_percent' => ['label' => 'Class X Percentage', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $num($p->tenth_percent))],
            'twelfth_percent' => ['label' => 'Class XII Percentage', 'group' => 'Academics', 'audience' => 'company', 'value' => $s(fn (StudentProfile $p) => $num($p->twelfth_percent))],
            // S4.6 CDC-entered academic extras (admin only until the owner widens the D78 company field set)
            'tenth_passing_year' => ['label' => 'Year of passing 10th', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->tenth_passing_year)],
            'tenth_board' => ['label' => 'Xth Board', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->tenth_board)],
            'twelfth_passing_year' => ['label' => 'Year of passing 12th', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->twelfth_passing_year)],
            'twelfth_board' => ['label' => 'XIIth Board', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->twelfth_board)],
            'current_semester' => ['label' => 'Current Semester', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->current_semester)],
            'course_start_date' => ['label' => 'Course Start Date', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->course_start_date?->format('Y-m-d'))],
            'course_end_date' => ['label' => 'Course End Date', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->course_end_date?->format('Y-m-d'))],
            'lateral_entry' => ['label' => 'Lateral Entry', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->lateral_entry ? 'Yes' : 'No')],
            'previous_degree' => ['label' => 'Previous Degree', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->previous_degree)],
            'previous_degree_score' => ['label' => 'Previous Degree Score', 'group' => 'Academics', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => $p->previous_degree_score === null ? null : ((float) $p->previous_degree_score).($p->previous_degree_score_type === 'percentage' ? '%' : ($p->previous_degree_score_type === 'cgpa' ? ' CGPA' : '')))],

            // Contact
            'institute_email' => ['label' => 'Email ID', 'group' => 'Contact', 'audience' => 'contact', 'value' => $s(fn (StudentProfile $p) => $p->institute_email)],
            'personal_email' => ['label' => 'Personal Email', 'group' => 'Contact', 'audience' => 'contact', 'value' => $s(fn (StudentProfile $p) => $p->personal_email)],
            'mobile' => ['label' => 'Mobile No.', 'group' => 'Contact', 'audience' => 'contact', 'value' => $s(fn (StudentProfile $p) => $p->phone)],

            // Account
            'account_status' => ['label' => 'Account Status', 'group' => 'Account', 'audience' => 'admin', 'value' => $s(fn (StudentProfile $p) => ($p->user?->is_active ?? true) ? 'Active' : 'Suspended')],
            'enrolment_status' => ['label' => 'Enrolment', 'group' => 'Account', 'audience' => 'admin', 'value' => fn (array $c) => $c['enrollment'] ? ($c['enrollment']->status === 'active' ? 'Enrolled' : ucfirst($c['enrollment']->status)) : null],

            // Application (filled when the row is an application of the job profile being exported)
            'applied' => ['label' => 'Applied', 'group' => 'Application', 'audience' => 'admin', 'value' => fn (array $c) => $c['applied'] === null ? null : ($c['applied'] ? 'Applied' : 'Not applied')],
            'application_status' => ['label' => 'Application Status', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => ucfirst($a->status))],
            'applied_at' => ['label' => 'Applied At', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $ist($a->applied_at))],
            'last_edited' => ['label' => 'Last Edited', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $ist($a->updated_at))],
            // Admin only for now: the default company export has no resume label and companies get no templates (owner note).
            'resume_label' => ['label' => 'Attached Resume', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $a->resume?->label)],
            'resume_verified' => ['label' => 'Resume Verified', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $a->resume ? ($a->resume->status === 'approved' ? 'Verified' : ucfirst($a->resume->status)) : null)],
            'resume_link' => ['label' => 'Resume Link', 'group' => 'Application', 'audience' => 'company', 'value' => $app(fn (Application $a) => $a->resume ? ['link' => $a->resume->signedUrl(30)] : null)],
            'unverified_resume_flag' => ['label' => 'Unverified Resume Flag', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $a->used_unverified_resume ? 'YES' : '')],
            'placed_elsewhere_flag' => ['label' => 'Placed Elsewhere Flag', 'group' => 'Application', 'audience' => 'admin', 'value' => $app(fn (Application $a) => $a->placed_elsewhere_flag ? 'YES' : '')],
            'answers' => ['label' => 'Additional Questions (one column each)', 'group' => 'Application', 'audience' => 'company', 'expand' => 'questions'],
            'stages' => ['label' => 'Stage Results (one column each)', 'group' => 'Application', 'audience' => 'company', 'expand' => 'rounds'],
            'current_stage' => ['label' => 'Current Stage', 'group' => 'Application', 'audience' => 'company', 'value' => fn (array $c) => self::currentStage($c)],
            'stage_decision' => ['label' => 'Decision in this Stage', 'group' => 'Application', 'audience' => 'admin', 'value' => fn (array $c) => $c['decision']['label'] ?? null],

            // Offer for the job profile being exported
            'offer_type' => ['label' => 'Offer Type', 'group' => 'Offer', 'audience' => 'admin', 'value' => fn (array $c) => ($o = self::offer($c)) ? (Offer::LABELS[$o->offer_type] ?? $o->offer_type) : null],
            'ctc_offered' => ['label' => 'CTC offered', 'group' => 'Offer', 'audience' => 'admin', 'value' => fn (array $c) => ($o = self::offer($c)) ? ($o->ctc_annual ?? $o->stipend_monthly) : null],
            'ctc_currency' => ['label' => 'CTC Currency', 'group' => 'Offer', 'audience' => 'admin', 'value' => fn (array $c) => self::offer($c)?->currency],
            'ctc_interval' => ['label' => 'CTC Interval', 'group' => 'Offer', 'audience' => 'admin', 'value' => fn (array $c) => ($o = self::offer($c)) ? ($o->ctc_annual !== null ? 'YEAR' : ($o->stipend_monthly !== null ? 'MONTH' : null)) : null],

            // Placement Cycle Specific Information (needs a chosen cycle; admin only)
            'cycle_enrolment' => ['label' => 'Enrolment', 'group' => 'Placement', 'audience' => 'admin', 'cycle' => true, 'value' => fn (array $c) => ($e = $c['cycle']['enrollment'] ?? null) ? ($e->status === 'active' ? 'Enrolled' : ucfirst($e->status)) : 'Not enrolled'],
            'cycle_offers' => ['label' => 'Offers', 'group' => 'Placement', 'audience' => 'admin', 'cycle' => true, 'value' => fn (array $c) => collect($c['cycle']['offers'] ?? [])->map(fn (Offer $o) => ($o->company?->name ?? '').' ('.(Offer::LABELS[$o->offer_type] ?? $o->offer_type).')')->implode('; ')],
            'cycle_best_ctc' => ['label' => 'Best CTC (annual, INR)', 'group' => 'Placement', 'audience' => 'admin', 'cycle' => true, 'value' => fn (array $c) => collect($c['cycle']['offers'] ?? [])->where('currency', 'INR')->max('ctc_annual')],
            'cycle_blocks' => ['label' => 'Active Blocks', 'group' => 'Placement', 'audience' => 'admin', 'cycle' => true, 'value' => fn (array $c) => collect($c['cycle']['blocks'] ?? [])->map(fn ($b) => $b->message())->implode('; ')],
        ];
    }

    /**
     * Picker payload for the template editor.
     *
     * @return list<array{key: string, label: string, group: string, audience: string, cycle: bool}>
     */
    public static function forPicker(): array
    {
        return collect(self::fields())->map(fn (array $f, string $key) => [
            'key' => $key,
            'label' => $f['label'],
            'group' => $f['group'],
            'audience' => $f['audience'],
            'cycle' => (bool) ($f['cycle'] ?? false),
        ])->values()->all();
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::fields());
    }

    public static function stageText(?ApplicationRoundResult $row, bool $companyView, bool $isFinal): ?string
    {
        if (! $row || ($companyView && ! $row->isPublished())) {
            return null;
        }
        $text = match ($row->result) {
            'selected' => $isFinal ? 'Selected' : 'Shortlisted',
            'waitlisted' => 'On Hold',
            'rejected' => 'Not selected',
            default => 'Pending',
        };
        if ($row->attendance) {
            $text .= $row->attendance === 'yes' ? ' (appeared)' : ' (absent)';
        }
        // The same marker as the default applicant export (L12).
        if ($row->is_addendum) {
            $text .= ' [addendum]';
        }

        return ! $companyView && ! $row->isPublished() ? $text.' (draft)' : $text;
    }

    public static function questionHeader(int $index, string $question): string
    {
        return 'Q'.$index.': '.Str::limit($question, 60);
    }

    private static function offer(array $c): ?Offer
    {
        return $c['application']?->offer;
    }

    /** 1-based number of the furthest stage the student is in (published results only). */
    private static function currentStage(array $c): ?int
    {
        $application = $c['application'];
        $posting = $c['posting'];
        if (! $application || ! $posting) {
            return null;
        }
        $stage = 1;
        foreach ($posting->rounds->values() as $i => $round) {
            $row = $application->roundResults->firstWhere('posting_round_id', $round->id);
            if ($row && $row->isPublished() && $row->result === 'selected') {
                $stage = min($i + 2, $posting->rounds->count());
            }
        }

        return $stage;
    }
}
