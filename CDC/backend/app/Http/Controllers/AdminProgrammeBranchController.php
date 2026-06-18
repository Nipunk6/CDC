<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeBranch;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProgrammeBranchController extends Controller
{
    public function __construct(private readonly PortalNotificationService $notificationService)
    {
    }

    public function index(): JsonResponse
    {
        $items = ProgrammeBranch::query()
            ->where('is_custom', true)
            ->orderBy('programme_name')
            ->orderBy('branch_name')
            ->get(['id', 'programme_name', 'branch_name', 'is_active', 'created_at']);

        $branchStates = ProgrammeBranch::query()
            ->where('is_custom', false)
            ->orderBy('programme_name')
            ->orderBy('branch_name')
            ->get(['id', 'programme_name', 'branch_name', 'is_active']);

        return response()->json([
            'custom_branches' => $items
                ->groupBy('programme_name')
                ->map(fn ($rows, $programme) => [
                    'programme' => $programme,
                    'branches' => $rows->map(fn ($row) => [
                        'id' => $row->id,
                        'branch_name' => $row->branch_name,
                        'is_active' => $row->is_active,
                    ])->values(),
                ])
                ->values(),
            'items' => $items,
            'branch_states' => $branchStates
                ->groupBy('programme_name')
                ->map(fn ($rows, $programme) => [
                    'programme' => $programme,
                    'branches' => $rows->map(fn ($row) => [
                        'branch_name' => $row->branch_name,
                        'is_active' => $row->is_active,
                    ])->values(),
                ])
                ->values(),
        ]);
    }

    public function updateExistingStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_name' => ['required', 'string', 'max:255'],
            'branch_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $programmeBranch = ProgrammeBranch::updateOrCreate(
            [
                'programme_name' => $validated['programme_name'],
                'branch_name' => $validated['branch_name'],
                'is_custom' => false,
            ],
            [
                'is_active' => $validated['is_active'],
                'created_by' => $request->user()?->id,
            ]
        );

        $this->notifyAdmins(
            request: $request,
            actionText: sprintf('marked as %s', $validated['is_active'] ? 'active' : 'inactive'),
            programmeName: $validated['programme_name'],
            branchName: $validated['branch_name']
        );

        return response()->json([
            'message' => 'Branch status updated successfully.',
            'programme_branch' => $programmeBranch,
        ]);
    }

    public function destroy(ProgrammeBranch $programmeBranch): JsonResponse
    {
        if (! $programmeBranch->is_custom) {
            return response()->json([
                'message' => 'Only custom branches can be deleted.',
            ], 422);
        }

        $programmeName = $programmeBranch->programme_name;
        $branchName = $programmeBranch->branch_name;

        $programmeBranch->delete();

        $this->notifyAdmins(
            request: request(),
            actionText: 'deleted',
            programmeName: $programmeName,
            branchName: $branchName
        );

        return response()->json([
            'message' => 'Custom branch deleted successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_name' => ['required', 'string', 'max:255'],
            'branch_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programme_branches', 'branch_name')->where(
                    fn ($query) => $query->where('programme_name', $request->input('programme_name'))
                        ->where('is_custom', true)
                ),
            ],
        ]);

        $branch = ProgrammeBranch::create([
            'programme_name' => $validated['programme_name'],
            'branch_name' => $validated['branch_name'],
            'is_custom' => true,
            'is_active' => true,
            'created_by' => $request->user()?->id,
        ]);

        $this->notifyAdmins(
            request: $request,
            actionText: 'added',
            programmeName: $validated['programme_name'],
            branchName: $validated['branch_name']
        );

        return response()->json([
            'message' => 'Custom branch added successfully.',
            'programme_branch' => $branch,
        ], 201);
    }

    private function notifyAdmins(Request $request, string $actionText, string $programmeName, string $branchName): void
    {
        $actorEmail = $request->user()?->email ?? 'unknown-admin';
        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->createInAppNotification(
                user: $admin,
                title: 'Branch Manager Update',
                message: sprintf(
                    'Admin %s: Branch "%s" in programme "%s" was %s by %s.',
                    $admin->email,
                    $branchName,
                    $programmeName,
                    $actionText,
                    $actorEmail
                ),
                type: 'info'
            );
        }
    }
}
