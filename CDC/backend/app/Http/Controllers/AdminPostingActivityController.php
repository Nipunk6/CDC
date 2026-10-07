<?php

namespace App\Http\Controllers;

use App\Mail\PortalNoticeMail;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PostingDocument;
use App\Models\PostingRound;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Job profile extras (Superset parity S6): Attached Documents, Activity, Communication Log and "Send Applicant List".
 */
class AdminPostingActivityController extends Controller
{
    public const MAX_DOCUMENTS = 10;

    private const ACTIVITY_LABELS = [
        'posting.float' => 'Opened for applications',
        'posting.scheduled_open' => 'Opened for applications (scheduled)',
        'posting.open_now' => 'Opened for applications (schedule cancelled)',
        'posting.update' => 'Job profile details changed',
        'posting.eligibility_update' => 'Eligibility edited',
        'posting.close' => 'Closed for applications',
        'posting.reopen' => 'Reopened for applications',
        'posting.cancel' => 'Cancelled',
        'posting.notify' => 'Eligible students emailed',
        'posting.export' => 'Applicants downloaded',
        'posting.eligible_export' => 'Eligible list downloaded',
        'posting.document_add' => 'Document attached',
        'posting.document_remove' => 'Document removed',
        'posting.send_applicant_list' => 'Applicant list sent to the company',
        'round.create' => 'Stage added',
        'round.update' => 'Stage changed',
        'round.delete' => 'Stage removed',
        'round.reorder' => 'Stages reordered',
        'round.results_draft' => 'Draft decisions entered',
        'round.attendance' => 'Attendance marked',
        'round.publish' => 'Stage shortlist published',
        'round.addendum' => 'Addendum added',
        'round.readd' => 'Candidate re-added',
        'round.shortlist_export' => 'Stage shortlist downloaded',
        'stage.reconcile' => 'Ineligible students marked as rejected (Reconcile)',
        'stage.reconcile_report' => 'Reconcile report downloaded',
        'stage.email' => 'Email sent to a stage',
        'waitlist.promote' => 'Moved off On Hold',
        'waitlist.remove' => 'Removed from On Hold',
        'result.publish' => 'Results announced',
        'offer.create' => 'Offer announced',
        'offer.update' => 'Offer edited',
        'offer.revoke' => 'Offer revoked',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    // ---- Attached Documents ------------------------------------------------------------------

    public function documents(JobPosting $jobPosting): JsonResponse
    {
        return response()->json(['documents' => $jobPosting->documents()->with('uploader:id,name')->get()->map(fn (PostingDocument $d) => $this->documentPayload($d))]);
    }

    public function storeDocument(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ], [
            'file.mimes' => 'Attach a PDF file.',
            'file.max' => 'The file must be 5 MB or smaller.',
            'file.uploaded' => 'The file must be 5 MB or smaller.',
        ]);

        if ($jobPosting->documents()->count() >= self::MAX_DOCUMENTS) {
            return response()->json(['message' => 'A job profile can have at most '.self::MAX_DOCUMENTS.' documents.'], 422);
        }

        $path = $request->file('file')->storeAs("posting-documents/{$jobPosting->id}", Str::uuid().'.pdf', 'local');
        $document = $jobPosting->documents()->create([
            'title' => trim($validated['title']),
            'file_path' => $path,
            'file_size' => $request->file('file')->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'posting.document_add', $jobPosting, null, $document->only(['id', 'title', 'file_size']));

        return response()->json(['message' => 'Document attached.', 'document' => $this->documentPayload($document)], 201);
    }

    public function destroyDocument(Request $request, JobPosting $jobPosting, PostingDocument $postingDocument): JsonResponse
    {
        abort_if($postingDocument->job_posting_id !== $jobPosting->id, 404, 'Document not found.');

        $before = $postingDocument->only(['id', 'title', 'file_size']);
        Storage::disk('local')->delete($postingDocument->file_path);
        $postingDocument->delete();
        $this->audit->log($request, 'posting.document_remove', $jobPosting, $before, null);

        return response()->json(['message' => 'Document removed.']);
    }

    public function downloadDocument(JobPosting $jobPosting, PostingDocument $postingDocument): StreamedResponse
    {
        abort_if($postingDocument->job_posting_id !== $jobPosting->id, 404, 'Document not found.');

        return $postingDocument->stream();
    }

    // ---- Activity ------------------------------------------------------------------------------

