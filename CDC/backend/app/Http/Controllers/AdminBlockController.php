<?php

namespace App\Http\Controllers;

use App\Models\PlacementBlock;
use App\Services\AuditService;
use App\Services\PortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placement blocks (spec B4 / M7.3). Admin is god: manual/debarred blocks and unblocking at any time.
 * Blocks are never hard-deleted — unblocking keeps the row with who/when.
 */
class AdminBlockController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle_id' => ['nullable', 'integer'],
            'student_profile_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);

        $query = PlacementBlock::query()
            ->with([
                'studentProfile:id,roll_no,full_name,branch',
                'placementCycle:id,name',
                'offer:id,offer_type,job_posting_id',
                'blockedBy:id,name',
                'unblockedBy:id,name',
            ])
            ->latest('id');

        if (! empty($validated['cycle_id'])) {
            $query->where('placement_cycle_id', $validated['cycle_id']);
        }
        if (! empty($validated['student_profile_id'])) {
            $query->where('student_profile_id', $validated['student_profile_id']);
        }
        if (array_key_exists('active', $validated) && $validated['active'] !== null) {
            $query->where('active', (bool) $validated['active']);
        }

        return response()->json([
            'blocks' => $query->limit(2000)->get()->map(fn (PlacementBlock $b) => $b->toArray() + ['message' => $b->message()]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'placement_cycle_id' => ['required', 'integer', 'exists:placement_cycles,id'],
            'scope' => ['required', 'in:all,internships_only'],
            'reason' => ['required', 'in:manual,debarred'],
            'remark' => ['required', 'string', 'max:255'],
        ]);

        // Debarment blocks everything in the cycle whatever scope was picked (EligibilityService treats it so).
        if ($validated['reason'] === 'debarred') {
            $validated['scope'] = 'all';
        }

        $block = PlacementBlock::create($validated + [
            'active' => true,
            'blocked_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'block.create', $block, null, $block->only(['student_profile_id', 'placement_cycle_id', 'scope', 'reason', 'remark']));

        $this->notifications->createInAppNotification(
            $block->studentProfile->user,
            $validated['reason'] === 'debarred' ? 'Debarred from a placement' : 'Placement block added',
            $block->message().' Placement: '.$block->placementCycle->name.'.',
            'warning'
        );

        return response()->json(['message' => 'Block added.', 'block' => $block->fresh()->toArray() + ['message' => $block->message()]], 201);
    }

    /**
     * Unblock (sets inactive; never deletes).
     */
    public function destroy(Request $request, PlacementBlock $placementBlock): JsonResponse
    {
        if (! $placementBlock->active) {
            return response()->json(['message' => 'This block is already lifted.'], 422);
        }

        $placementBlock->update([
            'active' => false,
            'unblocked_by' => $request->user()->id,
            'unblocked_at' => now(),
        ]);

        // Nothing else blocks everything in this cycle → the student's other live applications are no longer "placed elsewhere".
        $stillBlocked = PlacementBlock::query()
            ->where('student_profile_id', $placementBlock->student_profile_id)
            ->where('placement_cycle_id', $placementBlock->placement_cycle_id)
            ->where('active', true)
            ->exists();
        $cleared = 0;
        if (! $stillBlocked) {
            $cleared = \App\Models\Application::query()
                ->where('student_profile_id', $placementBlock->student_profile_id)
                ->where('placed_elsewhere_flag', true)
                ->whereHas('jobPosting', fn ($q) => $q->where('placement_cycle_id', $placementBlock->placement_cycle_id))
                ->update(['placed_elsewhere_flag' => false]);
        }

        $this->audit->log($request, 'block.remove', $placementBlock, ['active' => true], ['active' => false, 'placed_elsewhere_flags_cleared' => $cleared]);

        $this->notifications->createInAppNotification(
            $placementBlock->studentProfile->user,
            'Placement block lifted',
            'The CDC lifted a placement block in '.$placementBlock->placementCycle->name.'.',
            'success'
        );

        return response()->json(['message' => 'Block lifted.']);
    }
}
