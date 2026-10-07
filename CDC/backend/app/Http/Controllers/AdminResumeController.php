<?php

namespace App\Http\Controllers;

use App\Mail\ResumeReviewedMail;
use App\Models\Resume;
use App\Services\AuditService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminResumeController extends Controller
{
    public const PER_PAGE = 50;

    public function __construct(
        private readonly AuditService $audit,
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Resume::query()
            ->with([
                'studentProfile:id,roll_no,full_name,programme,branch,graduating_batch',
                'reviewedBy:id,name',
            ])
            ->orderBy('updated_at')
            ->orderBy('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas('studentProfile', function (Builder $s) use ($search): void {
                $s->where('roll_no', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'resumes' => collect($page->items())->map(fn (Resume $r) => $r->toArray() + [
                'preview_url' => $r->previewUrl(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'counts' => Resume::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function file(Resume $resume): StreamedResponse|JsonResponse
    {
        if (! Storage::disk('local')->exists($resume->file_path)) {
            return response()->json(['message' => 'The file is missing.'], 404);
        }

        return Storage::disk('local')->response($resume->file_path, $resume->downloadName(), [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }

    public function update(Request $request, Resume $resume): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remark' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            // The version the admin actually previewed; a newer upload must be reviewed again (D60).
            'expected_updated_at' => ['nullable', 'date'],
        ], [
            'admin_remark.required_if' => 'Add a remark telling the student what to fix.',
        ]);

        if (! $this->decide($request, $resume, $validated)) {
            return response()->json([
                'message' => 'The student replaced this resume after you opened it. Review the new file before deciding.',
            ], 409);
        }

        $approved = $validated['status'] === 'approved';

        return response()->json([
            'message' => $approved ? 'Resume verified.' : 'Resume rejected.',
            'resume' => $resume->fresh()->load(['studentProfile:id,roll_no,full_name,programme,branch,graduating_batch', 'reviewedBy:id,name']),
        ]);
    }

    /**
     * The one write path for a resume decision (also used by "Mark all as verified" on the student page, S4.5):
     * the D60 race guard, the B3 flag clearing, the audit row, the in-app notice and the E-mail.
     * Returns false (and changes nothing) when the file changed after `expected_updated_at`.
     *
     * @param  array{status: string, admin_remark?: ?string, expected_updated_at?: ?string}  $validated
     */
    public function decide(Request $request, Resume $resume, array $validated): bool
    {
        $before = $resume->only(['status', 'admin_remark']);

        $stale = DB::transaction(function () use ($resume, $validated, $request): bool {
            $locked = Resume::query()->whereKey($resume->id)->lockForUpdate()->first();

            if (! empty($validated['expected_updated_at'])
                && $locked->updated_at?->toIso8601String() !== \Carbon\Carbon::parse($validated['expected_updated_at'])->toIso8601String()) {
                return true;
            }

            $resume->update([
                'status' => $validated['status'],
                'admin_remark' => $validated['admin_remark'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // B3: approving a resume clears the unverified flag on every application that uses it.
            if ($validated['status'] === 'approved') {
                $resume->applications()->update(['used_unverified_resume' => false]);
            }

            return false;
        });

        if ($stale) {
            return false;
        }

        $this->audit->log($request, 'resume.'.($validated['status'] === 'approved' ? 'approve' : 'reject'), $resume, $before, $resume->only(['status', 'admin_remark']));

        $student = $resume->studentProfile;
        $approved = $validated['status'] === 'approved';

        $this->notifications->createInAppNotification(
            $student->user,
            $approved ? 'Resume verified' : 'Resume needs changes',
            $approved
                ? sprintf('Your resume "%s" has been verified by the CDC.', $resume->label)
                : sprintf('Your resume "%s" was rejected. Remark: %s', $resume->label, $validated['admin_remark']),
            $approved ? 'success' : 'warning'
        );

        $subject = $approved ? 'Your resume has been verified' : 'Your resume needs changes';
        $this->mail->send(
            $student->user,
            new ResumeReviewedMail($student->full_name, $resume->label, $approved, $validated['admin_remark'] ?? null, $subject),
            $subject,
            'emails.resume-reviewed'
        );

        return true;
    }

    /**
     * No-login streaming for signed links (companies, Excel exports, admin preview proxy).
     */
    public function signed(Resume $resume): StreamedResponse|JsonResponse
    {
        return $this->file($resume);
    }
}
