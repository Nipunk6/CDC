<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single implementation of spec rule B2. Every caller (board badge, apply,
 * float-time audience, admin eligible list, exports) goes through this class.
 *
 * check() and eligibleStudentsQuery() encode the same rules; EligibilityServiceTest
 * asserts they agree.
 */
final class EligibilityService
{
    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<int, \Illuminate\Support\Collection<int, object>> active blocks per student, memoised per request */
    private array $blocks = [];

    /** Forget memoised blocks (after creating/removing blocks within the same request). */
    public function flush(): void
    {
        $this->blocks = [];
    }

    /**
     * @return array{eligible: bool, reasons: list<string>} reasons are student-facing sentences
     */
    public function check(StudentProfile $student, JobPosting $posting): array
    {
        $reasons = [];
        $rules = $posting->eligibilityRules();

        // 1. Enrolled and active in the posting's cycle.
        $enrollment = $student->relationLoaded('cycleEnrollments')
            ? $student->cycleEnrollments->firstWhere('placement_cycle_id', $posting->placement_cycle_id)
            : $student->cycleEnrollments()->where('placement_cycle_id', $posting->placement_cycle_id)->first();

        if (! $enrollment) {
            $reasons[] = 'You are not enrolled in this placement cycle.';
        } elseif ($enrollment->status !== 'active') {
            $reasons[] = 'Your enrolment in this placement cycle is suspended.';
        }

        // 2. Account active.
        if ($student->user && $student->user->is_active === false) {
            $reasons[] = 'Your account is suspended.';
        }

        // 3 + 4. Blocks and debarment.
        foreach ($this->blockingBlocks($student, $posting) as $message) {
            $reasons[] = $message;
        }

        // 5. Programme + branch selected in the matrix.
        $row = null;
        $programme = $this->findProgramme($rules, (string) $student->programme);

        if ($programme === null) {
            $reasons[] = 'Your programme is not eligible.';
        } else {
            $row = $this->findBranch($programme, (string) $student->branch);
            if ($row === null) {
                $reasons[] = 'Your branch is not eligible.';
            }
        }

        if ($row !== null) {
            // 6. CGPA.
            $cutoff = $this->number($row['cgpa'] ?? null);
            if ($cutoff !== null) {
                if ($student->current_cgpa === null) {
                    $reasons[] = sprintf('CGPA not on record (cutoff %s).', $this->fmt($cutoff));
                } elseif ($this->lessThan((float) $student->current_cgpa, $cutoff)) {
                    $reasons[] = sprintf('CGPA below cutoff (%s < %s)', $this->fmt((float) $student->current_cgpa), $this->fmt($cutoff));
                }
            }

            // 7. Backlogs (D57).
            $ongoing = (int) $student->ongoing_backlogs;
            $total = (int) $student->total_backlogs;

            if (! $this->truthy($row['backlogsAllowed'] ?? false)) {
                if ($ongoing > 0 || $total > 0) {
                    $reasons[] = sprintf('No backlogs allowed (you have %d ongoing, %d total).', $ongoing, $total);
                }
            } else {
                $maxOngoing = $this->number($row['maxOngoingBacklogs'] ?? null);
                $maxTotal = $this->number($row['maxTotalBacklogs'] ?? null);

                if ($maxOngoing !== null && $ongoing > $maxOngoing) {
                    $reasons[] = sprintf('Ongoing backlogs above the limit (%d > %d).', $ongoing, (int) $maxOngoing);
                }
                if ($maxTotal !== null && $total > $maxTotal) {
                    $reasons[] = sprintf('Total backlogs above the limit (%d > %d).', $total, (int) $maxTotal);
                }
            }
        }

        // 8. Gender.
        $gender = $this->genderFilter($rules);
        if ($gender !== null && $student->gender !== $gender) {
            $reasons[] = sprintf('Open to %s students only.', $gender);
        }

        // 9. Graduating batch (D68: form-level batch, else the programme row's batches; PhD exempt; blank = any).
        $batches = $this->batchesFor($rules, $programme, (string) $student->programme);
        if ($batches !== [] && ! in_array((string) $student->graduating_batch, $batches, true)) {
            $reasons[] = sprintf('Open to the %s graduating batch only.', implode(' / ', $batches));
        }

        // 10. School marks.
        foreach (['minTenthPercent' => ['tenth_percent', '10th'], 'minTwelfthPercent' => ['twelfth_percent', '12th']] as $key => [$column, $label]) {
            $min = $this->number($rules[$key] ?? null);
            if ($min === null) {
                continue;
            }
            $value = $student->{$column};
            if ($value === null) {
                $reasons[] = sprintf('%s %% not on record (cutoff %s).', $label, $this->fmt($min));
            } elseif ($this->lessThan((float) $value, $min)) {
                $reasons[] = sprintf('%s %% below cutoff (%s < %s)', $label, $this->fmt((float) $value), $this->fmt($min));
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    /**
     * Whether the posting is open to the student's programme + branch at all. Owner decision (QA T3.2, 2026-10-01):
     * a drive whose branch list leaves the student out is not shown to them; one they miss for any other reason
     * (CGPA, backlogs, a block, …) is shown with the reason.
     */
    public function offersBranch(StudentProfile $student, JobPosting $posting): bool
    {
        $programme = $this->findProgramme($posting->eligibilityRules(), (string) $student->programme);

        return $programme !== null && $this->findBranch($programme, (string) $student->branch) !== null;
    }

    /**
     * Every eligible student for a posting, as a query (float-time E2 audience, admin lists, exports).
     * Works for an unsaved JobPosting too (the float dialog's preview).
     */
    public function eligibleStudentsQuery(JobPosting $posting): Builder
    {
        $rules = $posting->eligibilityRules();
        $cycleId = $posting->placement_cycle_id;

        $query = StudentProfile::query()
            ->whereHas('user', fn (Builder $u) => $u->where('is_active', true))
            ->whereHas('cycleEnrollments', fn (Builder $e) => $e->where('placement_cycle_id', $cycleId)->where('status', 'active'));

        if ($this->tableExists('placement_blocks')) {
            $internship = $posting->postingType() === 'internship';
            $query->whereNotExists(function ($sub) use ($cycleId, $internship): void {
                $sub->select(DB::raw(1))
                    ->from('placement_blocks')
                    ->whereColumn('placement_blocks.student_profile_id', 'student_profiles.id')
                    ->where('placement_blocks.placement_cycle_id', $cycleId)
                    ->where('placement_blocks.active', true)
                    ->where(function ($scope) use ($internship): void {
                        $scope->where('placement_blocks.scope', 'all')
                            ->orWhere('placement_blocks.reason', 'debarred');
                        if ($internship) {
                            $scope->orWhere('placement_blocks.scope', 'internships_only');
                        }
                    });
            });
        }

        $gender = $this->genderFilter($rules);
        if ($gender !== null) {
            $query->where('gender', $gender);
        }

        foreach (['minTenthPercent' => 'tenth_percent', 'minTwelfthPercent' => 'twelfth_percent'] as $key => $column) {
            $min = $this->number($rules[$key] ?? null);
            if ($min !== null) {
                $query->where($column, '>=', round($min, 2));
            }
        }

        $rows = $this->selectedRows($rules);

        if ($rows === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $matrix) use ($rows, $rules): void {
            foreach ($rows as [$programme, $row, $entry]) {
                $matrix->orWhere(function (Builder $q) use ($programme, $row, $entry, $rules): void {
                    $q->where('programme', $programme)->where('branch', (string) $row['branch']);

                    $batches = $this->batchesFor($rules, $entry, $programme);
                    if ($batches !== []) {
                        $q->whereIn('graduating_batch', array_map('intval', $batches));
                    }

                    $cutoff = $this->number($row['cgpa'] ?? null);
                    if ($cutoff !== null) {
                        $q->where('current_cgpa', '>=', round($cutoff, 2));
                    }

                    if (! $this->truthy($row['backlogsAllowed'] ?? false)) {
                        $q->where('ongoing_backlogs', 0)->where('total_backlogs', 0);
                    } else {
                        $maxOngoing = $this->number($row['maxOngoingBacklogs'] ?? null);
                        $maxTotal = $this->number($row['maxTotalBacklogs'] ?? null);
                        if ($maxOngoing !== null) {
                            $q->where('ongoing_backlogs', '<=', $maxOngoing);
                        }
                        if ($maxTotal !== null) {
                            $q->where('total_backlogs', '<=', $maxTotal);
                        }
                    }
                });
            }
        });
    }

    /**
     * Student-facing sentences for the active blocks that stop this student applying to this posting.
     *
     * @return list<string>
     */
    private function blockingBlocks(StudentProfile $student, JobPosting $posting): array
    {
        if (! $this->tableExists('placement_blocks')) {
            return [];
        }

        // One query per student per request, however many postings are checked (the job board checks them all).
        $this->blocks[$student->id] ??= DB::table('placement_blocks')
            ->leftJoin('offers', 'offers.id', '=', 'placement_blocks.offer_id')
            ->where('placement_blocks.student_profile_id', $student->id)
            ->where('placement_blocks.active', true)
            ->get(['placement_blocks.placement_cycle_id', 'placement_blocks.scope', 'placement_blocks.reason', 'placement_blocks.remark', 'offers.offer_type']);

        $blocks = $this->blocks[$student->id]->where('placement_cycle_id', $posting->placement_cycle_id);

        $messages = [];
        foreach ($blocks as $block) {
            $applies = $block->reason === 'debarred'
                || $block->scope === 'all'
                || ($block->scope === 'internships_only' && $posting->postingType() === 'internship');

            if ($applies) {
                $messages[] = self::blockMessage($block->reason, $block->scope, $block->offer_type, $block->remark);
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * Shared wording for a block (also used on the student profile).
     */
    public static function blockMessage(string $reason, string $scope, ?string $offerType, ?string $remark): string
    {
        if ($reason === 'debarred') {
            return 'You are debarred from this placement cycle.'.($remark ? " ({$remark})" : '');
        }

        if ($reason === 'offer') {
            $offer = [
                'intern' => 'an Internship offer',
                'intern_ppo' => 'a PPO',
                'fulltime' => 'a Full-Time offer',
                'intern_fulltime' => 'an Intern + Full-Time offer',
                'intern_performance_ppo' => 'an Intern + performance-based PPO offer',
                'ppo_offered' => 'a PPO offer',
            ][$offerType ?? ''] ?? 'an offer';

            return $scope === 'internships_only'
                ? "Blocked from internships: accepted {$offer}."
                : "Blocked: accepted {$offer}.";
        }

        return 'Blocked by the CDC'.($scope === 'internships_only' ? ' for internships' : '').($remark ? ": {$remark}" : '.');
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}> [programme name, branch row, programme entry]
     */
    private function selectedRows(array $rules): array
    {
        $rows = [];

        foreach (is_array($rules['eligibility'] ?? null) ? $rules['eligibility'] : [] as $programme) {
            if (! is_array($programme) || ! is_array($programme['branches'] ?? null)) {
                continue;
            }
            foreach ($programme['branches'] as $row) {
                if (is_array($row) && $this->truthy($row['selected'] ?? false) && isset($row['branch'])) {
                    $rows[] = [(string) ($programme['programme'] ?? ''), $row, $programme];
                }
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null the programme entry, only if it has at least one selected branch */
    private function findProgramme(array $rules, string $programme): ?array
    {
        foreach (is_array($rules['eligibility'] ?? null) ? $rules['eligibility'] : [] as $entry) {
            if (is_array($entry) && $this->same((string) ($entry['programme'] ?? ''), $programme)) {
                foreach (is_array($entry['branches'] ?? null) ? $entry['branches'] : [] as $row) {
                    if (is_array($row) && $this->truthy($row['selected'] ?? false)) {
                        return $entry;
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findBranch(array $programme, string $branch): ?array
    {
        foreach ($programme['branches'] as $row) {
            if (is_array($row) && $this->truthy($row['selected'] ?? false) && $this->same((string) ($row['branch'] ?? ''), $branch)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Allowed graduating batches for a student's programme (D68). The form-level `graduatingBatch` wins; legacy
     * forms that only stored per-programme `graduatingBatches`/`graduatingBatch` use those. PhD rows are exempt
     * because the eligibility grid never asks a batch for PhD. Empty list = any batch.
     *
     * @param  array<string, mixed>|null  $entry  the programme's eligibility entry
     * @return list<string>
     */
    private function batchesFor(array $rules, ?array $entry, string $programme): array
    {
        if (preg_match('/ph\.?d/i', $programme)) {
            return [];
        }

        $top = trim((string) ($rules['graduatingBatch'] ?? ''));
        if ($top !== '') {
            return [$top];
        }

        $list = is_array($entry['graduatingBatches'] ?? null) ? $entry['graduatingBatches'] : [];
        if ($list === [] && ! empty($entry['graduatingBatch'])) {
            $list = [$entry['graduatingBatch']];
        }

        return array_values(array_filter(array_map(fn ($b) => trim((string) $b), $list), fn ($b) => $b !== ''));
    }

    private function genderFilter(array $rules): ?string
    {
        $gender = strtolower(trim((string) ($rules['genderFilter'] ?? 'all')));

        return in_array($gender, ['male', 'female'], true) ? $gender : null;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || is_bool($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /** Compare at 2-decimal precision, as the DB columns are decimal(…,2). */
    private function lessThan(float $value, float $cutoff): bool
    {
        return (int) round($value * 100) < (int) round($cutoff * 100);
    }

    private function same(string $a, string $b): bool
    {
        return strcasecmp(trim($a), trim($b)) === 0;
    }

    private function fmt(float $value): string
    {
        // 7 → "7.0", 6.85 → "6.85" (matches how cutoffs are typed on the form).
        $text = number_format($value, 2, '.', '');

        return str_ends_with($text, '0') ? substr($text, 0, -1) : $text;
    }

    private function tableExists(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }
}
