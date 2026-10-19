<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\JobPostingSpoc;
use App\Models\PlacementCycle;
use App\Models\ScptMember;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\EligibilityService;
use App\Services\SpocService;
use App\Services\SpreadsheetImportService;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P10.1 SCPT registry and P10.2 SPOC assignment (owner decisions 2026-10-19, P3-D50). SCPT membership is per placement
 * cycle; a job profile may have several SPOCs, chosen from its cycle's SCPT. Being eligible for the job profile only
 * raises a warning. Every write is audited; every SPOC change mails the SPOC (SpocService).
 */
class AdminScptController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly EligibilityService $eligibility,
        private readonly SpocService $spocs,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    // ---------------------------------------------------------------- SCPT registry

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'placement_cycle_id' => ['required', 'integer', 'exists:placement_cycles,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $members = ScptMember::query()
            ->where('placement_cycle_id', $validated['placement_cycle_id'])
            ->when($search !== '', fn ($q) => $q->whereHas('studentProfile', fn ($s) => Like::whereContains($s, ['roll_no', 'full_name'], $search)))
            ->with(['studentProfile:id,roll_no,full_name,programme,branch,graduating_batch,phone,institute_email', 'spocAssignments.jobPosting.postable.company:id,name'])
            ->get()
            ->sortBy(fn (ScptMember $m) => $m->studentProfile?->roll_no)
            ->values();

        return response()->json([
            'members' => $members->map(fn (ScptMember $m) => $this->memberPayload($m))->all(),
        ]);
    }

    /**
     * Add students to a cycle's SCPT by roll number: a pasted list or an uploaded sheet (first column). Unknown roll
     * numbers are reported, never dropped; one audit row per batch.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'placement_cycle_id' => ['required', 'integer', 'exists:placement_cycles,id'],
            'designation' => ['nullable', 'string', 'max:100'],
            'roll_nos' => ['required_without:file', 'array'],
            'roll_nos.*' => ['nullable', 'string', 'max:30'],
            'file' => array_merge(['required_without:roll_nos'], SpreadsheetImportService::UPLOAD_RULES),
        ]);
        $cycle = PlacementCycle::findOrFail($request->integer('placement_cycle_id'));

        try {
            $rolls = $request->hasFile('file')
                ? $this->spreadsheets->firstColumn($request->file('file'), 'rollno', 'rollnumber', 'institutorollnumber')
                : $request->input('roll_nos', []);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $rolls = array_values(array_unique(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rolls))));
        if ($rolls === []) {
            return response()->json(['message' => 'No roll numbers were found in the input.'], 422);
        }

        $students = StudentProfile::query()->whereIn('roll_no', $rolls)->pluck('id', 'roll_no');
        $unknown = array_values(array_diff($rolls, $students->keys()->all()));
        $already = ScptMember::query()->where('placement_cycle_id', $cycle->id)->whereIn('student_profile_id', $students->values())->pluck('student_profile_id')->all();
        $new = array_values(array_diff($students->values()->all(), $already));

        $designation = filled($request->input('designation')) ? trim((string) $request->input('designation')) : null;
        foreach ($new as $studentId) {
            ScptMember::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $studentId, 'designation' => $designation, 'added_by' => $request->user()->id]);
        }
        if ($new !== []) {
            $this->audit->log($request, 'scpt.add', $cycle, null, [
                'added' => count($new),
                'designation' => $designation,
                'roll_nos' => array_slice($students->flip()->only($new)->values()->all(), 0, 100),
            ]);
        }

        return response()->json([
            'message' => sprintf('%d student(s) added to the SCPT. %d already in it. %d not found.', count($new), count($already), count($unknown)),
            'added' => count($new),
            'errors' => array_map(fn ($r) => ['roll_no' => $r, 'reason' => 'No student with this roll number.'], $unknown),
        ]);
    }

    public function update(Request $request, ScptMember $scptMember): JsonResponse
    {
        $validated = $request->validate(['designation' => ['present', 'nullable', 'string', 'max:100']]);
        $designation = filled($validated['designation']) ? trim((string) $validated['designation']) : null;

        $before = $scptMember->only(['designation']);
        $scptMember->update(['designation' => $designation]);
        $this->audit->log($request, 'scpt.update', $scptMember, $before, $scptMember->only(['designation']));

        return response()->json(['message' => 'SCPT member updated.', 'member' => $this->memberPayload($scptMember->load('studentProfile', 'spocAssignments.jobPosting.postable.company'))]);
    }

    /** Removing a member also ends their SPOC assignments; each of those job profiles is mailed as a removal. */
    public function destroy(Request $request, ScptMember $scptMember): JsonResponse
    {
        $scptMember->load(['studentProfile.user', 'spocAssignments.jobPosting.postable.company', 'spocAssignments.jobPosting.placementCycle']);
        $postings = $scptMember->spocAssignments->map->jobPosting->filter()->values();
        $before = ['roll_no' => $scptMember->studentProfile?->roll_no, 'placement_cycle_id' => $scptMember->placement_cycle_id,
            'designation' => $scptMember->designation, 'spoc_for' => $postings->pluck('id')->all()];

        $member = $scptMember->replicate();
        $member->setRelation('studentProfile', $scptMember->studentProfile);
        DB::transaction(fn () => $scptMember->delete()); // cascades job_posting_spocs
        $this->audit->log($request, 'scpt.remove', null, $before, null);

        foreach ($postings as $posting) {
            $this->spocs->notifyRemoved($member, $posting);
        }

        return response()->json(['message' => $postings->isEmpty()
            ? 'Removed from the SCPT.'
            : sprintf('Removed from the SCPT and as SPOC from %d job profile(s). They have been told by mail.', $postings->count())]);
    }

    // ---------------------------------------------------------------- SPOC assignment

    /**
     * The job profile's SPOCs, and every SCPT member of its cycle with their own eligibility for it, so the CDC sees
     * who is also a candidate (a warning, not a block).
     */
    public function candidates(JobPosting $jobPosting): JsonResponse
    {
        $assigned = $jobPosting->spocs()->pluck('id', 'scpt_member_id');
        $members = ScptMember::query()
            ->where('placement_cycle_id', $jobPosting->placement_cycle_id)
            ->with(['studentProfile.user', 'studentProfile.cycleEnrollments'])
            ->get()
            ->sortBy(fn (ScptMember $m) => $m->studentProfile?->roll_no)
            ->values();
        $applied = $jobPosting->applications()->where('status', 'applied')->pluck('student_profile_id')->flip();

        return response()->json([
            'placement_cycle' => $jobPosting->placementCycle?->only(['id', 'name']),
            'candidates' => $members->map(function (ScptMember $m) use ($jobPosting, $assigned, $applied) {
                $student = $m->studentProfile;
                $check = $student ? $this->eligibility->check($student, $jobPosting) : ['eligible' => false, 'reasons' => []];

                return [
                    'scpt_member_id' => $m->id,
                    'spoc_id' => $assigned->get($m->id),
                    'designation' => $m->designation,
                    'student' => $student?->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'phone', 'institute_email']),
                    'eligible' => (bool) $check['eligible'],
                    'reasons' => array_values($check['reasons']),
                    'applied' => $student ? $applied->has($student->id) : false,
                ];
            })->all(),
        ]);
    }

    public function assign(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'scpt_member_ids' => ['required', 'array', 'min:1', 'max:50'],
            'scpt_member_ids.*' => ['integer', 'distinct'],
        ]);
        if ($jobPosting->status === 'cancelled') {
            return response()->json(['message' => 'This job profile is cancelled. SPOCs cannot be assigned to it.'], 422);
        }

        $members = ScptMember::query()->whereIn('id', $validated['scpt_member_ids'])->with('studentProfile:id,roll_no')->get();
        $foreign = $members->where('placement_cycle_id', '!=', $jobPosting->placement_cycle_id);
        if ($members->count() !== count($validated['scpt_member_ids']) || $foreign->isNotEmpty()) {
            return response()->json(['message' => 'Choose SPOCs from the SCPT of this job profile\'s placement.'], 422);
        }

        $existing = $jobPosting->spocs()->pluck('scpt_member_id')->all();
        $new = $members->reject(fn (ScptMember $m) => in_array($m->id, $existing, true))->values();
        if ($new->isEmpty()) {
            return response()->json(['message' => 'The selected students are already SPOCs for this job profile.', 'added' => 0]);
        }

        $created = DB::transaction(fn () => $new->map(fn (ScptMember $m) => JobPostingSpoc::create([
            'job_posting_id' => $jobPosting->id, 'scpt_member_id' => $m->id, 'assigned_by' => $request->user()->id,
        ])));
        $this->audit->log($request, 'spoc.assign', $jobPosting, null, ['roll_nos' => $new->map(fn ($m) => $m->studentProfile?->roll_no)->all()]);

        $jobPosting->load(['postable.company', 'placementCycle']);
        foreach ($created as $spoc) {
            $spoc->setRelation('jobPosting', $jobPosting);
            $spoc->load('scptMember.studentProfile.user');
            $this->spocs->notifyAssigned($spoc);
        }

        return response()->json([
            'message' => sprintf('%d SPOC(s) assigned. Each has been told by mail with the current HR details.', $created->count()),
            'added' => $created->count(),
        ]);
    }

    public function unassign(Request $request, JobPosting $jobPosting, JobPostingSpoc $jobPostingSpoc): JsonResponse
    {
        abort_if($jobPostingSpoc->job_posting_id !== $jobPosting->id, 404, 'Not found.');
        $jobPostingSpoc->load('scptMember.studentProfile.user');
        $member = $jobPostingSpoc->scptMember;

        $jobPostingSpoc->delete();
        $this->audit->log($request, 'spoc.remove', $jobPosting, ['roll_no' => $member?->studentProfile?->roll_no], null);

        $jobPosting->load(['postable.company', 'placementCycle']);
        if ($member) {
            $this->spocs->notifyRemoved($member, $jobPosting);
        }

        return response()->json(['message' => 'SPOC removed. They have been told by mail.']);
    }

    /** @return array<string, mixed> */
    private function memberPayload(ScptMember $m): array
    {
        return [
            'id' => $m->id,
            'placement_cycle_id' => $m->placement_cycle_id,
            'designation' => $m->designation,
            'student' => $m->studentProfile?->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'phone', 'institute_email']),
            'spoc_for' => $m->spocAssignments->map(fn (JobPostingSpoc $s) => [
                'job_posting_id' => $s->job_posting_id,
                'title' => $s->jobPosting?->title(),
                'company' => $s->jobPosting?->company()?->name,
            ])->values()->all(),
            'created_at' => $m->created_at,
        ];
    }
}
