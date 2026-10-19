<?php

namespace App\Jobs;

use App\Mail\BroadcastMail;
use App\Mail\PostingFloatedMail;
use App\Models\JobPosting;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\EligibilityService;
use App\Services\JobProfileAnnouncementService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use App\Services\PostingEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E2 for a freshly floated posting: one BCC message per batch (spec B6, D89). The admin picks whether it is sent, to
 * whom (eligible students by default, or all students, branches or courses) and optionally its subject and message
 * (owner request 2026-10-19, JobProfileAnnouncementService). After an eligibility change it is run for the newly
 * eligible students only (D103). Either way a student is told about a drive once: every run records whom it mailed as
 * a `posting.notify` audit row and skips earlier recipients.
 */
class SendPostingFloatedMails implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    /**
     * @param  list<int>|null  $studentProfileIds  limit the run to these students (null = every eligible student)
     */
    public function __construct(public int $jobPostingId, public ?array $studentProfileIds = null)
    {
    }

    public function handle(
        EligibilityService $eligibility,
        MailDispatchService $mail,
        PortalNotificationService $notifications,
        PostingEligibilityService $postingEligibility,
        AuditService $audit,
        JobProfileAnnouncementService $announcements
    ): void
    {
        $posting = JobPosting::query()->with(['postable.company', 'placementCycle'])->find($this->jobPostingId);

        if (! $posting || $posting->status === 'cancelled') {
            return;
        }

        $settings = $announcements->settings($posting)['email'];
        if (! $settings['enabled']) {
            return;
        }
        $onlyEligible = ($settings['audience']['type'] ?? 'eligible') === 'eligible';

        $company = $posting->company()?->name ?? 'A company';
        $title = $posting->title();
        $typeLabel = $posting->postingType() === 'internship' ? 'internship' : 'full-time';
        $deadline = $posting->application_deadline->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST';
        $url = rtrim((string) config('app.frontend_url'), '/')."/student/postings/{$posting->id}";

        // The standard E2 text says "you are eligible", so a wider audience or the admin's own words use the broadcast layout.
        $custom = ! $onlyEligible || $settings['subject'] || $settings['message'];
        $mailable = $custom
            ? new BroadcastMail(
                $settings['subject'] ?: "New opening: {$company} — {$title}",
                sprintf("%s's %s is open for applications", $company, $title),
                array_values(array_filter([
                    ...BroadcastMail::paragraphs($settings['message'] ? nl2br(e($settings['message'])) : null),
                    "Company: {$company}",
                    'Role: '.$title.' ('.$typeLabel.')',
                    "Apply by: {$deadline}",
                    $onlyEligible ? null : 'Open the job profile on the portal to check whether you are eligible.',
                ])),
                $url,
                'View & Apply'
            )
            : new PostingFloatedMail($company, $title, $typeLabel, $deadline, $url);
        $template = $custom ? 'emails.broadcast' : 'emails.posting-floated';

        $query = $announcements->query($posting, $settings['audience'])->with('user');
        if ($this->studentProfileIds !== null) {
            $query->whereIn('student_profiles.id', $this->studentProfileIds ?: [0]);
        }
        $already = $postingEligibility->notifiedIds($posting);
        if ($already !== []) {
            $query->whereNotIn('student_profiles.id', $already);
        }

        $sent = [];
        try {
            $query->chunkById($mail->batchSize(), function ($students) use ($mail, $mailable, $template, $posting, &$sent): void {
                $mail->sendBulk($students->pluck('user'), $mailable, $mailable->envelope()->subject, $template, ['job_posting_id' => $posting->id, 'kind' => 'opening']);
                array_push($sent, ...$students->pluck('id')->map(fn ($id) => (int) $id)->all());
            }, 'student_profiles.id', 'id');
        } finally {
            if ($sent !== []) {
                $audit->logAs(null, null, 'posting.notify', $posting, null, [
                    'reason' => $this->studentProfileIds === null ? 'float' : 'eligibility_update',
                    'count' => count($sent),
                    'student_profile_ids' => $sent,
                ]);
            }
        }
    }
}
