<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PostingRound;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\PipelineService;
use App\Services\TemplateExports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Shortlist for <Stage>" (Superset parity S1): one stage's candidates with their current decision. Read-only here;
 * decisions are written through AdminPipelineController::results as drafts and published as before (D70, D75).
 */
class AdminShortlistController extends Controller
{
    public const PER_PAGE = 50;

    private const SORTS = ['name', 'roll_no', 'cgpa', 'decision'];

    private const DECISIONS = ['all', 'selected', 'waitlisted', 'rejected', 'undecided'];

    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly ExportService $exports,
        private readonly AuditService $audit
    ) {
    }

    public function show(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
            'decision' => ['nullable', 'in:'.implode(',', self::DECISIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $rows = $this->candidates($jobPosting, $postingRound);
        $counts = $this->counts($rows);

        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $decision = $validated['decision'] ?? 'all';
        $filtered = $rows
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (array $r) => str_contains(mb_strtolower($r['student']['full_name'].' '.$r['student']['roll_no']), $search)))
            ->when($decision !== 'all', fn (Collection $c) => $c->filter(fn (array $r) => ($r['result'] ?? 'undecided') === $decision || ($decision === 'undecided' && $r['result'] === 'pending')));

        $sorted = $this->sort($filtered, $validated['sort'] ?? 'roll_no', $validated['direction'] ?? 'asc')->values();
        $page = (int) ($validated['page'] ?? 1);
        $lastPage = max(1, (int) ceil($sorted->count() / self::PER_PAGE));

        $rounds = $jobPosting->rounds()->get();
        $index = $rounds->search(fn (PostingRound $r) => $r->id === $postingRound->id);
        $previous = $index > 0 ? $rounds[$index - 1] : null;
        $next = $rounds[$index + 1] ?? null;

        return response()->json([
            'posting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title(),
                'company_name' => $jobPosting->company()?->name,
                'status' => $jobPosting->status,
                'accepts_applications' => $jobPosting->acceptsApplications(),
            ],
            'round' => $postingRound->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'status', 'is_final']) + [
                'position' => $index + 1,
            ],
            'rounds' => $rounds->map(fn (PostingRound $r) => $r->only(['id', 'name', 'sort_order', 'status', 'is_final']))->values(),
            'previous_round' => $previous?->only(['id', 'name']),
            'next_round' => $next?->only(['id', 'name']),
            'next_label' => $postingRound->is_final ? 'FINAL OFFER' : ($next?->name ?? 'FINAL OFFER'),
            'counts' => $counts,
            'candidates' => $sorted->forPage($page, self::PER_PAGE)->values(),
            'meta' => [
                'current_page' => min($page, $lastPage),
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $sorted->count(),
            ],
        ]);
    }

    /**
     * "Download Current Shortlist": the stage's candidates with their current decision and a published/draft marker.
     */
    public function export(Request $request, JobPosting $jobPosting, PostingRound $postingRound): StreamedResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $request->validate(['template' => ['nullable', 'integer']]);
        $template = TemplateExports::find($request->query('template'));
        $rows = $this->sort($this->candidates($jobPosting, $postingRound), 'roll_no', 'asc')->values();
        $next = $jobPosting->rounds()->where('sort_order', '>', $postingRound->sort_order)->first();
        $nextLabel = $postingRound->is_final ? 'FINAL OFFER' : ($next?->name ?? 'FINAL OFFER');

        $this->audit->log($request, 'round.shortlist_export', $postingRound, null, [
            'posting_id' => $jobPosting->id,
            'rows' => $rows->count(),
            'template_id' => $template?->id,
        ]);

        return $template
            ? app(TemplateExports::class)->shortlist($jobPosting, $postingRound, $nextLabel, $template)
            : $this->exports->shortlistWorkbook($jobPosting, $postingRound, $nextLabel, $rows);
    }

    /**
     * The stage's pool (PipelineService::pool) plus any live applicant who already has a row in this stage (an
     * addendum or a result written outside the pool, D70(b)).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function candidates(JobPosting $posting, PostingRound $round): Collection
    {
        $poolIds = $this->pipeline->pool($round)->pluck('id')->all();
        $rowIds = $round->results()->pluck('application_id')->all();
        $ids = array_values(array_unique(array_merge($poolIds, $rowIds)));

        $applications = Application::query()
            ->whereIn('id', $ids)
            ->where('job_posting_id', $posting->id)
            ->where('status', 'applied')
            ->with([
                'studentProfile:id,roll_no,full_name,programme,branch,graduating_batch,current_cgpa,institute_email,phone',
                'roundResults' => fn ($q) => $q->where('posting_round_id', $round->id),
                'offer:id,application_id',
            ])
            ->get();

        $offers = Offer::query()
            ->with(['company:id,name', 'jobPosting.postable'])
            ->whereIn('student_profile_id', $applications->pluck('student_profile_id'))
            ->get()
            ->groupBy('student_profile_id');

        return $applications->map(function (Application $a) use ($poolIds, $offers, $posting): array {
            /** @var ApplicationRoundResult|null $row */
            $row = $a->roundResults->first();

            return [
                'application_id' => $a->id,
                'student' => $a->studentProfile?->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'current_cgpa', 'institute_email', 'phone']),
                'in_pool' => in_array($a->id, $poolIds, true),
                'result' => $row?->result,
                'published' => (bool) $row?->isPublished(),
                'is_addendum' => (bool) $row?->is_addendum,
                'attendance' => $row?->attendance,
                'remark' => $row?->remark,
                'placed_elsewhere_flag' => $a->placed_elsewhere_flag,
                'offer_here' => $a->offer !== null,
                'offers' => $offers->get($a->student_profile_id, collect())
                    ->filter(fn (Offer $o) => $o->job_posting_id !== $posting->id)
                    ->map(fn (Offer $o) => [
                        'role' => $o->jobPosting?->title(),
                        'company' => $o->company?->name,
                        'offer_type' => $o->offer_type,
                    ])->values(),
            ];
        })->values();
    }

    /**
     * "N selected out of M candidates": M is the stage's pool, the same number the Progress Grid shows; rows written
     * outside the pool (addenda, D70(b) warnings) are counted separately as "+K outside pool" (L8).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function counts(Collection $rows): array
    {
        $of = fn (string $result) => $rows->where('result', $result)->count();
        $pool = $rows->where('in_pool', true)->count();

        return [
            'candidates' => $pool,
            'pool' => $pool,
            'outside_pool' => $rows->count() - $pool,
            'selected' => $of('selected'),
            'waitlisted' => $of('waitlisted'),
            'rejected' => $of('rejected'),
            'undecided' => $rows->filter(fn (array $r) => $r['result'] === null || $r['result'] === 'pending')->count(),
            'drafts' => $rows->filter(fn (array $r) => $r['result'] !== null && $r['result'] !== 'pending' && ! $r['published'])->count(),
            'published' => $rows->where('published', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sort(Collection $rows, string $sort, string $direction): Collection
    {
        $order = ['selected' => 0, 'waitlisted' => 1, 'pending' => 2, null => 2, 'rejected' => 3];
        $key = match ($sort) {
            'name' => fn (array $r) => mb_strtolower((string) $r['student']['full_name']),
            'cgpa' => fn (array $r) => (float) ($r['student']['current_cgpa'] ?? 0),
            'decision' => fn (array $r) => ($order[$r['result'] ?? ''] ?? 2).'-'.$r['student']['roll_no'],
            default => fn (array $r) => (string) $r['student']['roll_no'],
        };

        return $direction === 'desc' ? $rows->sortByDesc($key) : $rows->sortBy($key);
    }
}
