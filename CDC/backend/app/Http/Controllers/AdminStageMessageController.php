<?php

namespace App\Http\Controllers;

use App\Mail\BroadcastMail;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Services\AudienceService;
use App\Services\AuditService;
use App\Services\BroadcastService;
use App\Support\UploadType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Send Email to Shortlisted / On Hold" on a stage's shortlist page (Superset parity S7.2): a free-text mail to the
 * stage's PUBLISHED shortlisted and/or on-hold students, in BCC batches, logged in the Communication Log and audited.
 * Draft decisions can never be targeted (that would leak unpublished results).
 */
class AdminStageMessageController extends Controller
{
    public function __construct(
        private readonly AudienceService $audiences,
        private readonly BroadcastService $broadcast,
        private readonly AuditService $audit
    ) {
    }

    /**
     * Published shortlisted / on-hold counts for the dialogs.
     */
    public function audience(JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $count = fn (array $results) => $this->audiences->query([$this->group($postingRound, $results)])->count();

        return response()->json(['selected' => $count(['selected']), 'waitlisted' => $count(['waitlisted'])]);
    }

    public function email(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:20000'],
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['in:'.implode(',', AudienceService::ROUND_RESULTS)],
            // The content must match the extension: a PDF renamed x.png is refused (L24).
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120', UploadType::rule(['pdf', 'jpg', 'jpeg', 'png'], 'Attach a PDF or an image (JPG/PNG).')],
        ], ['attachment.max' => 'The attachment must be at most 5 MB.', 'attachment.mimes' => 'Attach a PDF or an image (JPG/PNG).']);

        abort_if(BroadcastMail::paragraphs($validated['message']) === [], 422, 'Write a message.');

        $results = array_values(array_unique($validated['results']));
        $audience = $this->audiences->query([$this->group($postingRound, $results)]);
        $recipients = (clone $audience)->count();
        abort_if($recipients === 0, 422, 'No student has a published decision of this kind in this stage yet. Publish the shortlist first.');

        $path = null;
        $name = null;
        if ($file = $request->file('attachment')) {
            // Kept on the private disk while queued batches still need it, deleted once the send is done (L23).
            $path = $file->storeAs("stage-emails/{$postingRound->id}", Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf'), 'local');
            $name = Str::limit($file->getClientOriginalName(), 200, '');
        }

        $company = $jobPosting->company()?->name;
        $mailable = new BroadcastMail(
            $validated['subject'],
            trim(($company ? "{$company} · " : '').$jobPosting->title().' · '.$postingRound->name),
            BroadcastMail::paragraphs($validated['message']),
            rtrim((string) config('app.frontend_url'), '/').'/student/applications',
            'Open My Applications',
            $path,
            $name
        );

        $mailed = $this->broadcast->toAudience($audience, $mailable, 'emails.broadcast', [
            'job_posting_id' => $jobPosting->id,
            'kind' => 'stage_email',
        ], deleteAttachmentAfter: true);

        $this->audit->log($request, 'stage.email', $postingRound, null, [
            'job_posting_id' => $jobPosting->id, 'subject' => $validated['subject'], 'results' => $results,
            'recipients' => $mailed, 'attachment' => $name,
        ]);

        return response()->json(['message' => "Email sent to {$mailed} student(s) in BCC batches."]);
    }

    /** @param  list<string>  $results */
    private function group(PostingRound $round, array $results): array
    {
        return ['audience_type' => 'round_results', 'audience_filter' => ['job_posting_id' => $round->job_posting_id, 'posting_round_id' => $round->id, 'results' => $results]];
    }
}
