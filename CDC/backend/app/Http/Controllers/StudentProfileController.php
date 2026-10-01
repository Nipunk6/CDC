<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'student' => $this->payload($student),
        ]);
    }

    /**
     * Students may only touch their personal contact fields; anything else in the body is ignored.
     */
    public function update(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $validated = validator($request->only(StudentProfile::SELF_EDITABLE), [
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'home_state' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
        ], [
            'phone.regex' => 'Phone may contain only digits, spaces, +, - and brackets.',
        ])->validate();

        $student->update($validated);

        return response()->json([
            'message' => 'Profile updated.',
            'student' => $this->payload($student->fresh()),
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:1024'],
        ], [
            'photo.max' => 'The photo must be 1 MB or smaller.',
            'photo.uploaded' => 'The photo must be 1 MB or smaller.',
            'photo.mimes' => 'The photo must be a JPG or PNG image.',
        ]);

        $file = $request->file('photo');
        $path = $file->storeAs(
            "student-photos/{$student->roll_no}",
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'),
            'local'
        );

        $old = $student->photo_path;
        $student->update(['photo_path' => $path]);

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        return response()->json([
            'message' => 'Photo updated.',
            'student' => $this->payload($student->fresh()),
        ]);
    }

    public function photo(Request $request): StreamedResponse|JsonResponse
    {
        $student = $this->student($request);

        if (! $student->photo_path || ! Storage::disk('local')->exists($student->photo_path)) {
            return response()->json(['message' => 'No photo uploaded.'], 404);
        }

        return Storage::disk('local')->response($student->photo_path);
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile;

        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }

    private function payload(StudentProfile $student): array
    {
        $blocks = $student->placementBlocks()
            ->where('active', true)
            ->with(['placementCycle:id,name', 'offer:id,offer_type'])
            ->get();

        return $student->toArray() + [
            'active_blocks' => $blocks->map(fn ($b) => [
                'id' => $b->id,
                'placement_cycle' => $b->placementCycle?->name,
                'scope' => $b->scope,
                'reason' => $b->reason,
                'message' => $b->message().($b->placementCycle ? " ({$b->placementCycle->name})" : ''),
            ])->values(),
        ];
    }
}
