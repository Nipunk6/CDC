<?php

namespace App\Http\Controllers;

use App\Models\JobPostingSpoc;
use App\Models\ScptMember;
use App\Services\SpocService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P10.2 student "SPOC" tab: for an SCPT member, every job profile they are SPOC for, with the company's current HR
 * details. Students who are not on any SCPT get an empty answer (the page shows nothing but a short note).
 */
class StudentSpocController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        $memberships = ScptMember::query()
            ->where('student_profile_id', $student->id)
            ->with([
                'placementCycle:id,name,type,status',
                'spocAssignments.jobPosting.postable.company',
                'spocAssignments.jobPosting.placementCycle:id,name',
                'spocAssignments.jobPosting.spocs.scptMember.studentProfile:id,roll_no,full_name,phone,institute_email',
            ])
            ->get();

        $assignments = $memberships->flatMap(fn (ScptMember $m) => $m->spocAssignments)
            ->filter(fn (JobPostingSpoc $s) => $s->jobPosting && $s->jobPosting->status !== 'cancelled')
            ->sortByDesc(fn (JobPostingSpoc $s) => $s->jobPosting->floated_at)
            ->map(function (JobPostingSpoc $s) use ($student) {
                $posting = $s->jobPosting;
                $company = $posting->company();

                return [
                    'id' => $s->id,
                    'job_posting_id' => $posting->id,
                    'company' => ['name' => $company?->name, 'logo_url' => $company?->logo_url, 'website' => $company?->website],
                    'role' => $posting->title(),
                    'type' => $posting->postingType(),
                    'placement_cycle' => $posting->placementCycle?->only(['id', 'name']),
                    'status' => $posting->status,
                    'application_deadline' => $posting->application_deadline,
                    'hr' => SpocService::hrDetails($company),
                    'co_spocs' => $posting->spocs
                        ->map(fn (JobPostingSpoc $o) => $o->scptMember?->studentProfile)
                        ->filter(fn ($p) => $p && $p->id !== $student->id)
                        ->map(fn ($p) => ['name' => $p->full_name, 'roll_no' => $p->roll_no, 'phone' => $p->phone, 'email' => $p->institute_email])
                        ->values()->all(),
                    'assigned_at' => $s->created_at,
                ];
            })->values();

        return response()->json([
            'is_scpt' => $memberships->isNotEmpty(),
            'memberships' => $memberships->map(fn (ScptMember $m) => [
                'placement_cycle' => $m->placementCycle?->only(['id', 'name', 'type', 'status']),
                'designation' => $m->designation,
            ])->values()->all(),
            'assignments' => $memberships->isNotEmpty() ? $assignments->all() : [],
        ]);
    }
}
