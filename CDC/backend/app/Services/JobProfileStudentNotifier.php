<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\PortalNotification;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Owner request 2026-10-19: the student notification center is personal. This puts job profile events there for the
 * students they concern: a new job profile they are eligible for (once per job profile, with the same expandable
 * details as its notice), an admin announcement to selected students, deadline changes, reopening, closing,
 * cancelling and stage reschedules for eligible students and applicants. Results, offers and stage emails are added
 * where they are sent (PipelineService, AdminResultController, AdminStageMessageController).
 */
class JobProfileStudentNotifier
{
    public function __construct(
        private readonly PortalNotificationService $notifications,
        private readonly EligibilityService $eligibility,
        private readonly JobProfileAnnouncementService $announcements,
        private readonly JobProfileNoticeService $notices
    ) {
    }

    /** The job profile opened for applications: every eligible student, plus the admin's selected audiences. */
    public function opened(JobPosting $posting): void
    {
        $this->newJob($posting, null);
        $this->announced($posting);
    }

    /**
     * "New job profile you're eligible for", once per student and job profile.
     *
     * @param  list<int>|null  $studentProfileIds  limit to these students (newly eligible); null = everyone eligible
     */
    public function newJob(JobPosting $posting, ?array $studentProfileIds): int
    {
        if (! $posting->acceptsApplications()) {
            return 0;
        }
        $query = $this->eligibility->eligibleStudentsQuery($posting)
            ->whereNotIn('student_profiles.id', $this->applicants($posting)->select('student_profile_id'));
        if ($studentProfileIds !== null) {
            $query->whereIn('student_profiles.id', $studentProfileIds ?: [0]);
        }
        [$company, $title] = $this->names($posting);

        return $this->notifications->notifyStudents(
            $query,
            "You're eligible: {$company} — {$title}",
            "{$company}'s {$title} is open for applications. Apply by {$this->when($posting->application_deadline)}.",
            'success',
            $this->context($posting, 'new_job', withDetails: true),
            once: true
        );
    }

    /**
     * The admin picked branches or courses for the notice or the email: those students get it here too (an "eligible"
     * audience is already covered by newJob, and "all students" stays on the general Notices page).
     */
    public function announced(JobPosting $posting): int
    {
        if (! $posting->acceptsApplications()) {
            return 0;
        }
        $settings = $this->announcements->settings($posting);
        $audiences = [];
        foreach (['notice', 'email'] as $channel) {
            $type = $settings[$channel]['audience']['type'] ?? 'eligible';
            if ($settings[$channel]['enabled'] && in_array($type, ['branches', 'programmes'], true)) {
                $audiences[] = $settings[$channel]['audience'];
            }
        }
        if ($audiences === []) {
            return 0;
        }

        $query = StudentProfile::query()->where(function (Builder $any) use ($posting, $audiences): void {
            foreach ($audiences as $audience) {
                $any->orWhereIn('student_profiles.id', $this->announcements->query($posting, $audience)->select('student_profiles.id'));
            }
        })->whereNotIn('student_profiles.user_id', PortalNotification::query()
            ->where('job_posting_id', $posting->id)->where('category', 'new_job')->select('user_id')); // already told as eligible
        [$company, $title] = $this->names($posting);
        $message = $settings['notice']['message'] ?: $settings['email']['message']
            ?: "Applications are open until {$this->when($posting->application_deadline)}. Open the job profile to check whether you are eligible.";

        return $this->notifications->notifyStudents(
            $query,
            $settings['notice']['title'] ?: $this->notices->defaultTitle($posting),
            $message,
            'info',
            $this->context($posting, 'announcement', withDetails: true),
            once: true
        );
    }

    public function deadlineChanged(JobPosting $posting, ?CarbonInterface $before): int
    {
        if (! $posting->acceptsApplications() || ! $posting->application_deadline || $before?->equalTo($posting->application_deadline)) {
            return 0;
        }
        [$company, $title] = $this->names($posting);
        $was = $before ? " (was {$this->when($before)})" : '';

        return $this->notifications->notifyStudents(
            $this->eligibleOrApplied($posting),
            "Deadline changed: {$company} — {$title}",
            "Applications now close on {$this->when($posting->application_deadline)}{$was}.",
            'warning',
            $this->context($posting, 'deadline')
        );
    }

    public function reopened(JobPosting $posting): int
    {
        if (! $posting->acceptsApplications()) {
            return 0;
        }
        [$company, $title] = $this->names($posting);

        return $this->notifications->notifyStudents(
            $this->eligibleOrApplied($posting),
            "Applications reopened: {$company} — {$title}",
            "You can apply, edit or withdraw until {$this->when($posting->application_deadline)}.",
            'info',
            $this->context($posting, 'status')
        );
    }

    public function closed(JobPosting $posting): int
    {
        [$company, $title] = $this->names($posting);

        return $this->notifications->notifyStudents(
            $this->applied($posting),
            "Applications closed: {$company} — {$title}",
            'Applications are closed and the selection process has started. Results will appear here.',
            'info',
            $this->context($posting, 'status', link: '/student/applications')
        );
    }

    public function cancelled(JobPosting $posting): int
    {
        [$company, $title] = $this->names($posting);

        return $this->notifications->notifyStudents(
            $this->applied($posting),
            "Cancelled: {$company} — {$title}",
            "The CDC has cancelled {$company}'s {$title}. Your application is no longer active.",
            'warning',
            $this->context($posting, 'status', link: '/student/applications')
        );
    }

    /** A stage's date, time or venue changed: tell its applicants. */
    public function roundRescheduled(JobPosting $posting, PostingRound $round): int
    {
        if (! $round->scheduled_at && ! $round->venue) {
            return 0;
        }
        [$company, $title] = $this->names($posting);
        $parts = array_filter([$round->scheduled_at ? $this->when($round->scheduled_at) : null, $round->venue]);

        return $this->notifications->notifyStudents(
            $this->applied($posting),
            "{$round->name} scheduled: {$company} — {$title}",
            "{$round->name} is now on ".implode(' at ', $parts).'.',
            'info',
            $this->context($posting, 'schedule', link: '/student/applications')
        );
    }

    private function eligibleOrApplied(JobPosting $posting): Builder
    {
        $eligible = $this->eligibility->eligibleStudentsQuery($posting)->select('student_profiles.id');

        return StudentProfile::query()->where(fn (Builder $q) => $q
            ->whereIn('student_profiles.id', $eligible)
            ->orWhereIn('student_profiles.id', $this->applicants($posting)->select('student_profile_id')));
    }

    private function applied(JobPosting $posting): Builder
    {
        return StudentProfile::query()->whereIn('student_profiles.id', $this->applicants($posting)->select('student_profile_id'));
    }

    private function applicants(JobPosting $posting): Builder
    {
        return Application::query()->where('job_posting_id', $posting->id)->where('status', 'applied');
    }

    /** @return array{0: string, 1: string} */
    private function names(JobPosting $posting): array
    {
        $posting->loadMissing('postable.company');

        return [$posting->company()?->name ?? 'A company', $posting->title()];
    }

    /** @return array<string, mixed> */
    private function context(JobPosting $posting, string $category, bool $withDetails = false, ?string $link = null): array
    {
        return [
            'category' => $category,
            'job_posting_id' => $posting->id,
            'link' => $link ?? "/student/postings/{$posting->id}",
            'details' => $withDetails ? $this->notices->details($posting) : null,
        ];
    }

    private function when(CarbonInterface $at): string
    {
        return $at->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST';
    }
}
