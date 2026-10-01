<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Services\AuditService;
use App\Services\PipelineService;
use App\Services\SpreadsheetImportService;
use App\Services\StakeholderNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The per-posting selection pipeline (spec M6.2/M6.3): rounds × applicants, draft results, publishing.
 */
class AdminPipelineController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PipelineService $pipeline,
        private readonly SpreadsheetImportService $spreadsheets,
        private readonly StakeholderNotifier $stakeholders
    ) {
    }

    /**
     * Every live application with its per-round attendance/result/flags (the req-20 grid).
     */
    public function show(JobPosting $jobPosting): JsonResponse
    {
        $rounds = $jobPosting->rounds()->get();

        $applications = $jobPosting->applications()
            ->where('status', 'applied')
            ->with([
                'studentProfile:id,roll_no,full_name,programme,branch,graduating_batch,current_cgpa',
                'resume:id,label,status',
                'roundResults',
            ])
            ->orderBy('applied_at')
            ->get();

        return response()->json([
            'rounds' => $rounds->map(fn (PostingRound $round) => $round->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'status', 'is_final']) + [
                'draft_count' => $round->results()->whereNull('published_at')->where('result', '!=', 'pending')->count(),
                'published_count' => $round->results()->whereNotNull('published_at')->count(),
                'pool_count' => $this->pipeline->pool($round)->count(),
            ]),
            'applications' => $applications->map(fn (Application $a) => [
                'id' => $a->id,
                'student' => $a->studentProfile,
                'resume' => $a->resume,
                'resume_url' => $a->resume?->previewUrl(),
                'used_unverified_resume' => $a->used_unverified_resume,
                'placed_elsewhere_flag' => $a->placed_elsewhere_flag,
                'results' => $a->roundResults->mapWithKeys(fn (ApplicationRoundResult $r) => [
                    $r->posting_round_id => [
                        'attendance' => $r->attendance,
                        'result' => $r->result,
                        'is_addendum' => $r->is_addendum,
                        'published' => $r->isPublished(),
                        'published_at' => $r->published_at,
                        'remark' => $r->remark,
                    ],
                ]),
            ]),
            'accepts_applications' => $jobPosting->acceptsApplications(),
        ]);
    }

    /**
     * Draft results for a round from JSON entries, a pasted roll list, or an uploaded sheet
     * (roll_no, result). Unknown / withdrawn roll numbers are reported, never dropped.
     */
    public function results(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        if ($blocked = $this->blocked($jobPosting)) {
            return $blocked;
        }

        $request->validate([
            'entries' => ['sometimes', 'array'],
            'entries.*.roll_no' => ['required', 'string', 'max:30'],
            'entries.*.result' => ['required', 'in:pending,selected,rejected,waitlisted'],
            'roll_nos' => ['sometimes', 'array'],
            'roll_nos.*' => ['nullable', 'string', 'max:30'],
            'result' => ['required_with:roll_nos', 'in:selected,rejected,waitlisted'],
            'file' => array_merge(['sometimes'], SpreadsheetImportService::UPLOAD_RULES),
        ]);

        try {
            $entries = $this->collectEntries($request);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($entries === []) {
            return response()->json(['message' => 'No roll numbers were found in the input.'], 422);
        }

        [$found, $unknown] = $this->pipeline->resolveApplicants($jobPosting, array_column($entries, 'roll_no'));

        $pool = $this->pipeline->pool($postingRound)->pluck('id')->all();
        $previous = $jobPosting->rounds()->where('sort_order', '<', $postingRound->sort_order)->get()->last();
        $waitlisted = $previous ? $previous->results()->where('result', 'waitlisted')->whereNotNull('published_at')->pluck('application_id')->all() : [];
        $warnings = [];
        $drafts = [];
        foreach ($entries as $entry) {
            $application = $found[$entry['roll_no']] ?? null;
            if (! $application) {
                continue;
            }
            if (! in_array($application->id, $pool, true)) {
                $warnings[] = ['roll_no' => $entry['roll_no'], 'reason' => in_array($application->id, $waitlisted, true)
                    ? 'Is on the previous round\'s waitlist — use "Move" on the Waitlist tab first.'
                    : 'Was not selected in the previous round.'];
            }
            $drafts[] = ['application' => $application] + $entry;
        }

        $report = $this->pipeline->writeDrafts($postingRound, $drafts, $request->user());

        if ($report['written'] > 0) {
            $this->audit->log($request, 'round.results_draft', $postingRound, null, [
                'posting_id' => $jobPosting->id,
                'written' => $report['written'],
                'entries' => array_slice(array_map(fn ($e) => $e['roll_no'].':'.$e['result'], $entries), 0, 100),
            ]);
        }

        return response()->json([
            'message' => sprintf('%d draft result(s) saved. %d not applied. Nothing is visible to students until you publish.', $report['written'], count($unknown) + count($report['skipped'])),
            'written' => $report['written'],
            'errors' => array_merge($unknown, $report['skipped']),
            'warnings' => $warnings,
        ]);
    }

    /**
     * Admin-marked attendance (spec Q4.6).
     */
    public function attendance(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        if ($blocked = $this->blocked($jobPosting)) {
            return $blocked;
        }

        $validated = $request->validate([
            'roll_nos_present' => ['array'],
            'roll_nos_present.*' => ['string', 'max:30'],
            'roll_nos_absent' => ['array'],
            'roll_nos_absent.*' => ['string', 'max:30'],
        ]);

        $present = $validated['roll_nos_present'] ?? [];
        $absent = $validated['roll_nos_absent'] ?? [];
        [$found, $unknown] = $this->pipeline->resolveApplicants($jobPosting, array_merge($present, $absent));
        $presentSet = array_flip(array_map(fn ($r) => strtoupper(trim($r)), $present));

        $marked = 0;
        foreach ($found as $roll => $application) {
            $row = ApplicationRoundResult::query()->firstOrNew([
                'application_id' => $application->id,
                'posting_round_id' => $postingRound->id,
            ]);
            $row->attendance = isset($presentSet[$roll]) ? 'yes' : 'no';
            $row->result ??= 'pending';
            $row->save();
            $marked++;
        }

        $this->audit->log($request, 'round.attendance', $postingRound, null, [
            'present' => count(array_intersect_key($found, $presentSet)),
            'absent' => $marked - count(array_intersect_key($found, $presentSet)),
        ]);

        return response()->json([
            'message' => "Attendance saved for {$marked} student(s).",
            'errors' => $unknown,
        ]);
    }

    public function publish(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        if ($blocked = $this->blocked($jobPosting)) {
            return $blocked;
        }

        $validated = $request->validate([
            'reject_remaining' => ['nullable', 'boolean'],
        ]);

        if ($postingRound->is_final) {
            return response()->json([
                'message' => 'The final round is published from the Results page, where offers are created.',
            ], 422);
        }

        // Rounds are published in order (D75).
        $earlier = $jobPosting->rounds()->where('sort_order', '<', $postingRound->sort_order)->where('status', '!=', 'completed')->first();
        if ($earlier) {
            return response()->json(['message' => "Publish \"{$earlier->name}\" before this round."], 422);
        }

        $rejectRemaining = (bool) ($validated['reject_remaining'] ?? false);
        $preview = $this->pipeline->publishPreview($postingRound);

        if ($preview['drafts'] === 0 && (! $rejectRemaining || $preview['to_reject'] === 0)) {
            return response()->json(['message' => 'There is nothing new to publish for this round.'], 422);
        }

        $counts = $this->pipeline->publish($postingRound, $request->user(), $rejectRemaining);
        $rowIds = $counts['row_ids'];
        unset($counts['row_ids']);

        $this->audit->log($request, 'round.publish', $postingRound, null, $counts + [
            'posting_id' => $jobPosting->id,
            'reject_remaining' => $rejectRemaining,
        ]);

        $this->pipeline->dispatchResultMails($postingRound, $rowIds);

        return response()->json([
            'message' => sprintf(
                'Published: %d selected, %d waitlisted, %d not selected. Students have been notified.',
                $counts['selected'],
                $counts['waitlisted'],
                $counts['rejected']
            ),
            'counts' => $counts,
        ]);
    }

    /**
     * Adding candidates after a round's results were published (spec Q4.5): draft `selected` rows flagged
     * `is_addendum`, published with the normal Publish button. Previously rejected students are refused here.
     */
    public function addendum(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        if ($blocked = $this->blocked($jobPosting)) {
            return $blocked;
        }

        $validated = $request->validate([
            'roll_nos' => ['required', 'array', 'min:1'],
            'roll_nos.*' => ['required', 'string', 'max:30'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        [$found, $unknown] = $this->pipeline->resolveApplicants($jobPosting, $validated['roll_nos']);
        $report = $this->pipeline->writeDrafts($postingRound, array_map(fn (Application $a) => [
            'application' => $a,
            'result' => 'selected',
            'is_addendum' => true,
            'remark' => $validated['remark'] ?? 'Addendum',
        ], array_values($found)), $request->user());

        if ($report['written'] > 0) {
            $this->audit->log($request, 'round.addendum', $postingRound, null, [
                'posting_id' => $jobPosting->id,
                'roll_nos' => array_keys($found),
                'remark' => $validated['remark'] ?? null,
            ]);
        }

        return response()->json([
            'message' => "{$report['written']} addendum candidate(s) added as drafts. Publish the round to notify them.",
            'errors' => array_merge($unknown, $report['skipped']),
        ]);
    }

    /**
     * Admin-only protocol to bring back a candidate already rejected (published) in this round.
     * The company is told automatically (spec Q4.5).
     */
    public function readd(Request $request, JobPosting $jobPosting, PostingRound $postingRound, Application $application): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        abort_if($application->job_posting_id !== $jobPosting->id, 404, 'Application not found.');

        $validated = $request->validate([
            'confirm' => ['required', 'accepted'],
            'remark' => ['required', 'string', 'max:255'],
        ], [
            'confirm.accepted' => 'Confirm that the company will be notified.',
            'remark.required' => 'Give a reason for re-adding this candidate.',
        ]);

        $row = $postingRound->results()->where('application_id', $application->id)->first();

        if (! $row || $row->result !== 'rejected' || ! $row->isPublished()) {
            return response()->json(['message' => 'Only a candidate whose rejection in this round was published can be re-added.'], 422);
        }

        $before = $row->only(['result', 'is_addendum', 'published_at', 'remark']);
        $row->update([
            'result' => 'selected',
            'is_addendum' => true,
            'published_at' => null,
            'decided_by' => $request->user()->id,
            'remark' => $validated['remark'],
        ]);

        $student = $application->studentProfile;
        $this->audit->log($request, 'round.readd', $row, $before, $row->only(['result', 'is_addendum', 'published_at', 'remark']) + [
            'roll_no' => $student->roll_no,
        ]);

        $this->stakeholders->notifyCompany(
            $jobPosting,
            'Candidate re-added to your process',
            sprintf('The CDC has re-added %s (%s) to %s for %s after they were earlier marked not selected.', $student->full_name, $student->roll_no, $postingRound->name, $jobPosting->title()),
            ['Reason: '.$validated['remark']],
            'warning'
        );

        return response()->json(['message' => 'Candidate re-added as a draft and the company has been notified. Publish the round to inform the student.']);
    }

    /**
     * Take someone off a round's waitlist (spec Q4.4). A published waitlist entry becomes a published "not selected"
     * (with the regret mail); a draft entry is simply deleted.
     */
    public function removeFromWaitlist(Request $request, JobPosting $jobPosting, PostingRound $postingRound, Application $application): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        abort_if($application->job_posting_id !== $jobPosting->id, 404, 'Application not found.');

        $row = $this->pipeline->removeFromWaitlist($postingRound, $application, $request->user());
        if (! $row) {
            return response()->json(['message' => 'This candidate is not on the waitlist for this round.'], 422);
        }

        $this->audit->log($request, 'waitlist.remove', $postingRound, ['application_id' => $application->id, 'result' => 'waitlisted'], [
            'application_id' => $application->id,
            'result' => $row->exists ? 'rejected' : 'removed draft',
        ]);

        if ($row->exists && $row->published_at) {
            $this->pipeline->dispatchResultMails($postingRound, [$row->id], false);
        }

        return response()->json(['message' => 'Removed from the waitlist.']);
    }

    /**
     * "Move to next round" (D90, QA F-002): promote a PUBLISHED waitlisted candidate to "selected" in this round, mail
     * them (with the next round's name) and let the next round decide them like everyone else.
     */
    public function promoteFromWaitlist(Request $request, JobPosting $jobPosting, PostingRound $postingRound, Application $application): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);
        abort_if($application->job_posting_id !== $jobPosting->id, 404, 'Application not found.');
        if ($blocked = $this->blocked($jobPosting)) {
            return $blocked;
        }

        $row = $this->pipeline->promoteFromWaitlist($postingRound, $application, $request->user());
        if (! $row) {
            return response()->json(['message' => 'Only a candidate on this round\'s PUBLISHED waitlist can be moved on. (A draft waitlist entry can simply be changed to "selected".)'], 422);
        }

        $this->audit->log($request, 'waitlist.promote', $postingRound, ['application_id' => $application->id, 'result' => 'waitlisted'], [
            'application_id' => $application->id,
            'result' => 'selected',
            'remark' => 'Promoted from waitlist',
        ]);
        $this->pipeline->dispatchResultMails($postingRound, [$row->id]);

        $next = $jobPosting->rounds()->where('sort_order', '>', $postingRound->sort_order)->first();

        return response()->json([
            'message' => $application->studentProfile->roll_no.' moved off the waitlist'.($next ? " and into {$next->name}" : '').'. The student has been notified.',
        ]);
    }

    /**
     * Remove a DRAFT row. Published rows are never deleted.
     */
    public function destroyDraft(Request $request, JobPosting $jobPosting, PostingRound $postingRound, Application $application): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        $row = $postingRound->results()->where('application_id', $application->id)->first();

        if (! $row || $row->isPublished()) {
            return response()->json(['message' => 'Only draft results can be removed.'], 422);
        }

        $before = $row->only(['result', 'is_addendum', 'remark', 'attendance']);
        $row->delete();
        $this->audit->log($request, 'round.draft_remove', $postingRound, $before + ['application_id' => $application->id], null);

        return response()->json(['message' => 'Draft result removed.']);
    }

    /**
     * Placed-elsewhere protocol (spec B3 / Q3.7): end this application's process with "Selected elsewhere via CDC",
     * and by default tell the company, inviting replacement candidates.
     */
    public function removeFromProcess(Request $request, JobPosting $jobPosting, Application $application): JsonResponse
    {
        abort_if($application->job_posting_id !== $jobPosting->id, 404, 'Application not found.');

        $validated = $request->validate([
            'notify_company' => ['nullable', 'boolean'],
        ]);

        if (! $application->placed_elsewhere_flag) {
            return response()->json(['message' => 'Only applications flagged as placed elsewhere can be removed this way.'], 422);
        }
        if ($application->status !== 'applied') {
            return response()->json(['message' => 'This application is not live.'], 422);
        }

        if ($application->roundResults()->whereNotNull('published_at')->where('result', 'rejected')->exists()) {
            return response()->json(['message' => 'This application is already out of the process.'], 422);
        }

        // A published waitlist entry is where they currently stand: end it there (D84).
        $waitlistRow = $application->roundResults()->whereNotNull('published_at')->where('result', 'waitlisted')->first();

        $published = $application->roundResults()->whereNotNull('published_at')->pluck('posting_round_id')->all();
        $round = $waitlistRow
            ? $waitlistRow->postingRound
            : $jobPosting->rounds()->get()->first(fn (PostingRound $r) => ! in_array($r->id, $published, true));

        if (! $round) {
            return response()->json(['message' => 'Every round of this application is already decided.'], 422);
        }

        $existing = $application->roundResults()->where('posting_round_id', $round->id)->first();

        $before = $existing?->only(['result', 'published_at', 'remark']);
        ApplicationRoundResult::query()->updateOrCreate(
            ['application_id' => $application->id, 'posting_round_id' => $round->id],
            ['result' => 'rejected', 'published_at' => now(), 'decided_by' => $request->user()->id, 'remark' => 'Selected elsewhere via CDC']
        );

        $student = $application->studentProfile;
        $this->audit->log($request, 'application.remove_placed_elsewhere', $application, $before, [
            'round' => $round->name,
            'result' => 'rejected',
            'remark' => 'Selected elsewhere via CDC',
            'notify_company' => (bool) ($validated['notify_company'] ?? true),
        ]);

        if ($validated['notify_company'] ?? true) {
            $this->stakeholders->notifyCompany(
                $jobPosting,
                'Candidate withdrawn from your process',
                sprintf('%s (%s) has been selected elsewhere through the CDC and is no longer part of your process for %s.', $student->full_name, $student->roll_no, $jobPosting->title()),
                ['If you need replacement candidates, open the drive in the Company Portal and send a "Replacement request" proposal for the current round.'],
                'warning'
            );
        }

        return response()->json(['message' => 'Removed from the process'.(($validated['notify_company'] ?? true) ? ' and the company has been notified.' : '.')]);
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * @return list<array{roll_no: string, result: string}>
     */
    private function collectEntries(Request $request): array
    {
        if ($request->hasFile('file')) {
            $entries = [];
            foreach ($this->spreadsheets->rows($request->file('file')) as $cells) {
                $roll = strtoupper(trim($cells[0] ?? ''));
                if ($roll === '' || preg_replace('/[^A-Z]/', '', $roll) === 'ROLLNO') {
                    continue;
                }
                $result = strtolower(trim($cells[1] ?? '')) ?: (string) $request->input('result', 'selected');
                if (! in_array($result, ['selected', 'rejected', 'waitlisted', 'pending'], true)) {
                    throw new \RuntimeException("Row for {$roll}: result must be selected, rejected, waitlisted or pending.");
                }
                $entries[] = ['roll_no' => $roll, 'result' => $result];
            }

            return $entries;
        }

        if ($request->has('roll_nos')) {
            $result = (string) $request->input('result');
            $entries = [];
            foreach ($request->input('roll_nos', []) as $roll) {
                $roll = strtoupper(trim((string) $roll));
                if ($roll !== '') {
                    $entries[] = ['roll_no' => $roll, 'result' => $result];
                }
            }

            return $entries;
        }

        return array_map(fn ($e) => [
            'roll_no' => strtoupper(trim($e['roll_no'])),
            'result' => $e['result'],
        ], $request->input('entries', []));
    }

    private function blocked(JobPosting $posting): ?JsonResponse
    {
        if ($posting->status === 'cancelled') {
            return response()->json(['message' => 'This posting is cancelled.'], 422);
        }

        if ($posting->acceptsApplications()) {
            return response()->json(['message' => 'Applications are still open. Close applications (or wait for the deadline) before entering results.'], 422);
        }

        return null;
    }

    private function assertRoundOf(JobPosting $posting, PostingRound $round): void
    {
        abort_if($round->job_posting_id !== $posting->id, 404, 'Round not found.');
    }
}
