<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\StudentProfile;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentResumeController extends Controller
{
    public function __construct(private readonly FileUploadService $uploads)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'resumes' => $student->resumes()->orderBy('slot')->get()->map(fn (Resume $r) => $this->payload($r)),
            'max_slots' => Resume::MAX_SLOTS,
        ]);
    }

    /**
     * Upload into a slot. Re-uploading replaces the file and resets verification, unless locked.
     */
    public function store(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $validated = $request->validate([
            'slot' => ['required', 'integer', 'min:1', 'max:'.Resume::MAX_SLOTS],
            'label' => ['required', 'string', 'max:60'],
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Resume::MAX_KB],
        ], [
            'file.max' => 'The resume must be 2 MB or smaller.',
            // PHP itself rejects files above upload_max_filesize before Laravel sees them.
            'file.uploaded' => 'The resume must be 2 MB or smaller.',
            'file.mimes' => 'The resume must be a PDF.',
            'file.mimetypes' => 'The resume must be a PDF.',
        ]);

        $stored = $this->uploads->uploadResume($request->file('file'), $student, (int) $validated['slot']);

        try {
            // Lock the student's profile row so two uploads into the same slot are serialised (D60).
            [$resume, $oldPath, $created] = DB::transaction(function () use ($student, $validated, $stored): array {
                StudentProfile::query()->whereKey($student->id)->lockForUpdate()->first();

                $existing = $student->resumes()->where('slot', $validated['slot'])->first();

                if ($existing && $existing->isLocked()) {
                    return [null, null, false];
                }

                $attributes = [
                    'label' => trim($validated['label']),
                    'file_path' => $stored['path'],
                    'file_size' => $stored['size'],
                    'status' => 'pending',
                    'admin_remark' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];

                if ($existing) {
                    $oldPath = $existing->file_path;
                    $existing->update($attributes);

                    return [$existing, $oldPath, false];
                }

                return [$student->resumes()->create($attributes + ['slot' => $validated['slot']]), null, true];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);

            throw $exception;
        }

        if (! $resume) {
            Storage::disk('local')->delete($stored['path']);

            return response()->json([
                'message' => 'This resume is attached to an application that is still in progress, so it cannot be replaced.',
            ], 422);
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Resume uploaded. It will be verified by the CDC.',
            'resume' => $this->payload($resume->fresh()),
        ], $created ? 201 : 200);
    }

    /**
     * Rename a slot without replacing the file.
     */
    public function update(Request $request, Resume $resume): JsonResponse
    {
        $this->authorizeOwner($request, $resume);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ]);

        $resume->update(['label' => trim($validated['label'])]);

        return response()->json(['message' => 'Label updated.', 'resume' => $this->payload($resume)]);
    }

    public function destroy(Request $request, Resume $resume): JsonResponse
    {
        $this->authorizeOwner($request, $resume);

        if ($resume->isLocked()) {
            return response()->json([
                'message' => 'This resume is attached to an application that is still in progress, so it cannot be deleted.',
            ], 422);
        }

        if ($this->hasAnyApplication($resume)) {
            return response()->json([
                'message' => 'This resume is part of a past application record and cannot be deleted. You can replace it with a new file instead.',
            ], 422);
        }

        Storage::disk('local')->delete($resume->file_path);
        $resume->delete();

        return response()->json(['message' => 'Resume deleted.']);
    }

    public function file(Request $request, Resume $resume): StreamedResponse|JsonResponse
    {
        $this->authorizeOwner($request, $resume);

        return $this->stream($resume);
    }

    private function stream(Resume $resume): StreamedResponse|JsonResponse
    {
        if (! Storage::disk('local')->exists($resume->file_path)) {
            return response()->json(['message' => 'The file is missing.'], 404);
        }

        return Storage::disk('local')->response($resume->file_path, $resume->downloadName(), [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }

    private function hasAnyApplication(Resume $resume): bool
    {
        return $resume->applications()->exists();
    }

    private function authorizeOwner(Request $request, Resume $resume): void
    {
        // 404 rather than 403 so resume ids cannot be probed (Phase 1 pattern).
        abort_if($resume->student_profile_id !== $this->student($request)->id, 404, 'Resume not found.');
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }

    private function payload(Resume $resume): array
    {
        return $resume->toArray() + ['is_locked' => $resume->isLocked()];
    }
}
