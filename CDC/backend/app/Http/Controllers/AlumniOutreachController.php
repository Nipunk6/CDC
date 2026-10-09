<?php

namespace App\Http\Controllers;

use App\Mail\AlumniOutreachConfirmationMail;
use App\Models\AlumniOutreachSubmission;
use App\Models\User;
use App\Services\PortalNotificationService;
use App\Support\MailCooldown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AlumniOutreachController extends Controller
{
    public function __construct(private readonly PortalNotificationService $notificationService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\s'.-]+$/u"],
            'email' => ['required', 'email:rfc', 'max:255'],
            'country_code' => ['required', 'string', 'max:10'],
            'phone_number' => ['required', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:40'],
            'graduation_year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'programme' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'current_organization' => ['required', 'string', 'max:255'],
            'current_designation' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            // Accept 'NA' (case-insensitive) or a valid URL
            'linkedin_url' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value) || strtolower(trim((string) $value)) === 'na') {
                        return;
                    }
                    if (filter_var(trim((string) $value), FILTER_VALIDATE_URL) === false) {
                        $fail('The LinkedIn URL must be a valid URL or "NA".');
                    }
                }
            ],
            'willing_to_mentor' => ['sometimes', 'boolean'],
            'willing_to_refer' => ['sometimes', 'boolean'],
            'message' => ['required', 'string', 'max:2000'],
            'general_comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = AlumniOutreachSubmission::create([
            ...$validated,
            'phone' => trim(sprintf('%s %s', $validated['country_code'], $validated['phone_number'])),
            'willing_to_mentor' => (bool) ($validated['willing_to_mentor'] ?? false),
            'willing_to_refer' => (bool) ($validated['willing_to_refer'] ?? false),
        ]);

        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(function (User $admin) use ($submission): void {
                $this->notificationService->createInAppNotification(
                    user: $admin,
                    title: 'Alumni Outreach Submission',
                    message: sprintf('%s submitted the alumni outreach form.', $submission->full_name),
                    type: 'info'
                );
            });

        // Confirmation email to the alumni: at most one per address per 10 minutes (SEC-010), so the public form
        // cannot be used to flood someone's inbox. The submission itself is always kept.
        if (MailCooldown::attempt('alumni-confirmation', (string) $submission->email)) {
            try {
                Mail::to($submission->email)->send(new AlumniOutreachConfirmationMail($submission));
            } catch (\Throwable) {
                // Silently fail — do not block the success response if mail transport fails
            }
        }

        return response()->json([
            'message' => 'Thank you for your submission. Our CDC team will reach out soon.',
            'submission' => $submission,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = AlumniOutreachSubmission::query()->latest();

        if (!empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(function ($inner) use ($term): void {
                $inner->where('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('current_organization', 'like', "%{$term}%")
                    ->orWhere('programme', 'like', "%{$term}%")
                    ->orWhere('department', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'submissions' => $query->get(),
        ]);
    }
}
