<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\PostingRound;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * P6.2 stage attendance (owner decisions 2026-10-19, P3-D53): marked by the CDC per stage, by pasted roll numbers or
 * an Excel/CSV sheet (preview first). Values are Present / Absent only; a student left out (or with a blank cell) is
 * left as they were. Attendance can be corrected after the stage is published (like stage decisions since
 * 2026-10-18). Companies and students see it only on rows whose result has been published.
 */
class AttendanceService
{
    /** Header names (lower case, letters only) that identify the two columns of an uploaded sheet. */
    private const ROLL_HEADERS = ['rollno', 'rollnumber', 'roll', 'institutorollnumber', 'admissionno'];

    private const ATTENDANCE_HEADERS = ['attendance', 'present', 'presentabsent', 'status'];

    private const PRESENT = ['present', 'p', 'yes', 'y', 'appeared', '1', 'true'];

    private const ABSENT = ['absent', 'a', 'no', 'n', 'notappeared', '0', 'false'];

    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    /**
     * Who belongs on the stage's attendance sheet: its pool (every live applicant for the first stage, the published
     * shortlist of the previous stage after that) plus anyone who already has a row in this stage.
     *
     * @return Collection<int, Application>
     */
    public function sheetApplications(PostingRound $round): Collection
    {
        $pool = $this->pipeline->pool($round)->pluck('id');
        $withRow = $round->results()->pluck('application_id');

        return Application::query()
            ->whereIn('id', $pool->merge($withRow)->unique()->values())
            ->where('status', 'applied')
            ->with(['studentProfile:id,roll_no,full_name,programme,branch,graduating_batch', 'roundResults' => fn ($q) => $q->where('posting_round_id', $round->id)])
            ->get()
            ->sortBy(fn (Application $a) => $a->studentProfile?->roll_no)
            ->values();
    }

    /**
     * Read an uploaded sheet: the header row (within the first 10 rows) names a roll-number column and an attendance
     * column. Blank attendance cells are skipped (left unmarked).
     *
     * @return array{0: array<string, 'yes'|'no'>, 1: list<array{roll_no: string, reason: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $rows = $this->spreadsheets->rows($file);
        [$headerRow, $rollCol, $attCol] = $this->findHeader($rows);

        $marks = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }
            $roll = strtoupper(trim((string) ($cells[$rollCol] ?? '')));
            $raw = trim((string) ($cells[$attCol] ?? ''));
            if ($roll === '') {
                continue; // footer lines, empty rows
            }
            if ($raw === '') {
                continue; // left unmarked on purpose
            }
            $value = self::normalise($raw);
            if ($value === null) {
                $errors[] = ['roll_no' => $roll, 'reason' => "Row {$number}: \"{$raw}\" is not Present or Absent."];

                continue;
            }
            if (isset($seen[$roll])) {
                $errors[] = ['roll_no' => $roll, 'reason' => "Row {$number}: listed more than once (first on row {$seen[$roll]}). Only the first row was used."];

                continue;
            }
            $seen[$roll] = $number;
            $marks[$roll] = $value;
        }

        return [$marks, $errors];
    }

    /**
     * Write (or, with $dryRun, only count) attendance for this stage. Unknown or withdrawn students are reported;
     * students outside the stage's pool are marked with a warning, as for stage results.
     *
     * @param  array<string, 'yes'|'no'>  $marks  roll number => attendance
     * @return array{present: int, absent: int, unchanged: int, corrected: list<array{roll_no: string, from: string, to: string}>,
     *               errors: list<array{roll_no: string, reason: string}>, warnings: list<array{roll_no: string, reason: string}>}
     */
    public function apply(JobPosting $posting, PostingRound $round, array $marks, bool $dryRun = false): array
    {
        [$found, $errors] = $this->pipeline->resolveApplicants($posting, array_keys($marks));
        $sheet = $this->sheetApplications($round)->pluck('id')->flip();

        $report = ['present' => 0, 'absent' => 0, 'unchanged' => 0, 'corrected' => [], 'errors' => $errors, 'warnings' => []];

        DB::transaction(function () use ($found, $marks, $round, $sheet, $dryRun, &$report): void {
            foreach ($found as $roll => $application) {
                $value = $marks[$roll] ?? $marks[strtoupper($roll)] ?? null;
                if ($value === null) {
                    continue;
                }
                if (! $sheet->has($application->id)) {
                    $report['warnings'][] = ['roll_no' => $roll, 'reason' => 'Not in this stage (not shortlisted in the previous stage). Marked anyway.'];
                }

                $row = ApplicationRoundResult::query()
                    ->where('application_id', $application->id)
                    ->where('posting_round_id', $round->id)
                    ->lockForUpdate()
                    ->first();

                if ($row && $row->attendance === $value) {
                    $report['unchanged']++;

                    continue;
                }
                if ($row && $row->attendance !== null && $row->visibleResult() !== null) {
                    // A correction to what the company and student already see (allowed, audited by the caller).
                    $report['corrected'][] = ['roll_no' => $roll, 'from' => $row->attendance, 'to' => $value];
                }
                $report[$value === 'yes' ? 'present' : 'absent']++;

                if ($dryRun) {
                    continue;
                }
                $row ??= new ApplicationRoundResult(['application_id' => $application->id, 'posting_round_id' => $round->id]);
                $row->attendance = $value;
                $row->result ??= 'pending';
                $row->save();
            }
        });

        return $report;
    }

    public static function normalise(string $raw): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($raw));

        return match (true) {
            in_array($key, self::PRESENT, true) => 'yes',
            in_array($key, self::ABSENT, true) => 'no',
            default => null,
        };
    }

    /** @return array{0: int, 1: int, 2: int} header row number, roll column index, attendance column index */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $number => $cells) {
            $roll = null;
            $att = null;
            foreach ($cells as $index => $cell) {
                $key = preg_replace('/[^a-z]/', '', strtolower((string) $cell));
                if ($roll === null && in_array($key, self::ROLL_HEADERS, true)) {
                    $roll = $index;
                } elseif ($att === null && in_array($key, self::ATTENDANCE_HEADERS, true)) {
                    $att = $index;
                }
            }
            if ($roll !== null && $att !== null) {
                return [$number, $roll, $att];
            }
        }

        throw new RuntimeException('The sheet needs a "Roll No" column and an "Attendance" column. Download the attendance sheet for this stage and fill it in.');
    }
}
