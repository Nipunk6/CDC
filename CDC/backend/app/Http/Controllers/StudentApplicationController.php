<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationSubmittedMail;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Services\EligibilityService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use App\Support\PostingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentApplicationController extends Controller
{
    public function __construct(
        private readonly EligibilityService $eligibility,
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $applications = $student->applications()
            ->with(['jobPosting.postable.company:id,name,logo_path', 'jobPosting.placementCycle:id,name,type,status', 'jobPosting.rounds', 'resume:id,label,status,slot', 'offer'])
            ->orderByDesc('applied_at')
            ->get();

        return response()->json([
            'applications' => $applications->map(function (Application $application) use ($student) {
                $posting = $application->jobPosting;

                return PostingPresenter::application($application) + [
                    'posting' => PostingPresenter::card($posting, $student->programme),
                    'resume' => $application->resume?->only(['id', 'label', 'status', 'slot']),
                    'trail' => PostingPresenter::trail($posting, $application),
                    'offer' => $this->offerFor($application),
                ];
            }),
        ]);
    }

    /**
     * Apply, or re-apply after withdrawing (same row, spec Q3.6).
     */
    public function apply(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $student = $this->student($request);
        $validated = $request->validate([
            'resume_id' => ['required', 'integer'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
        ]);

        if (! $this->isVisible($student, $jobPosting)) {
            return response()->json(['message' => 'Posting not found.'], 404);
        }

        if (! $jobPosting->acceptsApplications()) {
            return response()->json(['message' => $this->closedMessage($jobPosting)], 422);
        }

        $check = $this->eligibility->check($student, $jobPosting);
        if (! $check['eligible']) {
            return response()->json([
                'message' => 'You are not eligible for this posting.',
                'reasons' => $check['reasons'],
            ], 422);
        }

        $resume = $this->ownResume($student, (int) $validated['resume_id']);
        if (! $resume) {
            return response()->json(['message' => 'Choose one of your own resumes.', 'errors' => ['resume_id' => ['Choose one of your own resumes.']]], 422);
        }

        $answers = $this->validatedAnswers($jobPosting, $validated['answers'] ?? []);
        if (is_string($answers)) {
            return response()->json(['message' => $answers, 'errors' => ['answers' => [$answers]]], 422);
        }

        $result = DB::transaction(function () use ($student, $jobPosting, $resume, $answers) {
            $existing = Application::query()
                ->where('job_posting_id', $jobPosting->id)
                ->where('student_profile_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'applied') {
                return null;
            }

            $attributes = [
                'resume_id' => $resume->id,
                'status' => 'applied',
                'used_unverified_resume' => $resume->status !== 'approved',
                'answers' => $answers,
                'applied_at' => now(),
                'withdrawn_at' => null,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return Application::create($attributes + [
                'job_posting_id' => $jobPosting->id,
                'student_profile_id' => $student->id,
                'placed_elsewhere_flag' => false,
            ]);
        });

        if (! $result) {
            return response()->json(['message' => 'You have already applied. You can change your resume or answers instead.'], 409);
        }

        $this->confirm($student, $jobPosting, $result, $resume);

        return response()->json([
            'message' => $result->used_unverified_resume
                ? 'Application submitted. Your resume is not verified yet — get it verified by the CDC as soon as possible.'
                : 'Application submitted.',
            'application' => PostingPresenter::application($result),
        ], 201);
    }

    /**
     * Change the attached resume and/or answers until the deadline.
     */
    public function update(Request $request, Application $application): JsonResponse
    {
        $student = $this->student($request);
        $this->assertOwner($student, $application);

        $validated = $request->validate([
            'resume_id' => ['sometimes', 'integer'],
            'answers' => ['sometimes', 'nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
        ]);

        $posting = $application->jobPosting;

        if (! $posting->acceptsApplications()) {
            return response()->json(['message' => $this->closedMessage($posting)], 422);
        }

        if ($application->status !== 'applied') {
            return response()->json(['message' => 'This application was withdrawn. Apply again to change it.'], 422);
        }

        $changes = [];

        if (array_key_exists('resume_id', $validated)) {
            $resume = $this->ownResume($student, (int) $validated['resume_id']);
            if (! $resume) {
                return response()->json(['message' => 'Choose one of your own resumes.'], 422);
            }
            $changes['resume_id'] = $resume->id;
            $changes['used_unverified_resume'] = $resume->status !== 'approved';
        }

        if (array_key_exists('answers', $validated)) {
            $answers = $this->validatedAnswers($posting, $validated['answers'] ?? []);
            if (is_string($answers)) {
                return response()->json(['message' => $answers], 422);
            }
            $changes['answers'] = $answers;
        }

        $application->update($changes);

        return response()->json([
            'message' => 'Application updated.',
            'application' => PostingPresenter::application($application->fresh()),
        ]);
    }

    public function withdraw(Request $request, Application $application): JsonResponse
    {
        $student = $this->student($request);
        $this->assertOwner($student, $application);

        if (! $application->jobPosting->acceptsApplications()) {
            return response()->json(['message' => $this->closedMessage($application->jobPosting)], 422);
        }

        if ($application->status === 'withdrawn') {
            return response()->json(['message' => 'This application is already withdrawn.'], 422);
        }

        $application->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

        return response()->json([
            'message' => 'Application withdrawn. You can apply again until the deadline.',
            'application' => PostingPresenter::application($application),
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * @param  array<int, array{question_id: int, answer: mixed}>  $input
     * @return list<array{question_id: int, answer: mixed}>|string normalised answers or an error message
     */
    private function validatedAnswers(JobPosting $posting, array $input): array|string
    {
        $byId = [];
        foreach ($input as $item) {
            $byId[(int) $item['question_id']] = $item['answer'] ?? null;
        }

        $answers = [];

        foreach ($posting->questions()->get() as $question) {
            $value = $byId[$question->id] ?? null;
            $label = mb_strimwidth($question->question, 0, 60, '…');

            if ($question->qtype === 'text') {
                $value = is_scalar($value) ? trim((string) $value) : '';
                if (mb_strlen($value) > 2000) {
                    return "Your answer to \"{$label}\" is too long (2000 characters max).";
                }
                $filled = $value !== '';
            } elseif ($question->qtype === 'mcq_single') {
                $value = is_scalar($value) ? (string) $value : '';
                if ($value !== '' && ! in_array($value, $question->options ?? [], true)) {
                    return "Pick one of the listed options for \"{$label}\".";
                }
                $filled = $value !== '';
            } else {
                $value = is_array($value) ? array_values(array_unique(array_map('strval', array_filter($value, 'is_scalar')))) : [];
                if (array_diff($value, $question->options ?? []) !== []) {
                    return "Pick only listed options for \"{$label}\".";
                }
                $filled = $value !== [];
            }

            if ($question->required && ! $filled) {
                return "Answer the required question \"{$label}\".";
            }

            if ($filled) {
                $answers[] = ['question_id' => $question->id, 'answer' => $value];
            }
        }

        return $answers;
    }

    private function confirm(StudentProfile $student, JobPosting $posting, Application $application, Resume $resume): void
    {
        $company = $posting->company()?->name ?? 'the company';
        $title = $posting->title();
        $deadline = $posting->application_deadline->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST';

        $this->notifications->createInAppNotification(
            $student->user,
            'Application submitted',
            "You applied to {$title} at {$company} with \"{$resume->label}\".",
            $application->used_unverified_resume ? 'warning' : 'success'
        );

        $mailable = new ApplicationSubmittedMail(
            $student->full_name,
            $company,
            $title,
            $resume->label,
            $application->used_unverified_resume,
            $deadline,
            rtrim((string) config('app.frontend_url'), '/').'/student/applications'
        );

        $this->mail->send($student->user, $mailable, $mailable->envelope()->subject, 'emails.application-submitted');
    }

    /** @return array<string, mixed>|null */
    private function offerFor(Application $application): ?array
    {
        $offer = $application->offer;

        return $offer ? $offer->only(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency', 'announced_at']) + [
            'label' => \App\Models\Offer::LABELS[$offer->offer_type] ?? $offer->offer_type,
        ] : null;
    }

    private function closedMessage(JobPosting $posting): string
    {
        if ($posting->status !== 'open') {
            return 'This posting is no longer accepting applications.';
        }

        return $posting->deadlinePassed()
            ? 'The application deadline has passed.'
            : 'This placement cycle is closed, so it no longer accepts applications.';
    }

    private function ownResume(StudentProfile $student, int $resumeId): ?Resume
    {
        return $student->resumes()->whereKey($resumeId)->first();
    }

    private function isVisible(StudentProfile $student, JobPosting $posting): bool
    {
        return $posting->status !== 'cancelled'
            && $student->cycleEnrollments()->where('placement_cycle_id', $posting->placement_cycle_id)->exists();
    }

    private function assertOwner(StudentProfile $student, Application $application): void
    {
        abort_if($application->student_profile_id !== $student->id, 404, 'Application not found.');
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile?->load(['user', 'cycleEnrollments']);
        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }
}
