<?php

namespace App\Http\Controllers;

use App\Mail\StudentProfileUpdatedMail;
use App\Models\BranchChangeRequest;
use App\Services\AuditService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBranchChangeController extends Controller
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
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = BranchChangeRequest::query()
            ->with([
                'studentProfile:id,roll_no,full_name,programme,branch,graduating_batch,current_cgpa',
                'decidedBy:id,name',
            ])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'branch_change_requests' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function update(Request $request, BranchChangeRequest $branchChangeRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remark' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ], [
            'admin_remark.required_if' => 'Add a remark explaining why the request is rejected.',
        ]);

        $student = $branchChangeRequest->studentProfile;
        $profileBefore = $student->only(['programme', 'branch']);

        // Re-read under a row lock so two admins cannot both decide the same request.
        $decided = DB::transaction(function () use ($branchChangeRequest, $student, $validated, $request): bool {
            $locked = BranchChangeRequest::query()->whereKey($branchChangeRequest->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $branchChangeRequest->update([
                'status' => $validated['status'],
                'admin_remark' => $validated['admin_remark'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            if ($validated['status'] === 'approved') {
                $student->update(array_filter([
                    'branch' => $branchChangeRequest->requested_branch,
                    'programme' => $branchChangeRequest->requested_programme,
                ]));
            }

            return true;
        });

        if (! $decided) {
            return response()->json(['message' => 'This request has already been decided.'], 422);
        }

        $this->audit->log(
            $request,
            'branch_change.'.($validated['status'] === 'approved' ? 'approve' : 'reject'),
            $branchChangeRequest,
            ['status' => 'pending'],
            ['status' => $validated['status'], 'admin_remark' => $validated['admin_remark'] ?? null]
        );

        if ($validated['status'] === 'approved') {
            $this->audit->log($request, 'student.update', $student, $profileBefore, $student->only(['programme', 'branch']));
        }

        $approved = $validated['status'] === 'approved';
        $headline = $approved
            ? sprintf('Your branch change request has been approved. Your branch is now %s.', $student->branch)
            : 'Your branch change request has been rejected.';

        $this->notifications->createInAppNotification(
            $student->user,
            $approved ? 'Branch change approved' : 'Branch change rejected',
            $headline.($approved ? '' : ' Remark: '.$validated['admin_remark']),
            $approved ? 'success' : 'warning'
        );

        $this->mail->send(
            $student->user,
            new StudentProfileUpdatedMail(
                name: $student->full_name,
                headline: $headline,
                lines: $approved ? [] : ['CDC remark: '.$validated['admin_remark']],
                subjectLine: 'Branch change request '.($approved ? 'approved' : 'rejected'),
            ),
            'Branch change request '.($approved ? 'approved' : 'rejected'),
            'emails.student-profile-updated'
        );

        return response()->json([
            'message' => $approved ? 'Branch change approved and profile updated.' : 'Branch change rejected.',
            'branch_change_request' => $branchChangeRequest->fresh()->load('studentProfile:id,roll_no,full_name,programme,branch'),
        ]);
    }
}
