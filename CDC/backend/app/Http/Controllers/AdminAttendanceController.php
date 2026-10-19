<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\SpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * P6.2 stage attendance by Excel (owner decisions 2026-10-19, P3-D53): download the stage's attendance sheet (its
 * students, with any attendance already marked), fill Present / Absent, upload it — first as a preview, then for real.
 */
class AdminAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AuditService $audit,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    public function template(JobPosting $jobPosting, PostingRound $postingRound, ExportService $exports): StreamedResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $rows = $this->attendance->sheetApplications($postingRound)->map(function (Application $a) {
            $s = $a->studentProfile;
            $current = $a->roundResults->first()?->attendance;

            return [$s?->roll_no, $s?->full_name, $s?->programme, $s?->branch, $s?->graduating_batch,
                $current === 'yes' ? 'Present' : ($current === 'no' ? 'Absent' : '')];
        });

        $company = $jobPosting->company()?->name ?? 'Company';

        return $exports->tableWorkbook(
            sprintf('%s · %s · %s: attendance (fill Present or Absent; a blank cell leaves the student unmarked)', $company, $jobPosting->title(), $postingRound->name),
            ['Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'Attendance'],
            $rows,
            Str::slug($company.'-'.$postingRound->name).'-attendance.xlsx',
            'Attendance'
        );
    }

    public function upload(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');
        if ($jobPosting->status === 'cancelled') {
            return response()->json(['message' => 'This job profile is cancelled.'], 422);
        }
        if ($jobPosting->acceptsApplications()) {
            return response()->json(['message' => 'Applications are still open. Close applications (or wait for the deadline) before marking attendance.'], 422);
        }

        $request->validate([
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
            'dry_run' => ['nullable', 'boolean'],
        ]);

        try {
            [$marks, $parseErrors] = $this->attendance->parse($request->file('file'));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if ($marks === [] && $parseErrors === []) {
            return response()->json(['message' => 'No attendance was found in the sheet. Fill Present or Absent in the Attendance column.'], 422);
        }

        $dryRun = $request->boolean('dry_run');
        $report = $this->attendance->apply($jobPosting, $postingRound, $marks, $dryRun);
        $report['errors'] = array_merge($parseErrors, $report['errors']);

        if ($dryRun) {
            return response()->json([
                'message' => sprintf('Preview: %d present, %d absent, %d unchanged%s. %d row(s) with problems. Nothing is saved yet.',
                    $report['present'], $report['absent'], $report['unchanged'],
                    $report['corrected'] ? sprintf(', including %d correction(s) to published stages', count($report['corrected'])) : '',
                    count($report['errors'])),
                'dry_run' => true,
            ] + $report);
        }

        return response()->json(self::respond($request, $this->audit, $postingRound, $report, 'sheet'));
    }

    /**
     * Audit one attendance save and build its answer. Shared by the pasted lists (AdminPipelineController) and the
     * sheet upload, so both read the same.
     *
     * @param  array{present: int, absent: int, unchanged: int, corrected: list<array>, errors: list<array>, warnings: list<array>}  $report
     * @return array<string, mixed>
     */
    public static function respond(Request $request, AuditService $audit, PostingRound $round, array $report, string $source): array
    {
        $written = $report['present'] + $report['absent'];
        $audit->log($request, 'round.attendance', $round,
            $report['corrected'] ? ['corrected' => array_map(fn ($c) => $c['roll_no'].':'.$c['from'], array_slice($report['corrected'], 0, 100))] : null,
            [
                'source' => $source,
                'present' => $report['present'],
                'absent' => $report['absent'],
                'corrected' => array_map(fn ($c) => $c['roll_no'].':'.$c['to'], array_slice($report['corrected'], 0, 100)),
            ]);

        return [
            'message' => sprintf('Attendance saved for %d student(s)%s. %d already as marked. %d not applied.',
                $written,
                $report['corrected'] ? sprintf(', including %d correction(s) to published stages', count($report['corrected'])) : '',
                $report['unchanged'],
                count($report['errors'])),
            'present' => $report['present'],
            'absent' => $report['absent'],
            'unchanged' => $report['unchanged'],
            'corrected' => $report['corrected'],
            'errors' => $report['errors'],
            'warnings' => $report['warnings'],
        ];
    }
}
