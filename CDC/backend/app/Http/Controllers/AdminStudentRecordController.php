<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\StudentNote;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\StudentRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin student page's extras (Superset parity S4.5): internal notes, the per-student Placement and Eligibility
 * reports, and "Mark all as verified" for the student's resumes. Admin only (routes sit in the role:admin group).
 */
class AdminStudentRecordController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly StudentRecordService $record,
        private readonly ExportService $exports
    ) {
    }

    public function notes(StudentProfile $studentProfile): JsonResponse
    {
        return response()->json([
            'notes' => $studentProfile->adminNotes()
                ->with('author:id,name,email')
                ->latest('id')
                ->get()
                ->map(fn (StudentNote $n) => $this->notePayload($n)),
        ]);
    }

    public function storeNote(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Write the note first.',
        ]);

        $note = $studentProfile->adminNotes()->create([
            'author_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        // Logged against the student so it shows in the page's Activity tab (admin only).
        $this->audit->log($request, 'student.note_create', $studentProfile, null, ['note_id' => $note->id, 'body' => $note->body]);

        return response()->json([
            'message' => 'Note added.',
            'note' => $this->notePayload($note->load('author:id,name,email')),
        ], 201);
    }

    /**
     * The author or a super admin may delete a note.
     */
    public function destroyNote(Request $request, StudentProfile $studentProfile, StudentNote $note): JsonResponse
    {
        abort_if($note->student_profile_id !== $studentProfile->id, 404);

        $user = $request->user();
        if ($note->author_id !== $user->id && ! $user->is_super_admin) {
            return response()->json(['message' => 'Only the note\'s author or a super admin can delete it.'], 403);
        }

        $before = ['note_id' => $note->id, 'author_id' => $note->author_id, 'body' => $note->body];
        $note->delete();

        $this->audit->log($request, 'student.note_delete', $studentProfile, $before, null);

        return response()->json(['message' => 'Note deleted.']);
    }

    public function placementReport(Request $request, StudentProfile $studentProfile): StreamedResponse
    {
        $applications = $this->record->applications($studentProfile);
        $this->audit->log($request, 'student.placement_report', $studentProfile, null, ['applications' => $applications->count()]);

        return $this->exports->studentPlacementReport($studentProfile, $applications);
    }

    public function eligibilityReport(Request $request, StudentProfile $studentProfile): StreamedResponse
    {
        $rows = $this->record->eligibility($studentProfile);
        $this->audit->log($request, 'student.eligibility_report', $studentProfile, null, [
            'job_profiles' => count($rows),
            'eligible' => count(array_filter($rows, fn (array $r) => $r['eligible'])),
        ]);

        return $this->exports->studentEligibilityReport($studentProfile, $rows);
    }

    /**
     * "Mark all as verified": verifies each listed pending resume through AdminResumeController::decide(), so the
     * race guard, audit (`resume.approve`), notice and mail are exactly those of a single decision. A resume the
     * student replaced after the page loaded (stale `expected_updated_at`) is skipped and reported.
     */
    public function verifyAllResumes(Request $request, StudentProfile $studentProfile, AdminResumeController $resumes): JsonResponse
    {
        $validated = $request->validate([
            'resumes' => ['required', 'array', 'min:1', 'max:20'],
            'resumes.*.id' => ['required', 'integer'],
            'resumes.*.expected_updated_at' => ['required', 'date'],
        ]);

        $verified = 0;
        $skipped = 0;
        foreach ($validated['resumes'] as $item) {
            $resume = Resume::query()->where('student_profile_id', $studentProfile->id)->find($item['id']);
            if (! $resume || $resume->status !== 'pending') {
                $skipped++;

                continue;
            }

            $ok = $resumes->decide($request, $resume, ['status' => 'approved', 'admin_remark' => null, 'expected_updated_at' => $item['expected_updated_at']]);
            $ok ? $verified++ : $skipped++;
        }

        $message = sprintf('%d resume(s) verified.', $verified);
        if ($skipped > 0) {
            $message .= sprintf(' %d skipped (already decided, or replaced by the student after you opened the page; review those again).', $skipped);
        }

        return response()->json(['message' => $message, 'verified' => $verified, 'skipped' => $skipped]);
    }

    private function notePayload(StudentNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'author' => $note->author ? ['id' => $note->author->id, 'name' => $note->author->name] : null,
            'created_at' => $note->created_at,
        ];
    }
}
