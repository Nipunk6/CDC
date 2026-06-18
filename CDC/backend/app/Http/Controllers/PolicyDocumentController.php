<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocument;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\PortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyDocumentController extends Controller
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly PortalNotificationService $notificationService
    ) {
    }

    /**
     * Display a listing of all policy documents (Admin only).
     */
    public function index(): JsonResponse
    {
        $documents = PolicyDocument::orderBy('id', 'asc')->get();
        return response()->json($documents);
    }

    /**
     * Store a newly created policy document (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:pdf,link'],
            'url' => ['required_if:type,link', 'nullable', 'string'],
            'file' => ['required_if:type,pdf', 'nullable', 'file', 'mimes:pdf', 'max:5120'],
            'is_visible_jnf' => ['boolean'],
            'is_visible_inf' => ['boolean'],
        ]);

        $url = $validated['url'] ?? '';

        if ($validated['type'] === 'pdf' && $request->hasFile('file')) {
            $uploaded = $this->fileUploadService->uploadPolicyFile($request->file('file'));
            $url = $uploaded['url'];
        }

        $document = PolicyDocument::create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'url' => $url,
            'is_visible_jnf' => filter_var($request->input('is_visible_jnf', true), FILTER_VALIDATE_BOOLEAN),
            'is_visible_inf' => filter_var($request->input('is_visible_inf', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        $this->notifyAdmins($request, 'created', $document->title);

        return response()->json([
            'message' => 'Policy document created successfully.',
            'document' => $document,
        ], 201);
    }

    /**
     * Update the specified policy document (Admin only).
     */
    public function update(Request $request, PolicyDocument $policyDocument): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:pdf,link'],
            'url' => ['required_if:type,link', 'nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'is_visible_jnf' => ['boolean'],
            'is_visible_inf' => ['boolean'],
        ]);

        $url = $policyDocument->url;

        if ($validated['type'] === 'link') {
            $url = $validated['url'];
        } elseif ($validated['type'] === 'pdf' && $request->hasFile('file')) {
            $uploaded = $this->fileUploadService->uploadPolicyFile($request->file('file'));
            $url = $uploaded['url'];
        }

        $policyDocument->update([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'url' => $url,
            'is_visible_jnf' => filter_var($request->input('is_visible_jnf', true), FILTER_VALIDATE_BOOLEAN),
            'is_visible_inf' => filter_var($request->input('is_visible_inf', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        $this->notifyAdmins($request, 'updated', $policyDocument->title);

        return response()->json([
            'message' => 'Policy document updated successfully.',
            'document' => $policyDocument,
        ]);
    }

    /**
     * Remove the specified policy document (Admin only).
     */
    public function destroy(PolicyDocument $policyDocument): JsonResponse
    {
        $docTitle = $policyDocument->title;
        $policyDocument->delete();

        $this->notifyAdmins(request(), 'deleted', $docTitle);

        return response()->json([
            'message' => 'Policy document deleted successfully.',
        ]);
    }

    /**
     * Get active policy documents for recruiters based on form type (JNF or INF).
     */
    public function getForCompany(Request $request): JsonResponse
    {
        $formType = $request->query('form_type'); // 'jnf' or 'inf'

        $query = PolicyDocument::query();

        if ($formType === 'jnf') {
            $query->where('is_visible_jnf', true);
        } elseif ($formType === 'inf') {
            $query->where('is_visible_inf', true);
        } else {
            return response()->json(['message' => 'Invalid or missing form_type parameter.'], 400);
        }

        $documents = $query->orderBy('id', 'asc')->get(['id', 'title', 'type', 'url']);

        return response()->json($documents);
    }

    /**
     * Notify all admin users of actions performed on policy documents.
     */
    private function notifyAdmins(Request $request, string $actionText, string $docTitle): void
    {
        $actorEmail = $request->user()?->email ?? 'unknown-admin';
        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->createInAppNotification(
                user: $admin,
                title: 'Policy Document Update',
                message: sprintf(
                    'Admin %s: Policy document "%s" was %s by %s.',
                    $admin->email,
                    $docTitle,
                    $actionText,
                    $actorEmail
                ),
                type: 'info'
            );
        }
    }
}
