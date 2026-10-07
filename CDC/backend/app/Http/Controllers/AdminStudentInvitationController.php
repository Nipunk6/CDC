<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\StudentAccountService;
use App\Support\StudentDirectoryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Send Invitations (Superset parity S5): the invitation list with Sent / Accepted / Revoked, bulk "Re - Send
 * Invites" and "Revoke Invites". Invitations are personal mails (a set-password link each), so a bulk resend
 * dispatches one E1 per student through StudentAccountService::sendInvitation, never a BCC batch.
 */
class AdminStudentInvitationController extends Controller
{
    public const PER_PAGE = 50;

    public const ACTIVATED_MESSAGE = 'This student has already activated their account. Use Suspend instead.';

    public function __construct(
        private readonly AuditService $audit,
        private readonly StudentAccountService $accounts
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate($this->filterRules() + [
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Status chips count within the Batches + search filters, across every status.
        $base = $this->baseQuery(Arr::except($validated, ['invitation_status']));
        $counts = [];
        foreach (['sent', 'accepted', 'revoked'] as $status) {
            $query = clone $base;
            StudentDirectoryFilters::applyInvitationStatus($query, $status);
            $counts[$status] = $query->count();
        }

        $query = clone $base;
        if (! empty($validated['invitation_status'])) {
            StudentDirectoryFilters::applyInvitationStatus($query, $validated['invitation_status']);
        }

        $page = $query
            ->with('user:id,email,is_active,invited_at,last_invited_at,invite_count,activated_at,invite_revoked_at')
            ->orderBy('roll_no')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'students' => collect($page->items())->map(fn (StudentProfile $s) => $this->payload($s)),
            'counts' => $counts + ['total' => array_sum($counts)],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * "Re - Send Invites": the selected students, or every pending one (`all_pending`, within the Batches + search
     * filters). Accepted students are skipped. `all_pending` also skips Revoked students (owner decision, D126): a
     * revoked invitation is re-sent only when the admin selects that student explicitly, which un-revokes it.
     * One audit row for the batch (D25 pattern).
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate($this->filterRules() + [
            'all_pending' => ['nullable', 'boolean'],
            'student_ids' => ['required_without:all_pending', 'array', 'max:5000'],
            'student_ids.*' => ['integer'],
        ]);
        $allPending = (bool) ($validated['all_pending'] ?? false);

        $query = $allPending
            ? $this->baseQuery(Arr::except($validated, ['invitation_status']))
            : StudentProfile::query()->whereIn('id', $validated['student_ids'] ?? []);

        $sent = [];
        $skipped = 0;
        $skippedRevoked = 0;
        $query->with('user')->chunkById(500, function (Collection $students) use (&$sent, &$skipped, &$skippedRevoked, $allPending): void {
            foreach ($students as $student) {
                if (! $student->user || $student->user->activated_at !== null) {
                    $skipped++;

                    continue;
                }
                if ($allPending && $student->user->invite_revoked_at !== null) {
                    $skippedRevoked++;

                    continue;
                }
                $this->accounts->sendInvitation($student);
                $sent[] = $student->roll_no;
            }
        });

        if ($sent === []) {
            return response()->json([
                'message' => match (true) {
                    $skipped > 0 && ! $allPending => 'No invitation sent: the selected students have already activated their accounts.',
                    $skippedRevoked > 0 => sprintf('No pending invitations to resend. %d revoked invitation(s) were not included; select those students to re-invite them.', $skippedRevoked),
                    default => 'No pending invitations to resend.',
                },
            ], 422);
        }

        $this->audit->log($request, 'student.invite_resend_bulk', null, null, [
            'mode' => $allPending ? 'all_pending' : 'selected',
            'filters' => $allPending ? StudentDirectoryFilters::active(Arr::except($validated, ['all_pending', 'student_ids'])) : null,
            'sent_count' => count($sent),
            'skipped_accepted_count' => $skipped,
            'skipped_revoked_count' => $skippedRevoked,
            'roll_nos' => array_slice($sent, 0, 100),
            'roll_nos_truncated' => count($sent) > 100,
        ]);

        return response()->json([
            'message' => sprintf(
                'Invitation sent again to %d student(s).%s%s',
                count($sent),
                $skipped > 0 ? sprintf(' %d already accepted, skipped.', $skipped) : '',
                $skippedRevoked > 0 ? sprintf(' %d revoked, skipped.', $skippedRevoked) : ''
            ),
            'sent' => count($sent),
            'skipped' => $skipped,
            'skipped_revoked' => $skippedRevoked,
        ]);
    }

    /**
     * "Revoke Invites" in bulk. Refused as a whole when a selected student has already activated their account.
     */
    public function revoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:5000'],
            'student_ids.*' => ['integer'],
        ]);

