<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Models\ShortlistProposal;
use App\Services\PipelineService;
use App\Services\StakeholderNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company view of its OWN floated forms (spec M6.4 / Q10.x). Only PUBLISHED round outcomes are ever shown,
 * and admin-only flags (unverified resume, placed elsewhere) never leave the admin API.
 */
class CompanyPipelineController extends Controller
{
    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly StakeholderNotifier $stakeholders
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $postings = $this->ownPostings($request)
            ->with(['postable', 'placementCycle:id,name,type,status', 'rounds'])
            ->withCount(['applications as applicant_count' => fn ($q) => $q->where('status', 'applied')])
            ->orderByDesc('floated_at')
            ->get();

        return response()->json([
            'postings' => $postings->map(fn (JobPosting $p) => $this->summary($p)),
        ]);
    }

    public function show(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        $jobPosting->load(['postable', 'placementCycle:id,name,type,status', 'rounds']);
        $jobPosting->loadCount(['applications as applicant_count' => fn ($q) => $q->where('status', 'applied')]);

        return response()->json([
            'posting' => $this->summary($jobPosting) + [
                'rounds' => $jobPosting->rounds->map(fn (PostingRound $r) => $r->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'status', 'is_final']) + [
                    'published_selected' => $r->results()->whereNotNull('published_at')->where('result', 'selected')->count(),
                    'published_waitlisted' => $r->results()->whereNotNull('published_at')->where('result', 'waitlisted')->count(),
                ]),
                'questions' => $jobPosting->questions()->get(['id', 'question', 'qtype', 'options', 'required']),
            ],
        ]);
    }

    /**
     * Applicants with the Q10.2 field set; contact details only when the admin enabled sharing.
     */
    public function applicants(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);

        $share = $jobPosting->share_contact_details;
        $applications = $jobPosting->applications()
            ->where('status', 'applied')
            ->with(['studentProfile', 'resume', 'roundResults' => fn ($q) => $q->whereNotNull('published_at')])
            ->orderBy('applied_at')
            ->get();

        return response()->json([
            'share_contact_details' => $share,
            'applicants' => $applications->map(function (Application $a) use ($share) {
                $s = $a->studentProfile;

                return [
                    'application_id' => $a->id,
                    'roll_no' => $s->roll_no,
                    'full_name' => $s->full_name,
                    'programme' => $s->programme,
                    'branch' => $s->branch,
                    'graduating_batch' => $s->graduating_batch,
                    'current_cgpa' => $s->current_cgpa,
                    'ongoing_backlogs' => $s->ongoing_backlogs,
                    'total_backlogs' => $s->total_backlogs,
                    'tenth_percent' => $s->tenth_percent,
                    'twelfth_percent' => $s->twelfth_percent,
                    'answers' => $a->answers ?? [],
                    'resume_url' => $a->resume?->signedUrl(),
                    'rounds' => $a->roundResults->mapWithKeys(fn (ApplicationRoundResult $r) => [
                        $r->posting_round_id => ['result' => $r->result, 'attendance' => $r->attendance],
                    ]),
                ] + ($share ? [
                    'phone' => $s->phone,
                    'personal_email' => $s->personal_email,
                    'institute_email' => $s->institute_email,
                ] : []);
            }),
        ]);
    }

    /**
     * Same export as admins, restricted to the Q10.2 fields (contact only when shared), any time (spec Q8.2).
     */
    public function export(Request $request, JobPosting $jobPosting, \App\Services\ExportService $exports): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePosting($request, $jobPosting);

        return $exports->applicantsWorkbook($jobPosting, 'company');
    }

    public function proposals(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);

        return response()->json([
            'proposals' => $jobPosting->proposals()
                ->with('postingRound:id,name')
                ->latest('id')
                ->get(['id', 'job_posting_id', 'posting_round_id', 'kind', 'payload', 'status', 'admin_remark', 'decided_at', 'created_at']),
        ]);
    }

    /**
     * Propose a shortlist / waitlist / addendum / replacement for a round. Lands as `pending` for the CDC.
     */
    public function storeProposal(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Round not found.');

        $validated = $request->validate([
            'kind' => ['required', 'in:shortlist,waitlist,addendum,replacement_request'],
            'entries' => ['required', 'array', 'min:1', 'max:3000'],
            'entries.*.roll_no' => ['required', 'string', 'max:30'],
        ]);

        // A completed drive still takes replacement requests and addenda (E9 invites them; QA F-010).
        if ($jobPosting->status === 'cancelled'
            || ($jobPosting->status === 'completed' && ! in_array($validated['kind'], ['addendum', 'replacement_request'], true))) {
            return response()->json(['message' => 'This drive is '.$jobPosting->status.'.'.($jobPosting->status === 'completed' ? ' You can still send a replacement request or an addendum.' : '')], 422);
        }
        if ($jobPosting->acceptsApplications()) {
            return response()->json(['message' => 'Applications are still open. You can propose candidates once the application window closes.'], 422);
        }

        // One entry per roll number (case-insensitive).
        $unique = [];
        foreach ($validated['entries'] as $entry) {
            $unique[strtoupper(trim($entry['roll_no']))] ??= $entry;
        }
        $validated['entries'] = array_values($unique);

        [$found, $unknown] = $this->pipeline->resolveApplicants($jobPosting, array_column($validated['entries'], 'roll_no'), forCompany: true);

        if ($unknown !== []) {
            return response()->json([
                'message' => 'Some roll numbers are not applicants of this posting. Remove them and try again.',
                'errors' => $unknown,
            ], 422);
        }

        $payload = array_values(array_map(fn ($e) => ['roll_no' => strtoupper(trim($e['roll_no']))], $validated['entries']));

        $proposal = ShortlistProposal::create([
            'job_posting_id' => $jobPosting->id,
            'posting_round_id' => $postingRound->id,
            'proposed_by' => $request->user()->id,
            'kind' => $validated['kind'],
            'payload' => $payload,
            'status' => 'pending',
        ]);

        $company = $jobPosting->company()?->name ?? 'A company';
        $this->stakeholders->notifyAdmins(
            "{$company} proposed a {$this->kindLabel($validated['kind'])}",
            sprintf('%s submitted a %s of %d candidate(s) for %s — %s.', $company, $this->kindLabel($validated['kind']), count($payload), $jobPosting->title(), $postingRound->name),
            [],
            "/admin/postings/{$jobPosting->id}"
        );

        return response()->json([
            'message' => 'Proposal sent to the CDC. Results become visible to students only after the CDC publishes them.',
            'proposal' => $proposal,
        ], 201);
    }

    private function kindLabel(string $kind): string
    {
        return ['shortlist' => 'shortlist', 'waitlist' => 'waitlist', 'addendum' => 'addendum', 'replacement_request' => 'replacement request'][$kind] ?? $kind;
    }

    private function ownPostings(Request $request): Builder
    {
        $companyId = $request->user()->company_id;

        return JobPosting::query()
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($companyId): void {
                $q->where(fn (Builder $j) => $j->where('postable_type', Jnf::class)->whereIn('postable_id', Jnf::query()->where('company_id', $companyId)->select('id')))
                    ->orWhere(fn (Builder $i) => $i->where('postable_type', Inf::class)->whereIn('postable_id', Inf::query()->where('company_id', $companyId)->select('id')));
            });
    }

    private function authorizePosting(Request $request, JobPosting $posting): void
    {
        // 404, not 403, so other companies' posting ids cannot be probed (Phase 1 pattern).
        abort_unless($this->ownPostings($request)->whereKey($posting->id)->exists(), 404, 'Posting not found.');
    }

    private function summary(JobPosting $posting): array
    {
        return [
            'id' => $posting->id,
            'form_type' => $posting->formType(),
            'form_id' => $posting->postable_id,
            'type' => $posting->postingType(),
            'title' => $posting->title(),
            'placement_cycle' => $posting->placementCycle ? $posting->placementCycle->only(['id', 'name', 'type']) : null,
            'status' => $posting->status,
            'application_deadline' => $posting->application_deadline,
            'deadline_passed' => $posting->deadlinePassed(),
            'share_contact_details' => $posting->share_contact_details,
            'applicant_count' => (int) ($posting->applicant_count ?? 0),
            'rounds_count' => $posting->relationLoaded('rounds') ? $posting->rounds->count() : null,
        ];
    }
}
