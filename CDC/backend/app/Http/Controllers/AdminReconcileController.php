<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\PipelineService;
use App\Services\ReconcileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Reconcile Ineligible Students" on a stage (owner decision B2-11, fix H1): the list with reasons, its Excel report,
 * and "Mark Selected Students As Rejected" with the regret mail. Admin only; every action audited.
 */
class AdminReconcileController extends Controller
{
    public function __construct(
        private readonly ReconcileService $reconcile,
        private readonly PipelineService $pipeline,
        private readonly ExportService $exports,
        private readonly AuditService $audit
    ) {
    }

    public function show(JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        $rows = $this->reconcile->ineligible($postingRound);

        return response()->json([
            'round' => $postingRound->only(['id', 'name', 'is_final']),
            'pool_count' => $this->pipeline->pool($postingRound)->count(),
            'students' => $rows->map(fn (array $row) => [
                'application_id' => $row['application']->id,
                'student' => $row['application']->studentProfile->only(['id', 'roll_no', 'full_name', 'programme', 'branch']),
                'reasons' => $row['reasons'],
            ])->values(),
            'blocked_reason' => $this->blocked($jobPosting),
        ]);
    }

    public function export(Request $request, JobPosting $jobPosting, PostingRound $postingRound): StreamedResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        $rows = $this->reconcile->ineligible($postingRound);
        $this->audit->log($request, 'stage.reconcile_report', $postingRound, null, [
            'posting_id' => $jobPosting->id,
            'ineligible' => $rows->count(),
        ]);

        $company = $jobPosting->company()?->name ?? 'Company';

        return $this->exports->tableWorkbook(
            sprintf('%s · %s: students in \'%s\' who are no longer eligible', $company, $jobPosting->title(), $postingRound->name),
            ['S.No.', 'Roll Number', 'Name', 'Programme', 'Branch', 'Reasons'],
            $rows->values()->map(fn (array $row, int $i) => [
                $i + 1,
                $row['application']->studentProfile->roll_no,
                $row['application']->studentProfile->full_name,
                $row['application']->studentProfile->programme,
                $row['application']->studentProfile->branch,
                implode(' ', $row['reasons']),
            ]),
            Str::slug($company.' '.$jobPosting->title().' '.$postingRound->name.' ineligible').'.xlsx',
            'Ineligible'
        );
    }

    /**
     * Mark the chosen students as not selected in this stage (published at once) and send the regret mail.
     */
    public function reject(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        $validated = $request->validate([
            'application_ids' => ['required', 'array', 'min:1', 'max:5000'],
            'application_ids.*' => ['integer'],
            'confirm' => ['required', 'accepted'],
        ], ['confirm.accepted' => 'Confirm that the selected students will be marked as rejected.']);

        if ($reason = $this->blocked($jobPosting)) {
            return response()->json(['message' => $reason], 422);
        }

        $result = $this->reconcile->reject($postingRound, $validated['application_ids'], $request->user());

        if ($result['rows'] === []) {
            return response()->json([
                'message' => 'Nobody was marked as rejected: the selected students are no longer in this stage, are eligible again, or are already decided.',
                'skipped' => $result['skipped'],
            ], 422);
        }

        $this->audit->log($request, 'stage.reconcile', $postingRound, [
            'application_ids' => array_column($result['rejected'], 'application_id'),
            'result' => 'in pool',
        ], [
            'posting_id' => $jobPosting->id,
            'result' => 'rejected',
            'rejected' => $result['rejected'],
            'skipped' => $result['skipped'],
        ]);

        // Regret mail by the written row ids only, so nobody is mailed twice (D92, QA F-008).
        $this->pipeline->dispatchResultMails($postingRound, $result['rows'], false, 'reconcile_regret');

        return response()->json([
            'message' => sprintf('%d student(s) marked as rejected in %s and sent the regret mail.%s', count($result['rows']), $postingRound->name, $result['skipped'] ? ' '.count($result['skipped']).' skipped.' : ''),
            'rejected' => count($result['rows']),
            'skipped' => $result['skipped'],
        ]);
    }

    private function blocked(JobPosting $posting): ?string
    {
        if ($posting->status === 'cancelled') {
            return 'This job profile is cancelled.';
        }
        if ($posting->acceptsApplications()) {
            return 'Applications are still open. Close applications before reconciling a stage.';
        }

        return null;
    }

    private function assertRoundOf(JobPosting $posting, PostingRound $round): void
    {
        abort_if($round->job_posting_id !== $posting->id, 404, 'Stage not found.');
    }
}
