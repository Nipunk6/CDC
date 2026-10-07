<?php

namespace App\Http\Controllers;

use App\Mail\BroadcastMail;
use App\Models\Notice;
use App\Models\NoticeAudience;
use App\Services\AudienceService;
use App\Services\AuditService;
use App\Services\BroadcastService;
use App\Support\Like;
use App\Support\UploadType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Notice board (Superset parity S7.1): draft → publish, optional email in BCC batches, optional PDF attachment on the
 * private disk. Rich text is stored as HTML and always displayed as plain text (D76). Companies never see notices (B3).
 */
class AdminNoticeController extends Controller
{
    public const PER_PAGE = 25;

    private const TRACKED = ['title', 'body', 'job_posting_id', 'published_at', 'attachment_name'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly AudienceService $audiences,
        private readonly BroadcastService $broadcast
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,draft,published'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Notice::query()->with(['audiences', 'createdBy:id,name'])->withCount('reads')
            ->when(($validated['status'] ?? 'all') === 'draft', fn ($q) => $q->whereNull('published_at'))
            ->when(($validated['status'] ?? 'all') === 'published', fn ($q) => $q->whereNotNull('published_at'))
            ->when(filled($validated['search'] ?? null), fn ($q) => Like::whereContains($q, ['title'], $validated['search']))
            ->orderByRaw('published_at is null desc')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'notices' => collect($page->items())->map(fn (Notice $n) => $this->payload($n)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Notice $notice): JsonResponse
    {
        return response()->json(['notice' => $this->payload($notice->load(['audiences', 'createdBy:id,name'])->loadCount('reads'))]);
    }

    public function store(Request $request): JsonResponse
    {
        [$fields, $groups] = $this->validated($request);

        $notice = DB::transaction(function () use ($fields, $groups, $request) {
            $notice = Notice::create($fields + ['created_by' => $request->user()->id]);
            $this->syncAudiences($notice, $groups);

            return $notice;
        });

        $this->audit->log($request, 'notice.create', $notice, null, $this->snapshot($notice));

        return response()->json(['message' => 'Notice saved as a draft. Publish it to show it to students.', 'notice' => $this->payload($notice->fresh(['audiences']))], 201);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        [$fields, $groups] = $this->validated($request);
        $before = $this->snapshot($notice->load('audiences'));

        DB::transaction(function () use ($notice, $fields, $groups): void {
            $notice->update($fields);
            $this->syncAudiences($notice, $groups);
        });

        $this->audit->log($request, 'notice.update', $notice, $before, $this->snapshot($notice->fresh(['audiences'])));

        return response()->json(['message' => $notice->published_at ? 'Notice updated. Edits are not emailed again.' : 'Notice updated.', 'notice' => $this->payload($notice->fresh(['audiences']))]);
    }

    public function destroy(Request $request, Notice $notice): JsonResponse
    {
        $before = $this->snapshot($notice->load('audiences')) + ['id' => $notice->id];
        if ($notice->attachment_path) {
            Storage::disk('local')->delete($notice->attachment_path);
        }
        $notice->delete();
        $this->audit->log($request, 'notice.delete', null, $before, null);

        return response()->json(['message' => 'Notice deleted.']);
    }

    public function publish(Request $request, Notice $notice): JsonResponse
    {
        $validated = $request->validate(['send_email' => ['nullable', 'boolean']]);

        if ($notice->published_at) {
            return response()->json(['message' => 'This notice has already been published.'], 422);
        }
        $notice->load('audiences');
        if ($notice->audiences->isEmpty()) {
            return response()->json(['message' => 'Add an audience before publishing.'], 422);
        }

        $notice->update(['published_at' => now()]);
        $audience = $notice->audienceQuery()->count();

        $mailed = 0;
        if ($validated['send_email'] ?? false) {
            $mailed = $this->mailNotice($notice);
            $notice->update(['emailed_at' => now()]);
        }

        $this->audit->log($request, 'notice.publish', $notice, ['published_at' => null], [
            'published_at' => $notice->published_at->toIso8601String(), 'audience' => $audience, 'emailed' => $mailed,
        ]);

        return response()->json([
            'message' => "Notice published to {$audience} student(s)".($mailed ? " and emailed to {$mailed}." : '.'),
            'notice' => $this->payload($notice->fresh(['audiences'])->loadCount('reads')),
        ]);
    }

    public function uploadAttachment(Request $request, Notice $notice): JsonResponse
    {
        // The content must really be a PDF, not just the name (L24).
        $request->validate(
            ['file' => ['required', 'file', 'mimes:pdf', 'max:5120', UploadType::rule(['pdf'], 'The attachment must be a PDF.')]],
            ['file.max' => 'The attachment must be at most 5 MB.', 'file.mimes' => 'The attachment must be a PDF.']
        );

        $before = ['attachment_name' => $notice->attachment_name];
        $old = $notice->attachment_path;
        $file = $request->file('file');
        $path = $file->storeAs("notices/{$notice->id}", Str::uuid().'.pdf', 'local');
        $notice->update(['attachment_path' => $path, 'attachment_name' => Str::limit($file->getClientOriginalName(), 200, ''), 'attachment_size' => $file->getSize() ?: 0]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }
        $this->audit->log($request, 'notice.attachment', $notice, $before, ['attachment_name' => $notice->attachment_name]);

        return response()->json(['message' => 'Attachment uploaded.', 'notice' => $this->payload($notice->fresh(['audiences']))]);
    }

    public function destroyAttachment(Request $request, Notice $notice): JsonResponse
    {
        abort_unless($notice->attachment_path, 404, 'This notice has no attachment.');
        $before = ['attachment_name' => $notice->attachment_name];
        Storage::disk('local')->delete($notice->attachment_path);
        $notice->update(['attachment_path' => null, 'attachment_name' => null, 'attachment_size' => null]);
        $this->audit->log($request, 'notice.attachment', $notice, $before, ['attachment_name' => null]);

        return response()->json(['message' => 'Attachment removed.', 'notice' => $this->payload($notice->fresh(['audiences']))]);
    }

    public function attachment(Notice $notice): StreamedResponse
    {
        return self::streamAttachment($notice);
    }

    /**
     * Audience size and labels for the given groups, before saving (notice and survey composers).
     */
    public function previewAudience(Request $request): JsonResponse
    {
        $validated = $request->validate(['kind' => ['required', 'in:notice,survey'], 'audiences' => ['present', 'array']]);
        $groups = $this->audiences->normalise($validated['audiences'], $validated['kind'] === 'notice' ? AudienceService::NOTICE_TYPES : AudienceService::SURVEY_TYPES);

        return response()->json([
            'count' => $this->audiences->query($groups)->count(),
            'labels' => array_map(fn ($g) => $this->audiences->describe($g), $groups),
        ]);
    }

    public static function streamAttachment(Notice $notice): StreamedResponse
    {
        abort_unless($notice->attachment_path && Storage::disk('local')->exists($notice->attachment_path), 404, 'Attachment not found.');

        return Storage::disk('local')->download($notice->attachment_path, $notice->attachment_name ?: 'notice.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The mail attaches a copy of the notice's PDF made for this send (L23): replacing or deleting the notice's
     * attachment, or the notice, never breaks a BCC batch still in the queue. The copy is deleted after the send.
     */
    private function mailNotice(Notice $notice): int
    {
        $copy = null;
        if ($notice->attachment_path && Storage::disk('local')->exists($notice->attachment_path)) {
            $copy = "broadcast-attachments/notice-{$notice->id}/".Str::uuid().'.pdf';
            Storage::disk('local')->copy($notice->attachment_path, $copy);
        }

        $mailable = new BroadcastMail(
            "Notice: {$notice->title}",
            $notice->title,
            BroadcastMail::paragraphs($notice->body),
            rtrim((string) config('app.frontend_url'), '/').'/student/notices',
            'Open Notices',
            $copy,
            $copy ? $notice->attachment_name : null
        );

        return $this->broadcast->toAudience($notice->audienceQuery(), $mailable, 'emails.broadcast', [
            'job_posting_id' => $notice->job_posting_id,
            'kind' => 'notice',
        ], deleteAttachmentAfter: true);
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'audiences' => ['present', 'array'],
        ]);
        $groups = $this->audiences->normalise($validated['audiences'], AudienceService::NOTICE_TYPES);

        return [[
            'title' => trim($validated['title']),
            'body' => $validated['body'] ?? null,
            'job_posting_id' => $this->audiences->postingOf($groups),
        ], $groups];
    }

    /** @param  list<array<string, mixed>>  $groups */
    private function syncAudiences(Notice $notice, array $groups): void
    {
        $notice->audiences()->delete();
        foreach ($groups as $group) {
            $notice->audiences()->create($group);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Notice $notice): array
    {
        return $notice->only(self::TRACKED) + ['audiences' => $notice->audiences->map(fn (NoticeAudience $a) => $a->toGroup())->all()];
    }

    /** @return array<string, mixed> */
    private function payload(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'job_posting_id' => $notice->job_posting_id,
            'published_at' => $notice->published_at,
            'emailed_at' => $notice->emailed_at,
            'created_at' => $notice->created_at,
            'created_by' => $notice->createdBy?->name,
            'attachment' => $notice->attachment_path ? ['name' => $notice->attachment_name, 'size' => $notice->attachment_size] : null,
            'audiences' => $notice->audiences->map(fn (NoticeAudience $a) => $a->toGroup() + ['label' => $this->audiences->describe($a->toGroup())])->all(),
            'audience_count' => $notice->audienceQuery()->count(),
            'read_count' => (int) ($notice->reads_count ?? 0),
        ];
    }
}