    /**
     * The job profile's timeline from the audit log: the posting itself, its stages and its offers.
     */
    public function activity(JobPosting $jobPosting): JsonResponse
    {
        $roundIds = $jobPosting->rounds()->pluck('id');
        $offerIds = Offer::query()->where('job_posting_id', $jobPosting->id)->pluck('id');

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where(function (Builder $q) use ($jobPosting, $roundIds, $offerIds): void {
                $q->where(fn (Builder $s) => $s->where('subject_type', JobPosting::class)->where('subject_id', $jobPosting->id))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', PostingRound::class)->whereIn('subject_id', $roundIds))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', Offer::class)->whereIn('subject_id', $offerIds));
            })
            ->latest('id')
            ->limit(300)
            ->get();

        $roundNames = $jobPosting->rounds()->pluck('name', 'id');

        return response()->json([
            'activity' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => self::ACTIVITY_LABELS[$log->action] ?? Str::headline(str_replace('.', ' ', $log->action)),
                'stage' => $log->subject_type === PostingRound::class ? ($roundNames[$log->subject_id] ?? null) : null,
                'by' => $log->user?->name ?? $log->actor_name ?? 'System',
                'at' => $log->created_at,
                'before' => $log->before,
                'after' => $log->after,
            ])->values(),
        ]);
    }

    // ---- Communication Log ---------------------------------------------------------------------

    /**
     * Every mail sent for this job profile, one row per message (a BCC broadcast's batches and the per-student log
     * rows are folded together by kind, subject and minute).
     */
    public function communications(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $rows = EmailLog::query()
            ->where('job_posting_id', $jobPosting->id)
            ->when(filled($validated['search'] ?? null), fn ($q) => \App\Support\Like::whereContains($q, ['subject'], (string) $validated['search'])) // fix L28
            ->orderByDesc('created_at')
            ->get(['id', 'kind', 'subject', 'status', 'recipient_email', 'created_at']);

        $groups = $rows->groupBy(fn (EmailLog $l) => ($l->kind ?? '').'|'.$l->subject.'|'.$l->created_at?->format('Y-m-d H:i'))
            ->map(fn ($group) => [
                'at' => $group->first()->created_at,
                'kind' => $group->first()->kind,
                'subject' => $group->first()->subject,
                'recipients' => $group->count(),
                'sent' => $group->where('status', 'sent')->count(),
                'queued' => $group->where('status', 'queued')->count(),
                'failed' => $group->where('status', 'failed')->count(),
                'status' => $group->contains('status', 'failed') ? 'failed' : ($group->contains('status', 'queued') ? 'queued' : 'sent'),
            ])
            ->values();

        $perPage = 25;
        $page = (int) ($validated['page'] ?? 1);
        $lastPage = max(1, (int) ceil($groups->count() / $perPage));

        return response()->json([
            'messages' => $groups->forPage($page, $perPage)->values(),
            'meta' => ['current_page' => min($page, $lastPage), 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $groups->count()],
        ]);
    }

    // ---- Send Applicant List -------------------------------------------------------------------

    /**
     * Email the company a signed link (7 days) to the company-safe applicant export (S6.11). Not a gate: the company
     * can still download from its own portal at any time.
     */
    public function sendApplicantList(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $company = $jobPosting->company();
        $users = $company ? User::query()->where('role', 'company')->where('company_id', $company->id)->where('is_active', true)->get() : collect();
        if ($users->isEmpty()) {
            return response()->json(['message' => 'This company has no active portal users to send the list to.'], 422);
        }

        $url = URL::temporarySignedRoute('company-exports.applicants', now()->addDays(7), ['jobPosting' => $jobPosting->id]);
        $live = $jobPosting->applications()->where('status', 'applied')->count();
        $subject = 'Applicant list: '.$jobPosting->title();
        $lines = array_values(array_filter([
            "The CDC has shared the list of {$live} applicant(s) for {$jobPosting->title()}.",
            filled($validated['note'] ?? null) ? 'Note from the CDC: '.trim($validated['note']) : null,
            'The download link works for 7 days. You can also download the latest list any time from the Company Portal.',
        ]));

        foreach ($users as $user) {
            $this->notifications->createInAppNotification($user, $subject, $lines[0], 'info');
            $this->mail->send($user, new PortalNoticeMail($subject, "Hello {$user->name},", $lines[0], $lines, $url, 'Download Applicant List'), $subject, 'emails.portal-notice', [
                'job_posting_id' => $jobPosting->id,
                'kind' => 'applicant_list',
            ]);
        }

        $this->audit->log($request, 'posting.send_applicant_list', $jobPosting, null, [
            'recipients' => $users->pluck('email')->all(),
            'applicants' => $live,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['message' => sprintf('Applicant list sent to %d company user(s).', $users->count())]);
    }

    private function documentPayload(PostingDocument $document): array
    {
        return $document->only(['id', 'title', 'file_size', 'created_at']) + ['uploaded_by' => $document->uploader?->name];
    }
}
