<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\PostingRound;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Company bulk proposals from a sheet (owner decisions 2026-10-20, P3-D56). Students are identified by roll number
 * only. Bad rows are skipped and reported; the rest is proposed. Two shapes:
 * - the stage's decision sheet: Roll No + Decision (Shortlisted / On Hold; blank = not shortlisted), only students
 *   of that stage's pool;
 * - a roll-number list (first column) for one chosen proposal type.
 */
class CompanyProposalUploadService
{
    private const ROLL_HEADERS = ['rollno', 'rollnumber', 'roll', 'institutorollnumber'];

    private const DECISION_HEADERS = ['decision', 'result', 'status'];

    private const SHORTLISTED = ['shortlisted', 'shortlist', 'selected', 'select', 's', 'yes', 'y'];

    private const ON_HOLD = ['onhold', 'hold', 'waitlisted', 'waitlist', 'h', 'w'];

    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    /**
     * Rows of the stage's sheet: its pool (every live applicant at the first stage, the published shortlist of the
     * previous stage after that) — only what the company may already see.
     *
     * @return \Illuminate\Support\Collection<int, Application>
     */
    public function pool(PostingRound $round)
    {
        return $this->pipeline->pool($round)->load('studentProfile:id,roll_no,full_name,programme,branch,current_cgpa')
            ->sortBy(fn (Application $a) => $a->studentProfile?->roll_no)->values();
    }

    /**
     * @return array{shortlist: list<string>, waitlist: list<string>, errors: list<array{roll_no: string, reason: string}>, blank: int}
     */
    public function readDecisionSheet(UploadedFile $file, JobPosting $posting, PostingRound $round): array
    {
        $rows = $this->spreadsheets->rows($file);
        [$headerRow, $rollCol, $decisionCol] = $this->header($rows);

        $out = ['shortlist' => [], 'waitlist' => [], 'errors' => [], 'blank' => 0];
        $seen = [];
        $marked = [];
        foreach ($rows as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }
            $roll = strtoupper(trim((string) ($cells[$rollCol] ?? '')));
            $raw = trim((string) ($cells[$decisionCol] ?? ''));
            if ($roll === '') {
                continue;
            }
            if (isset($seen[$roll])) {
                $out['errors'][] = ['roll_no' => $roll, 'reason' => "Row {$number}: listed more than once (first on row {$seen[$roll]}). Only the first row was used."];

                continue;
            }
            $seen[$roll] = $number;
            if ($raw === '') {
                $out['blank']++;

                continue;
            }
            $kind = self::decision($raw);
            if ($kind === null) {
                $out['errors'][] = ['roll_no' => $roll, 'reason' => "Row {$number}: \"{$raw}\" is not Shortlisted or On Hold."];

                continue;
            }
            $marked[$roll] = ['kind' => $kind, 'row' => $number];
        }

        // Only applicants of this job profile who are in this stage.
        [$found, $unknown] = $this->pipeline->resolveApplicants($posting, array_keys($marked), forCompany: true);
        $inStage = $this->pipeline->pool($round)->pluck('id')->flip();
        foreach ($unknown as $u) {
            $out['errors'][] = ['roll_no' => $u['roll_no'], 'reason' => 'Row '.($marked[$u['roll_no']]['row'] ?? '?').': '.$u['reason'].' Skipped.'];
        }
        foreach ($found as $roll => $application) {
            if (! $inStage->has($application->id)) {
                $out['errors'][] = ['roll_no' => $roll, 'reason' => "Row {$marked[$roll]['row']}: not in this stage (not shortlisted in the previous stage). Skipped."];

                continue;
            }
            $out[$marked[$roll]['kind']][] = $roll;
        }

        return $out;
    }

    /**
     * A plain list of roll numbers (first column; a "Roll No" header row is skipped).
     *
     * @return array{rolls: list<string>, errors: list<array{roll_no: string, reason: string}>}
     */
    public function readRollList(UploadedFile $file, JobPosting $posting): array
    {
        $rolls = $this->spreadsheets->firstColumn($file, ...self::ROLL_HEADERS);
        $rolls = array_map(fn ($r) => strtoupper(trim((string) $r)), $rolls);
        $errors = [];
        $unique = [];
        foreach ($rolls as $roll) {
            if ($roll === '') {
                continue;
            }
            if (isset($unique[$roll])) {
                $errors[] = ['roll_no' => $roll, 'reason' => 'Listed more than once. Used once.'];

                continue;
            }
            $unique[$roll] = true;
        }

        [$found, $unknown] = $this->pipeline->resolveApplicants($posting, array_keys($unique), forCompany: true);
        foreach ($unknown as $u) {
            $errors[] = ['roll_no' => $u['roll_no'], 'reason' => $u['reason'].' Skipped.'];
        }

        return ['rolls' => array_keys($found), 'errors' => $errors];
    }

    public static function decision(string $raw): ?string
    {
        $key = preg_replace('/[^a-z]/', '', strtolower($raw));

        return match (true) {
            in_array($key, self::SHORTLISTED, true) => 'shortlist',
            in_array($key, self::ON_HOLD, true) => 'waitlist',
            default => null,
        };
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function header(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $number => $cells) {
            $roll = null;
            $decision = null;
            foreach ($cells as $index => $cell) {
                $key = preg_replace('/[^a-z]/', '', strtolower((string) $cell));
                if ($roll === null && in_array($key, self::ROLL_HEADERS, true)) {
                    $roll = $index;
                } elseif ($decision === null && in_array($key, self::DECISION_HEADERS, true)) {
                    $decision = $index;
                }
            }
            if ($roll !== null && $decision !== null) {
                return [$number, $roll, $decision];
            }
        }

        throw new RuntimeException('The sheet needs a "Roll No" column and a "Decision" column. Download the decision sheet for this stage and fill it in.');
    }
}
