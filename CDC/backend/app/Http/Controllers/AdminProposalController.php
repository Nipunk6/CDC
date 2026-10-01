<?php

namespace App\Http\Controllers;

use App\Models\ShortlistProposal;
use App\Services\AuditService;
use App\Services\PipelineService;
use App\Services\StakeholderNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Company shortlist/waitlist/addendum proposals (spec M6.5). Approving writes DRAFT results only — the round
 * still has to be published separately.
 */
class AdminProposalController extends Controller
{
    public const PER_PAGE = 50;

    public function __construct(
        private readonly AuditService $audit,
        private readonly PipelineService $pipeline,
        private readonly StakeholderNotifier $stakeholders
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'job_posting_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = ShortlistProposal::query()
            ->with(['postingRound:id,name', 'proposedBy:id,name,email', 'decidedBy:id,name', 'jobPosting.postable.company:id,name'])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['job_posting_id'])) {
            $query->where('job_posting_id', $validated['job_posting_id']);
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'proposals' => collect($page->items())->map(fn (ShortlistProposal $p) => [
                'id' => $p->id,
                'kind' => $p->kind,
                'status' => $p->status,
                'payload' => $p->payload,
                'admin_remark' => $p->admin_remark,
                'created_at' => $p->created_at,
                'decided_at' => $p->decided_at,
                'round' => $p->postingRound,
                'proposed_by' => $p->proposedBy,
                'decided_by' => $p->decidedBy,
                'posting' => [
                    'id' => $p->job_posting_id,
                    'title' => $p->jobPosting?->title(),
                    'company' => $p->jobPosting?->company()?->name,
                ],
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function update(Request $request, ShortlistProposal $shortlistProposal): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remark' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ], [
            'admin_remark.required_if' => 'Add a remark for the company explaining the rejection.',
        ]);

        $posting = $shortlistProposal->jobPosting;
        $round = $shortlistProposal->postingRound;
        $report = ['written' => 0, 'skipped' => []];

        if ($validated['status'] === 'approved') {
            if ($posting->status === 'cancelled') {
                return response()->json(['message' => 'This posting is cancelled.'], 422);
            }
            if ($posting->acceptsApplications()) {
                return response()->json(['message' => 'Applications are still open. Close applications before approving proposals.'], 422);
            }
        }
        $unknown = [];
        $removed = [];

        $decided = DB::transaction(function () use ($shortlistProposal, $validated, $request, $posting, $round, &$report, &$unknown, &$removed): bool {
            $locked = ShortlistProposal::query()->whereKey($shortlistProposal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            if ($validated['status'] === 'approved') {
                $payload = $shortlistProposal->payload ?? [];
                [$found, $unknown] = $this->pipeline->resolveApplicants($posting, array_column($payload, 'roll_no'));
                $kind = $shortlistProposal->kind;

                $entries = [];
                foreach ($payload as $item) {
                    $application = $found[$item['roll_no']] ?? null;
                    if ($application) {
                        $entries[] = [
                            'application' => $application,
                            'result' => $kind === 'waitlist' ? 'waitlisted' : 'selected',
                            'is_addendum' => in_array($kind, ['addendum', 'replacement_request'], true),
                            'remark' => 'From company '.str_replace('_', ' ', $kind),
                        ];
                    }
                }

                $report = $this->pipeline->writeDrafts($round, $entries, $request->user());

                // A company waitlist proposal is the complete desired waitlist (D75, unordered since D90): anyone left off is removed.
                if ($kind === 'waitlist') {
                    $keep = array_map(fn ($e) => $e['application']->id, $entries);
                    $current = $round->results()->where('result', 'waitlisted')->pluck('application_id')->all();
                    foreach (array_diff($current, $keep) as $applicationId) {
                        $row = $this->pipeline->removeFromWaitlist($round, \App\Models\Application::findOrFail($applicationId), $request->user());
                        if ($row) {
                            $removed[] = $row;
                        }
                    }
                }
            }

            $shortlistProposal->update([
                'status' => $validated['status'],
                'admin_remark' => $validated['admin_remark'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            return true;
        });

        if (! $decided) {
            return response()->json(['message' => 'This proposal has already been decided.'], 422);
        }

        $approved = $validated['status'] === 'approved';

        // Students taken off a PUBLISHED waitlist are told, and each removal is audited (QA F-003).
        foreach ($removed as $row) {
            $this->audit->log($request, 'waitlist.remove', $round, ['application_id' => $row->application_id, 'result' => 'waitlisted'], [
                'application_id' => $row->application_id,
                'result' => $row->exists ? 'rejected' : 'removed draft',
                'via' => 'proposal #'.$shortlistProposal->id,
            ]);
        }
        $this->pipeline->dispatchResultMails($round, collect($removed)->filter(fn ($row) => $row->exists && $row->published_at)->pluck('id'), false);

        $this->audit->log($request, 'proposal.'.($approved ? 'approve' : 'reject'), $shortlistProposal, ['status' => 'pending'], [
            'status' => $validated['status'],
            'admin_remark' => $validated['admin_remark'] ?? null,
            'drafts_written' => $report['written'],
        ]);

        $this->stakeholders->notifyCompany(
            $posting,
            'Your '.str_replace('_', ' ', $shortlistProposal->kind).' was '.($approved ? 'accepted' : 'declined'),
            $approved
                ? sprintf('The CDC accepted your %s for %s — %s. Candidates are informed once the CDC publishes the round.', str_replace('_', ' ', $shortlistProposal->kind), $posting->title(), $round->name)
                : sprintf('The CDC declined your %s for %s — %s.', str_replace('_', ' ', $shortlistProposal->kind), $posting->title(), $round->name),
            $approved ? [] : ['CDC remark: '.$validated['admin_remark']],
            $approved ? 'success' : 'warning'
        );

        return response()->json([
            'message' => $approved
                ? "Proposal approved: {$report['written']} draft result(s) written. Publish the round to notify students."
                : 'Proposal rejected and the company has been told.',
            'errors' => array_merge($unknown, $report['skipped']),
        ]);
    }
}