        $students = StudentProfile::query()->with('user')->whereIn('id', $validated['student_ids'])->orderBy('roll_no')->get();
        $activated = $students->filter(fn (StudentProfile $s) => $s->user?->activated_at !== null);

        if ($activated->isNotEmpty()) {
            return response()->json([
                'message' => $activated->count() === 1 && $students->count() === 1
                    ? self::ACTIVATED_MESSAGE
                    : sprintf(
                        '%d selected student(s) have already activated their account (%s). Use Suspend instead.',
                        $activated->count(),
                        $activated->take(10)->pluck('roll_no')->implode(', ').($activated->count() > 10 ? ', ...' : '')
                    ),
                'activated' => $activated->pluck('roll_no')->values(),
            ], 422);
        }

        $revoked = [];
        $already = 0;
        foreach ($students as $student) {
            if (! $student->user || $student->user->invite_revoked_at !== null) {
                $already++;

                continue;
            }
            $this->accounts->revokeInvitation($student);
            $revoked[] = $student->roll_no;
        }

        if ($revoked !== []) {
            $this->audit->log($request, 'student.invite_revoke_bulk', null, null, [
                'revoked_count' => count($revoked),
                'already_revoked_count' => $already,
                'roll_nos' => array_slice($revoked, 0, 100),
                'roll_nos_truncated' => count($revoked) > 100,
            ]);
        }

        return response()->json([
            'message' => sprintf(
                '%d invitation(s) revoked.%s',
                count($revoked),
                $already > 0 ? sprintf(' %d were already revoked.', $already) : ''
            ),
            'revoked' => count($revoked),
            'already_revoked' => $already,
        ]);
    }

    /**
     * Revoke one student's invitation (student page). Refused for an activated account.
     */
    public function revokeOne(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $user = $studentProfile->user;

        if ($user->activated_at !== null) {
            return response()->json(['message' => self::ACTIVATED_MESSAGE], 422);
        }
        if ($user->invite_revoked_at !== null) {
            return response()->json(['message' => 'This invitation is already revoked. Resend it to give the student a new link.'], 422);
        }

        $this->accounts->revokeInvitation($studentProfile);
        $this->audit->log($request, 'student.invite_revoke', $studentProfile, ['invite_revoked_at' => null], [
            'invite_revoked_at' => $user->fresh()->invite_revoked_at?->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'Invitation revoked. The set-password link no longer works.',
            'invitation' => self::invitationFields($user->fresh()),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return Arr::only(StudentDirectoryFilters::rules(), ['search', 'batches', 'batches.*', 'invitation_status']);
    }

    /**
     * @return Builder<StudentProfile>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = StudentProfile::query();
        StudentDirectoryFilters::apply($query, Arr::only($filters, ['search', 'batches', 'invitation_status']));

        return $query;
    }

    private function payload(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'roll_no' => $student->roll_no,
            'full_name' => $student->full_name,
            'phone' => $student->phone,
            'graduating_batch' => $student->graduating_batch,
            'institute_email' => $student->institute_email,
            'personal_email' => $student->personal_email,
            'gender' => $student->gender,
            'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
            'programme' => $student->programme,
            'branch' => $student->branch,
            'is_active' => (bool) ($student->user?->is_active ?? true),
        ] + ($student->user ? self::invitationFields($student->user) : []);
    }

    /** The invitation fields of a student's user row (also used by the student page). */
    public static function invitationFields(\App\Models\User $user): array
    {
        return [
            'invitation_status' => $user->invitationStatus(),
            'invited_at' => $user->invited_at?->toIso8601String(),
            'last_invited_at' => $user->last_invited_at?->toIso8601String(),
            'invite_count' => (int) $user->invite_count,
            'activated_at' => $user->activated_at?->toIso8601String(),
            'invite_revoked_at' => $user->invite_revoked_at?->toIso8601String(),
        ];
    }
}
