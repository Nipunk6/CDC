<?php

namespace App\Jobs;

use App\Mail\PostingFloatedMail;
use App\Models\JobPosting;
use App\Models\StudentProfile;
use App\Services\EligibilityService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E2 to every eligible student of a freshly floated posting: one BCC message per batch (spec B6, D89).
 */
class SendPostingFloatedMails implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(public int $jobPostingId)
    {
    }

    public function handle(EligibilityService $eligibility, MailDispatchService $mail, PortalNotificationService $notifications): void
    {
        $posting = JobPosting::query()->with(['postable.company', 'placementCycle'])->find($this->jobPostingId);

        if (! $posting || $posting->status === 'cancelled') {
            return;
        }

        $company = $posting->company()?->name ?? 'A company';
        $title = $posting->title();
        $typeLabel = $posting->postingType() === 'internship' ? 'internship' : 'full-time';
        $deadline = $posting->application_deadline->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST';
        $url = rtrim((string) config('app.frontend_url'), '/')."/student/postings/{$posting->id}";

        $mailable = new PostingFloatedMail($company, $title, $typeLabel, $deadline, $url);

        $eligibility->eligibleStudentsQuery($posting)
            ->with('user')
            ->chunkById($mail->batchSize(), function ($students) use ($mail, $notifications, $mailable, $company, $title, $typeLabel, $deadline): void {
                /** @var StudentProfile $student */
                foreach ($students as $student) {
                    $notifications->createInAppNotification(
                        $student->user,
                        "New opening: {$company}",
                        "{$title} ({$typeLabel}) is open. Apply by {$deadline}.",
                        'info'
                    );
                }

                $mail->sendBulk($students->pluck('user'), $mailable, $mailable->envelope()->subject, 'emails.posting-floated');
            });
    }
}
