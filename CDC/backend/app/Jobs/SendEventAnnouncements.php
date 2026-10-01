<?php

namespace App\Jobs;

use App\Mail\EventAnnouncedMail;
use App\Models\CampusEvent;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E6 to an event's audience: one BCC message per batch (spec B6, D89).
 */
class SendEventAnnouncements implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public const TYPE_LABELS = ['ppt' => 'Pre-Placement Talk', 'workshop' => 'Workshop', 'webinar' => 'Webinar', 'other' => 'Event'];

    public function __construct(public int $eventId)
    {
    }

    public function handle(MailDispatchService $mail, PortalNotificationService $notifications): void
    {
        $event = CampusEvent::query()->with('company:id,name')->find($this->eventId);
        if (! $event || ! $event->published_at) {
            return;
        }

        $label = self::TYPE_LABELS[$event->event_type] ?? 'Event';
        $when = $event->starts_at->timezone('Asia/Kolkata')->format('D, d M Y · h:i A').' IST';
        $description = $event->description ? trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $event->description)))) : null;

        $mailable = new EventAnnouncedMail($event->title, $label, $when, $event->company?->name, $event->venue, $event->meeting_link, $description);

        $event->audienceQuery()->with('user')->chunkById($mail->batchSize(), function ($students) use ($event, $mail, $notifications, $mailable, $label, $when): void {
            foreach ($students as $student) {
                $notifications->createInAppNotification($student->user, "{$label}: {$event->title}", "{$when}".($event->venue ? " · {$event->venue}" : ''), 'info');
            }

            $mail->sendBulk($students->pluck('user'), $mailable, $mailable->envelope()->subject, 'emails.event-announced');
        });
    }
}
