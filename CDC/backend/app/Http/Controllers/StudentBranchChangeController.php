<?php

namespace App\Http\Controllers;

use App\Models\BranchChangeRequest;
use App\Models\StudentProfile;
use App\Support\ProgrammeCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentBranchChangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        return response()->json([
            'branch_change_requests' => $student->branchChangeRequests()->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        $validated = $request->validate([
            'requested_programme' => ['nullable', 'string', 'max:255'],
            'requested_branch' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $programme = $validated['requested_programme'] ?? $student->programme;
        $match = ProgrammeCatalogue::resolve($programme, $validated['requested_branch']);

        if (! $match) {
            return response()->json(['message' => 'The requested branch is not in the programme catalogue.'], 422);
        }

        if ($match['programme'] === $student->programme && $match['branch'] === $student->branch) {
            return response()->json(['message' => 'You are already in this branch.'], 422);
        }

        // Lock the profile row so a double-submit cannot create two pending requests.
        $branchChange = DB::transaction(function () use ($student, $match, $validated): ?BranchChangeRequest {
            StudentProfile::query()->whereKey($student->id)->lockForUpdate()->first();

            if ($student->branchChangeRequests()->where('status', 'pending')->exists()) {
                return null;
            }

            return BranchChangeRequest::create([
                'student_profile_id' => $student->id,
                'current_branch' => $student->branch,
                'requested_branch' => $match['branch'],
                'requested_programme' => $match['programme'] === $student->programme ? null : $match['programme'],
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);
        });

        if (! $branchChange) {
            return response()->json([
                'message' => 'You already have a pending branch change request. Wait for the CDC to decide it first.',
            ], 409);
        }

        return response()->json([
            'message' => 'Branch change request submitted. The CDC will review it.',
            'branch_change_request' => $branchChange,
        ], 201);
    }
}
