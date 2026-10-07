<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student "Notices" page (Superset parity S7.1): published notices in the student's audience, newest first, with
 * read receipts for the unread dots. A notice outside the student's audience is a 404.
 */
class StudentNoticeController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * Newest first, 20 per page. The audience filter, the unread count (over all of the student's notices) and the
     * read flags all run in SQL, so older notices never drop off a busy board (M3).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $student = $this->student($request);
        $visible = Notice::query()->visibleTo($student);
        $readBy = fn ($reads) => $reads->where('student_profile_id', $student->id);

        $unread = (clone $visible)->whereDoesntHave('reads', $readBy)->count();
        $page = $visible
            ->withExists(['reads as is_read' => $readBy])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'notices' => collect($page->items())->map(fn (Notice $n) => $this->payload($n) + ['is_read' => (bool) $n->is_read])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'unread_count' => $unread,
        ]);
    }

    /**
     * Opening a notice marks it read.
     */
    public function read(Request $request, Notice $notice): JsonResponse
    {
        $student = $this->student($request);
        abort_unless($notice->isVisibleTo($student), 404, 'Notice not found.');

        NoticeRead::query()->firstOrCreate(['notice_id' => $notice->id, 'student_profile_id' => $student->id], ['read_at' => now()]);

        return response()->json(['notice' => $this->payload($notice) + ['is_read' => true]]);
    }

    public function attachment(Request $request, Notice $notice): StreamedResponse
    {
        abort_unless($notice->isVisibleTo($this->student($request)), 404, 'Notice not found.');

        return AdminNoticeController::streamAttachment($notice);
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }

    /** @return array<string, mixed> */
    private function payload(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'published_at' => $notice->published_at,
            'attachment' => $notice->attachment_path ? ['name' => $notice->attachment_name, 'size' => $notice->attachment_size] : null,
        ];
    }
}
