<?php

namespace App\Services;

use App\Models\ApplicationRoundResult;
use App\Models\CampusEvent;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Support\ProgrammeCatalogue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Audience groups for notices and surveys (Superset parity S7). A group is
 * ['audience_type' => ..., 'audience_filter' => [...]]; a student in ANY group is in the audience.
 * all / branches / posting_applicants reuse CampusEvent::audienceQuery (the event audience logic); the others add
 * a placement's enrolled students, a passout batch, the offer holders of a job profile, and a stage's PUBLISHED
 * shortlisted / on-hold students (draft decisions are never targetable, S7.2).
 */
class AudienceService
{
    public const EVENT_TYPES = ['all', 'branches', 'posting_applicants'];

    public const NOTICE_TYPES = ['all', 'branches', 'cycle', 'posting_applicants', 'round_results'];

    public const SURVEY_TYPES = ['all', 'branches', 'cycle', 'batch', 'offer_holders', 'posting_applicants'];

    /** Stage decisions a round audience may target. */
    public const ROUND_RESULTS = ['selected', 'waitlisted'];

    /**
     * Active students in any of the groups. No groups = nobody.
     *
     * @param  list<array{audience_type: string, audience_filter: array<string, mixed>|null}>  $groups
     */
    public function query(array $groups): Builder
    {
        $query = StudentProfile::query()->whereHas('user', fn (Builder $u) => $u->where('is_active', true));

        if ($groups === []) {
            return $query->whereRaw('1 = 0');
        }

        foreach ($groups as $group) {
            if (($group['audience_type'] ?? null) === 'all') {
                return $query; // one "all students" group covers everyone
            }
        }

        return $query->where(function (Builder $any) use ($groups): void {
            foreach ($groups as $group) {
                $any->orWhereIn('student_profiles.id', $this->groupQuery($group)->select('student_profiles.id'));
            }
        });
    }

