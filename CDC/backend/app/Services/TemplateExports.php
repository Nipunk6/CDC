<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ExportTemplate;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the rows for every download that accepts an Excel Template (Superset parity S3) and hands them to
 * ExportService::templateWorkbook. The "Default Template" downloads keep their existing code and headers.
 */
class TemplateExports
{
    /** Columns of the Default Template for the new Eligible List download (S3.6). */
    public const ELIGIBLE_DEFAULT = [
        ['key' => 'sno', 'label' => 'S.No.'],
        ['key' => 'roll_no', 'label' => 'Roll No'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'programme', 'label' => 'Programme'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'batch', 'label' => 'Passout Batch'],
        ['key' => 'cgpa', 'label' => 'CGPA'],
        ['key' => 'ongoing_backlogs', 'label' => 'Ongoing Backlogs'],
        ['key' => 'total_backlogs', 'label' => 'Total Backlogs'],
        ['key' => 'tenth_percent', 'label' => 'Class X Percentage'],
        ['key' => 'twelfth_percent', 'label' => 'Class XII Percentage'],
        ['key' => 'institute_email', 'label' => 'Email ID'],
        ['key' => 'mobile', 'label' => 'Mobile No.'],
        ['key' => 'applied', 'label' => 'Applied'],
    ];

    /** Columns of the Default Template for the student list's "Download as Excel" (S4.4). */
    public const STUDENTS_DEFAULT = [
        ['key' => 'sno', 'label' => 'S.No.'],
        ['key' => 'roll_no', 'label' => 'Roll Number'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'programme', 'label' => 'Programme'],
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'batch', 'label' => 'Passout Batch'],
        ['key' => 'gender', 'label' => 'Gender'],
        ['key' => 'cgpa', 'label' => 'CGPA'],
        ['key' => 'ongoing_backlogs', 'label' => 'Ongoing Backlogs'],
        ['key' => 'total_backlogs', 'label' => 'Total Backlogs'],
        ['key' => 'tenth_percent', 'label' => 'Class X Percentage'],
        ['key' => 'twelfth_percent', 'label' => 'Class XII Percentage'],
        ['key' => 'institute_email', 'label' => 'Email ID'],
        ['key' => 'personal_email', 'label' => 'Personal Email'],
        ['key' => 'mobile', 'label' => 'Mobile No.'],
        ['key' => 'account_status', 'label' => 'Account Status'],
    ];

    public function __construct(
        private readonly ExportService $exports,
        private readonly EligibilityService $eligibility,
        private readonly PipelineService $pipeline
    ) {
    }

    public static function find(int|string|null $id): ?ExportTemplate
    {
        return $id ? ExportTemplate::query()->where('type', 'STUDENT_LIST')->findOrFail((int) $id) : null;
    }

    /** Admin applicant download with a custom template: every application, withdrawn ones included (as the default). */
    public function applicants(JobPosting $posting, ExportTemplate $template): StreamedResponse
    {
        $applications = $posting->applications()
            ->with(['studentProfile.user:id,is_active', 'resume', 'roundResults', 'offer'])
            ->orderBy('applied_at')
            ->get();

        $rows = $applications->map(fn (Application $a) => ['student' => $a->studentProfile, 'application' => $a, 'applied' => $a->status === 'applied']);

        return $this->exports->templateWorkbook($template, $rows, $posting, 'admin', $this->fileName('Applicant List for', $posting, $template));
    }

    /** "Download Eligible List" (S3.6, admin only): everyone currently eligible, with Applied / Not applied. */
    public function eligible(JobPosting $posting, ?ExportTemplate $template): StreamedResponse
    {
        $students = $this->eligibility->eligibleStudentsQuery($posting)->with('user:id,is_active')->orderBy('roll_no')->get();
        $applications = $posting->applications()
            ->where('status', 'applied')
            ->whereIn('student_profile_id', $students->pluck('id'))
            ->with(['resume', 'roundResults', 'offer'])
            ->get()
            ->keyBy('student_profile_id');

        $rows = $students->map(fn (StudentProfile $s) => [
            'student' => $s,
            'application' => $applications->get($s->id),
            'applied' => $applications->has($s->id),
        ]);

        $template ??= new ExportTemplate(['name' => 'Default', 'type' => 'STUDENT_LIST', 'columns' => self::ELIGIBLE_DEFAULT]);

        return $this->exports->templateWorkbook($template, $rows, $posting, 'admin', $this->fileName('Eligible List for', $posting, $template->exists ? $template : null));
    }

    /** "Download Current Shortlist" with a custom template (S1.4 + S3): the default's title row and IST footer (L11). */
    public function shortlist(JobPosting $posting, PostingRound $round, string $nextLabel, ExportTemplate $template): StreamedResponse
    {
        $ids = array_values(array_unique(array_merge(
            $this->pipeline->pool($round)->pluck('id')->all(),
            $round->results()->pluck('application_id')->all()
        )));
        $applications = Application::query()
            ->whereIn('id', $ids)
            ->where('status', 'applied')
            ->with(['studentProfile.user:id,is_active', 'resume', 'roundResults', 'offer'])
            ->get()
            ->sortBy(fn (Application $a) => $a->studentProfile->roll_no)
            ->values();

        $labels = ['selected' => $round->is_final ? 'Selected' : 'Shortlisted', 'waitlisted' => 'On Hold', 'rejected' => 'Not selected'];
        $rows = $applications->map(function (Application $a) use ($round, $labels) {
            $row = $a->roundResults->firstWhere('posting_round_id', $round->id);
            $label = $row && isset($labels[$row->result]) ? $labels[$row->result].($row->isPublished() ? '' : ' (draft)') : 'Undecided';

            return ['student' => $a->studentProfile, 'application' => $a, 'applied' => true, 'decision' => ['label' => $label]];
        });

        $company = $posting->company()?->name ?? 'Company';

        return $this->exports->templateWorkbook($template, $rows, $posting, 'admin', Str::slug($company.' '.$posting->title().' '.$round->name.' shortlist '.$template->name).'.xlsx', [
            sprintf('%s · %s: students shortlisted during \'%s\' to be proceeded to %s (Template: %s)', $company, $posting->title(), $round->name, $nextLabel, $template->name),
        ], footer: true);
    }

    /** Placement (cycle) student list with a custom template; `$narrow` limits the enrolments (enrolled-list filters). */
    public function cycleStudents(PlacementCycle $cycle, ExportTemplate $template, ?callable $narrow = null): StreamedResponse
    {
        $enrollments = $cycle->enrollments()->when($narrow !== null, fn ($query) => $narrow($query))
            ->with('studentProfile.user:id,is_active')->orderBy('id')->get();
        $rows = $enrollments->map(fn ($e) => ['student' => $e->studentProfile, 'enrollment' => $e]);

        return $this->exports->templateWorkbook($template, $rows, null, 'admin', Str::slug($cycle->name.' students '.$template->name).'.xlsx');
    }

    /**
     * A filtered student list (S4.4 "Download as Excel"), with the Default Template when none is given.
     *
     * @param  Builder<StudentProfile>  $query
     */
    public function students(Builder $query, ?ExportTemplate $template, string $fileName): StreamedResponse
    {
        $rows = $query->with('user:id,is_active')->orderBy('roll_no')->get()->map(fn (StudentProfile $s) => ['student' => $s]);
        $template ??= new ExportTemplate(['name' => 'Default', 'type' => 'STUDENT_LIST', 'columns' => self::STUDENTS_DEFAULT]);

        return $this->exports->templateWorkbook($template, $rows, null, 'admin', $fileName);
    }

    private function fileName(string $prefix, JobPosting $posting, ?ExportTemplate $template): string
    {
        $company = $posting->company()?->name ?? 'Company';
        $base = $prefix.' '.$posting->title().' at '.$company.($template ? ' Template '.$template->name : '');

        return Str::slug($base, '_').'.xlsx';
    }
}
