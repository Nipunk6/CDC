<?php

namespace App\Services;

use App\Models\CalendarEntry;
use App\Models\CalendarOverride;
use App\Models\CampusEvent;
use App\Models\JobPosting;
use App\Models\PostingRound;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendar reminders (owner request 2026-10-19): an in-app notification `remind_minutes` before an own entry or an
 * automatic item the user set a reminder on. Done or hidden items are not reminded; each reminder is sent once.
 * Run every minute by `calendar:send-reminders`.
 */
class CalendarReminderService
{
    public function __construct(private PortalNotificationService $notifications) {}

    public function sendDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $sent = 0;

        CalendarEntry::query()->with('user')
            ->whereNotNull('remind_minutes')->whereNull('reminded_at')->whereNull('done_at')
            // Nothing long past; the longest reminder is four weeks ahead.
            ->whereBetween('starts_at', [$now->copy()->subHour(), $now->copy()->addDays(29)])
            ->get()
            ->each(function (CalendarEntry $e) use ($now, &$sent) {
                if ($e->starts_at->copy()->subMinutes($e->remind_minutes)->lte($now) && $this->claim(CalendarEntry::class, $e->id)) {
                    $this->notify($e->user, $e->title, $e->starts_at);
                    $sent++;
                }
            });

        CalendarOverride::query()->with('user')
            ->whereNotNull('remind_minutes')->whereNull('reminded_at')->whereNull('done_at')->where('hidden', false)
            ->get()
            ->each(function (CalendarOverride $o) use ($now, &$sent) {
                [$at, $title] = $this->resolve($o);
                if (! $at || $at->lt($now->copy()->subHour()) || $at->copy()->subMinutes($o->remind_minutes)->gt($now)) {
                    return;
                }
                if ($this->claim(CalendarOverride::class, $o->id)) {
                    $this->notify($o->user, $o->title ?? $title ?? $o->label ?? 'Calendar item', $at);
                    $sent++;
                }
            });

        return $sent;
    }

    /** Claimed atomically, so overlapping runs never send the same reminder twice. */
    private function claim(string $model, int $id): bool
    {
        return $model::query()->whereKey($id)->whereNull('reminded_at')->update(['reminded_at' => now()]) === 1;
    }

    private function notify($user, string $title, CarbonInterface $at): void
    {
        if (! $user) {
            return;
        }
        $when = $at->copy()->timezone('Asia/Kolkata')->format('D j M, h:i A');
        $this->notifications->createInAppNotification($user, 'Reminder: '.$title, "Coming up on your calendar at {$when} IST.");
    }

    /**
     * The item's time and title as the user sees them: their own moved time, else the placement data's current one.
     *
     * @return array{0: ?CarbonInterface, 1: ?string}
     */
    private function resolve(CalendarOverride $o): array
    {
        [$type, $id] = explode(':', $o->item_key) + [null, null];
        [$at, $title] = match ($type) {
            'event' => ($e = CampusEvent::find($id)) ? [$e->starts_at, $e->title] : [null, null],
            'deadline' => ($p = JobPosting::find($id)) ? [$p->application_deadline, 'Apply by: '.$p->title()] : [null, null],
            'round' => ($r = PostingRound::with('jobPosting')->find($id)) ? [$r->scheduled_at, $r->name.' — '.$r->jobPosting?->title()] : [null, null],
            'visit' => ($p = JobPosting::find($id)) && $p->visit_date ? [Carbon::parse($p->visit_date->toDateString().' 10:00', 'Asia/Kolkata')->utc(), 'Visit: '.$p->title()] : [null, null],
            default => [null, null],
        };

        return [$o->starts_at ?? $at, $title];
    }
}