    /**
     * Keep only the audience rows (`notice_audiences` / `survey_audiences`) whose group includes this student, in SQL,
     * so the student's Notices and Surveys pages filter and paginate in the database (M3). Mirrors groupQuery() type by
     * type: the student's own enrolments, applications, offers and published stage decisions are read once (four small
     * queries) and compared with the JSON filter of each row. Use it inside `whereHas('audiences', ...)`.
     */
    public function whereIncludes(Builder $rows, StudentProfile $student): Builder
    {
        $table = $rows->getModel()->getTable();
        $filter = fn (string $key) => "{$table}.audience_filter->{$key}";
        $type = "{$table}.audience_type";

        $cycles = $student->cycleEnrollments()->where('status', 'active')->pluck('placement_cycle_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $applied = $student->applications()->where('status', 'applied')->pluck('job_posting_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $offers = $student->offers()->pluck('job_posting_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $decisions = ApplicationRoundResult::query()
            ->whereHas('application', fn (Builder $a) => $a->where('student_profile_id', $student->id)->where('status', 'applied'))
            ->whereNotNull('published_at')
            ->whereIn('result', self::ROUND_RESULTS)
            ->get(['posting_round_id', 'result']);

        return $rows->where(function (Builder $any) use ($student, $type, $filter, $table, $cycles, $applied, $offers, $decisions): void {
            $any->where($type, 'all');

            if (filled($student->programme)) {
                [$sql, $bindings] = $this->branchMatch($any, $table, (string) $student->programme, (string) ($student->branch ?? ''));
                $any->orWhere(fn (Builder $q) => $q->where($type, 'branches')->whereRaw($sql, $bindings));
            }
            if ($cycles !== []) {
                $any->orWhere(fn (Builder $q) => $q->where($type, 'cycle')->whereIn($filter('placement_cycle_id'), $cycles));
            }
            if ($applied !== []) {
                $any->orWhere(fn (Builder $q) => $q->where($type, 'posting_applicants')->whereIn($filter('job_posting_id'), $applied));
            }
            if ($offers !== []) {
                $any->orWhere(fn (Builder $q) => $q->where($type, 'offer_holders')->whereIn($filter('job_posting_id'), $offers));
            }
            if ($student->graduating_batch) {
                $any->orWhere(fn (Builder $q) => $q->where($type, 'batch')->whereJsonContains($filter('batches'), (int) $student->graduating_batch));
            }
            foreach ($decisions as $decision) {
                $any->orWhere(fn (Builder $q) => $q->where($type, 'round_results')
                    ->where($filter('posting_round_id'), (int) $decision->posting_round_id)
                    ->whereJsonContains($filter('results'), (string) $decision->result));
            }
        });
    }

    /**
     * "Branches" group test for one student: some {programme, branch} pair names the student's programme and either
     * names no branch (the whole programme) or the student's branch — the same rule as CampusEvent::audienceQuery.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function branchMatch(Builder $query, string $table, string $programme, string $branch): array
    {
        $column = $query->getQuery()->getGrammar()->wrap("{$table}.audience_filter");

        // SQLite (tests) walks the array with json_each; MySQL 8 with JSON_TABLE (a missing or null branch reads as NULL).
        [$from, $programmeCol, $branchCol] = $query->getConnection()->getDriverName() === 'sqlite'
            ? ["json_each({$column}, '$.branches') as p", "json_extract(p.value, '$.programme')", "json_extract(p.value, '$.branch')"]
            : ["json_table({$column}, '$.branches[*]' columns (programme varchar(255) path '$.programme', branch varchar(255) path '$.branch')) as p", 'p.programme', 'p.branch'];

        return ["exists (select 1 from {$from} where {$programmeCol} = ? and (coalesce({$branchCol}, '') = '' or {$branchCol} = ?))", [$programme, $branch]];
    }

    /**
     * @param  array{audience_type: string, audience_filter: array<string, mixed>|null}  $group
     */
    private function groupQuery(array $group): Builder
    {
        $type = (string) ($group['audience_type'] ?? '');
        $filter = is_array($group['audience_filter'] ?? null) ? $group['audience_filter'] : [];

        if (in_array($type, self::EVENT_TYPES, true)) {
            return (new CampusEvent(['audience_type' => $type, 'audience_filter' => $filter]))->audienceQuery();
        }

        $query = StudentProfile::query();

        return match ($type) {
            'cycle' => $query->whereHas('cycleEnrollments', fn (Builder $e) => $e
                ->where('placement_cycle_id', (int) ($filter['placement_cycle_id'] ?? 0))
                ->where('status', 'active')),
            'batch' => $query->whereIn('graduating_batch', array_map('intval', (array) ($filter['batches'] ?? []))),
            'offer_holders' => $query->whereHas('offers', fn (Builder $o) => $o->where('job_posting_id', (int) ($filter['job_posting_id'] ?? 0))),
            'round_results' => $query->whereHas('applications', fn (Builder $a) => $a
                ->where('status', 'applied')
                ->whereHas('roundResults', fn (Builder $r) => $r
                    ->where('posting_round_id', (int) ($filter['posting_round_id'] ?? 0))
                    ->whereNotNull('published_at')
                    ->whereIn('result', array_values(array_intersect((array) ($filter['results'] ?? []), self::ROUND_RESULTS))))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Validate and normalise submitted groups. Throws a 422 ValidationException on bad input.
     *
     * @param  list<string>  $allowedTypes
     * @return list<array{audience_type: string, audience_filter: array<string, mixed>|null}>
     */
    public function normalise(mixed $groups, array $allowedTypes): array
    {
        $validator = Validator::make(['audiences' => $groups], [
            'audiences' => ['present', 'array', 'max:20'],
            'audiences.*.audience_type' => ['required', 'in:'.implode(',', $allowedTypes)],
            'audiences.*.audience_filter' => ['nullable', 'array'],
        ]);
        $validator->validate();

        $out = [];
        foreach (array_values((array) $groups) as $index => $group) {
            $type = $group['audience_type'];
            $filter = (array) ($group['audience_filter'] ?? []);
            $fail = fn (string $message) => throw ValidationException::withMessages(["audiences.{$index}" => $message]);

            $out[] = ['audience_type' => $type, 'audience_filter' => match ($type) {
                'all' => null,
                'branches' => (function () use ($filter, $fail) {
                    $pairs = array_values(array_filter((array) ($filter['branches'] ?? []), 'is_array'));
                    if ($pairs === []) {
                        $fail('Pick at least one programme or branch.');
                    }
                    $clean = [];
                    foreach ($pairs as $pair) {
                        $programme = (string) ($pair['programme'] ?? '');
                        $branch = isset($pair['branch']) && $pair['branch'] !== '' ? (string) $pair['branch'] : null;
                        $ok = $branch === null ? ProgrammeCatalogue::hasProgramme($programme) : ProgrammeCatalogue::has($programme, $branch);
                        if (! $ok) {
                            $fail(sprintf('"%s" is not in the programme catalogue.', $branch ?? $programme));
                        }
                        $clean[] = ['programme' => $programme, 'branch' => $branch];
                    }

                    return ['branches' => $clean];
                })(),
                'cycle' => PlacementCycle::query()->whereKey((int) ($filter['placement_cycle_id'] ?? 0))->exists()
                    ? ['placement_cycle_id' => (int) $filter['placement_cycle_id']]
                    : $fail('Pick the placement.'),
                'batch' => (function () use ($filter, $fail) {
                    $batches = array_values(array_unique(array_filter(array_map('intval', (array) ($filter['batches'] ?? [])), fn ($b) => $b >= 2000 && $b <= 2100)));
                    if ($batches === []) {
                        $fail('Pick at least one passout batch.');
                    }

                    return ['batches' => $batches];
                })(),
                'posting_applicants', 'offer_holders' => JobPosting::query()->whereKey((int) ($filter['job_posting_id'] ?? 0))->exists()
                    ? ['job_posting_id' => (int) $filter['job_posting_id']]
                    : $fail('Pick the job profile.'),
                'round_results' => (function () use ($filter, $fail) {
                    $round = PostingRound::query()->find((int) ($filter['posting_round_id'] ?? 0));
                    if (! $round) {
                        $fail('Pick the stage.');
                    }
                    $results = array_values(array_unique(array_intersect((array) ($filter['results'] ?? []), self::ROUND_RESULTS)));
                    if ($results === []) {
                        $fail('Pick shortlisted and/or on-hold students.');
                    }

                    return ['job_posting_id' => $round->job_posting_id, 'posting_round_id' => $round->id, 'results' => $results];
                })(),
                default => $fail('Unknown audience.'),
            }];
        }

        return $out;
    }

    /**
     * The single job profile every group points at, if any (for email_logs.job_posting_id and the notice's context).
     *
     * @param  list<array{audience_type: string, audience_filter: array<string, mixed>|null}>  $groups
     */
    public function postingOf(array $groups): ?int
    {
        $ids = collect($groups)->map(fn ($g) => $g['audience_filter']['job_posting_id'] ?? null)->unique()->values();

        return $ids->count() === 1 && $ids->first() !== null ? (int) $ids->first() : null;
    }

    /**
     * Human-readable label of a group for admin screens.
     *
     * @param  array{audience_type: string, audience_filter: array<string, mixed>|null}  $group
     */
    public function describe(array $group): string
    {
        $filter = $group['audience_filter'] ?? [];
        $postingLabel = function (?int $id): string {
            $posting = $id ? JobPosting::query()->find($id) : null;

            return $posting ? trim(($posting->company()?->name ?? '').' — '.$posting->title(), ' —') : 'a job profile';
        };

        return match ($group['audience_type']) {
            'all' => 'All students',
            'branches' => collect($filter['branches'] ?? [])->map(fn ($p) => $p['branch'] ?: 'All of '.$p['programme'])->implode(', '),
            'cycle' => 'Students enrolled in '.(PlacementCycle::query()->find($filter['placement_cycle_id'] ?? 0)?->name ?? 'a placement'),
            'batch' => 'Passout Batch '.implode(', ', $filter['batches'] ?? []),
            'offer_holders' => 'Offer holders of '.$postingLabel($filter['job_posting_id'] ?? null),
            'posting_applicants' => 'Applicants of '.$postingLabel($filter['job_posting_id'] ?? null),
            'round_results' => (function () use ($filter, $postingLabel) {
                $round = PostingRound::query()->find($filter['posting_round_id'] ?? 0);
                $who = collect($filter['results'] ?? [])->map(fn ($r) => $r === 'selected' ? 'Shortlisted' : 'On Hold')->implode(' / ');

                return "{$who} in ".($round?->name ?? 'a stage').' · '.$postingLabel($filter['job_posting_id'] ?? null);
            })(),
            default => $group['audience_type'],
        };
    }
}
