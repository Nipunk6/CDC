<?php

namespace App\Http\Controllers;

use App\Mail\RecruiterEmailVerificationMail;
use App\Mail\NewCompanyRegistrationMail;
use App\Models\Company;
use App\Models\EmailLog;
use App\Models\RecruiterEmailVerification;
use App\Models\User;
use App\Services\PortalNotificationService;
use App\Support\MailCooldown;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CompanyAuthController extends Controller
{
    public function __construct(private readonly PortalNotificationService $notificationService)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'hr_email' => $this->normalizeEmail((string) $request->input('hr_email', '')),
        ]);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[^\s]*$/i'],
            'postal_address' => ['nullable', 'string', 'max:1000'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'sector' => ['required', 'string', 'max:255'],
            'company_logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'recruiter_name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\s'.-]+$/u"],
            'recruiter_designation' => ['required', 'string', 'max:255'],
            'hr_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:companies,hr_email'],
            'hr_phone' => ['required', 'string', 'max:30'],
            'hr_alt_phone' => ['nullable', 'string', 'max:30'],

            'head_name' => ['required', 'string', 'max:255'],
            'head_designation' => ['required', 'string', 'max:255'],
            'head_email' => ['required', 'string', 'email', 'max:255'],
            'head_mobile' => ['required', 'string', 'max:30'],
            'head_landline' => ['nullable', 'string', 'max:30'],

            'poc1_name' => ['required', 'string', 'max:255'],
            'poc1_designation' => ['required', 'string', 'max:255'],
            'poc1_email' => ['required', 'string', 'email', 'max:255'],
            'poc1_mobile' => ['required', 'string', 'max:30'],
            'poc1_landline' => ['nullable', 'string', 'max:30'],

            'poc2_name' => ['nullable', 'string', 'max:255'],
            'poc2_designation' => ['nullable', 'string', 'max:255'],
            'poc2_email' => ['nullable', 'string', 'email', 'max:255'],
            'poc2_mobile' => ['nullable', 'string', 'max:30'],
            'poc2_landline' => ['nullable', 'string', 'max:30'],

            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ], [
            'hr_email.unique' => 'The email has already been registered.',
        ]);

        $normalizedHrEmail = $this->normalizeEmail($validated['hr_email']);

        if (! $this->isRecruiterEmailVerified($normalizedHrEmail)) {
            return response()->json([
                'message' => 'Please verify the recruiter email before completing registration.',
            ], 422);
        }

        $validated['hr_email'] = $normalizedHrEmail;

        $logoPath = $request->file('company_logo')?->store('company-logos', 'public');

        if ($logoPath === null) {
            return response()->json([
                'message' => 'Unable to upload company logo.',
            ], 422);
        }

        $result = DB::transaction(function () use ($validated, $logoPath) {
            $company = Company::create([
                'name' => $validated['company_name'],
                'industry' => $validated['sector'],
                'sector' => $validated['sector'],
                'website' => $validated['website'],
                'postal_address' => $validated['postal_address'] ?? null,
                'employee_count' => $validated['employee_count'] ?? null,
                'logo_path' => $logoPath,
                'hr_name' => $validated['recruiter_name'],
                'hr_designation' => $validated['recruiter_designation'],
                'hr_email' => $validated['hr_email'],
                'hr_phone' => $validated['hr_phone'],
                'hr_alt_phone' => $validated['hr_alt_phone'] ?? null,
                'head_talent_contact' => [
                    'name' => $validated['head_name'],
                    'designation' => $validated['head_designation'],
                    'email' => $validated['head_email'],
                    'mobile' => $validated['head_mobile'],
                    'landline' => $validated['head_landline'] ?? null,
                ],
                'primary_contact' => [
                    'name' => $validated['poc1_name'],
                    'designation' => $validated['poc1_designation'],
                    'email' => $validated['poc1_email'],
                    'mobile' => $validated['poc1_mobile'],
                    'landline' => $validated['poc1_landline'] ?? null,
                ],
                'secondary_contact' => [
                    'name' => $validated['poc2_name'] ?? null,
                    'designation' => $validated['poc2_designation'] ?? null,
                    'email' => $validated['poc2_email'] ?? null,
                    'mobile' => $validated['poc2_mobile'] ?? null,
                    'landline' => $validated['poc2_landline'] ?? null,
                ],
            ]);

            $user = User::create([
                'name' => $validated['recruiter_name'],
                'email' => $validated['hr_email'],
                'password' => $validated['password'],
                'role' => 'company',
                'company_id' => $company->id,
            ]);

            $token = $user->createToken('company-auth-token')->plainTextToken;

            return compact('company', 'user', 'token');
        });

        // If transaction fails, cleanup any uploaded logo file.
        if (! isset($result['company']) && $logoPath !== null) {
            Storage::disk('public')->delete($logoPath);
        }

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notificationService->createInAppNotification(
                user: $admin,
                title: 'New Company Registration',
                message: sprintf('%s registered with HR contact %s.', $result['company']->name, $result['company']->hr_email),
                type: 'info'
            );

            $this->notificationService->sendLoggedEmail(
                user: $admin,
                mailable: new NewCompanyRegistrationMail(
                    companyName: $result['company']->name,
                    hrName: $result['company']->hr_name,
                    hrEmail: $result['company']->hr_email,
                ),
                subject: sprintf('New Company Registration: %s', $result['company']->name),
                template: 'new-company-registration'
            );
        }

        RecruiterEmailVerification::query()
            ->where('email', $normalizedHrEmail)
            ->delete();

        return response()->json([
            'message' => 'Company registered successfully.',
            'token' => $result['token'],
            'company' => $result['company'],
            'user' => $result['user'],
        ], 201);
    }

    public function sendRecruiterEmailVerificationLink(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail((string) $request->input('email', '')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', $this->recruiterEmailRule(), 'max:255', 'unique:users,email', 'unique:companies,hr_email'],
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'email.unique' => 'The email has already been registered.',
        ]);

        $email = $this->normalizeEmail($validated['email']);

        // SEC-010: one link per address per 10 minutes. Checked before the token is rotated, so a repeat request
        // cannot invalidate the link that was already sent.
        if (! MailCooldown::attempt('recruiter-verification', $email)) {
            $wait = max(1, MailCooldown::remaining('recruiter-verification', $email));

            return response()->json([
                'message' => sprintf(
                    'A verification link was sent to this address recently. Check your inbox (and spam folder), or request a new link in %d minute%s.',
                    (int) ceil($wait / 60),
                    (int) ceil($wait / 60) === 1 ? '' : 's'
                ),
            ], 429)->header('Retry-After', (string) $wait);
        }

        $name = trim((string) ($validated['name'] ?? 'Recruiter'));
        $ttlMinutes = max((int) env('COMPANY_RECRUITER_VERIFY_TTL_MINUTES', 30), 5);
        $expiresAt = now()->addMinutes($ttlMinutes);
        $token = Str::random(64);

        RecruiterEmailVerification::query()->updateOrCreate(
            ['email' => $email],
            [
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'verified_at' => null,
            ]
        );

        $apiOrigin = rtrim($request->getSchemeAndHttpHost(), '/');
        $verifyUrl = sprintf(
            '%s/api/auth/company/recruiter-email/verify?token=%s',
            $apiOrigin,
            urlencode($token)
        );

        try {
            Mail::to($email)->send(new RecruiterEmailVerificationMail(
                name: $name,
                verifyUrl: $verifyUrl,
                expiryMinutes: $ttlMinutes
            ));

            EmailLog::create([
                'recipient_email' => $email,
                'subject' => 'Verify Your Recruiter Email',
                'template' => 'recruiter-email-verification-link',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            MailCooldown::release('recruiter-verification', $email); // the send failed: let them try again now

            Log::error('Recruiter verification email delivery failed.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            EmailLog::create([
                'recipient_email' => $email,
                'subject' => 'Verify Your Recruiter Email',
                'template' => 'recruiter-email-verification-link',
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send verification link right now. Please try again.',
            ], 503);
        }

        return response()->json([
            'message' => 'A verification link has been sent to your email address.',
            'expires_in_minutes' => $ttlMinutes,
        ]);
    }

    public function verifyRecruiterEmail(Request $request)
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return $this->renderRecruiterVerificationResult(
                status: 'failed',
                message: 'Verification link is invalid.'
            );
        }

        $verification = RecruiterEmailVerification::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $verification) {
            return $this->renderRecruiterVerificationResult(
                status: 'failed',
                message: 'Verification link is invalid or already used.'
            );
        }

        if ($verification->expires_at->isPast()) {
            return $this->renderRecruiterVerificationResult(
                status: 'failed',
                message: 'Verification link has expired. Please request a new one.'
            );
        }

        if ($verification->verified_at === null) {
            $verification->update([
                'verified_at' => now(),
            ]);
        }

        return $this->renderRecruiterVerificationResult(
            status: 'success',
            message: 'Recruiter email verified successfully.',
            email: $verification->email
        );
    }

    public function recruiterEmailVerificationStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', $this->recruiterEmailRule(), 'max:255'],
        ]);

        $email = $this->normalizeEmail($validated['email']);

        return response()->json([
            'verified' => $this->isRecruiterEmailVerified($email),
            'email' => $email,
        ]);
    }

    private function isRecruiterEmailVerified(string $email): bool
    {
        $verification = RecruiterEmailVerification::query()
            ->where('email', $this->normalizeEmail($email))
            ->first();

        if (! $verification) {
            return false;
        }

        if ($verification->verified_at === null) {
            return false;
        }

        return $verification->expires_at->isFuture();
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function renderRecruiterVerificationResult(string $status, string $message, ?string $email = null)
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $query = [
            'verify_status' => $status,
            'verify_message' => $message,
        ];

        if ($email !== null) {
            $query['verified_email'] = $email;
        }

        $returnUrl = sprintf('%s/company/register?%s', $frontendUrl, http_build_query($query));

        return response()->view('recruiter-verification-result', [
            'status' => $status,
            'message' => $message,
            'returnUrl' => $returnUrl,
        ]);
    }

    /** `email:rfc,dns` normally; tests turn the DNS part off (config services.recruiter_email.dns_check). */
    private function recruiterEmailRule(): string
    {
        return config('services.recruiter_email.dns_check', true) ? 'email:rfc,dns' : 'email:rfc';
    }
}
