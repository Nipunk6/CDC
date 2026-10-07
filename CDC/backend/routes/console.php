<?php

use App\Models\PlacementCycle;
use App\Services\AuditService;
use App\Services\BlockingPolicy;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// One-off after the owner's blocking rule change (QA F-004): offer blocks created earlier covered only the offer's
// own cycle. This extends every active offer block to the other open cycles the student is enrolled in, exactly as
// a new enrolment would. Idempotent; each new row is audited.
Artisan::command('placement:extend-offer-blocks', function (BlockingPolicy $policy, AuditService $audit) {
    $created = 0;
    $flagged = 0;
    foreach (PlacementCycle::query()->where('status', 'open')->get() as $cycle) {
        $studentIds = $cycle->enrollments()->pluck('student_profile_id')->all();
        foreach ($policy->carryForward($cycle, $studentIds, null) as $block) {
            $audit->logAs(null, null, 'block.create', $block, null, $block->only(['student_profile_id', 'placement_cycle_id', 'scope', 'reason', 'offer_id']) + ['via' => 'extend-offer-blocks']);
            $flagged += $policy->flagLiveApplications($block->student_profile_id, $cycle->id, $block->scope);
            $created++;
        }
    }
    $this->info("{$created} block(s) added, {$flagged} live application(s) flagged as placed elsewhere.");
})->purpose('Extend existing offer blocks to every open cycle the student is enrolled in (QA F-004)');

// "Schedule For Later" (Superset parity S6.2): open job profiles whose opening time has come and send E2 once.
// Runs every minute from the scheduler (`php artisan schedule:work` locally, cron `schedule:run` in production).
Artisan::command('placement:open-scheduled', function (AuditService $audit, \App\Services\MailDispatchService $mail) {
    $opened = 0;
    \App\Models\JobPosting::query()
        ->whereNotNull('scheduled_open_at')
        ->where('scheduled_open_at', '<=', now())
        ->where('status', 'open')
        ->orderBy('scheduled_open_at')
        ->get()
        ->each(function (\App\Models\JobPosting $posting) use ($audit, $mail, &$opened): void {
            // Claim it atomically, so two overlapping runs can never open (and mail) the same job profile twice.
            $claimed = \App\Models\JobPosting::query()
                ->whereKey($posting->id)
                ->whereNotNull('scheduled_open_at')
                ->where('scheduled_open_at', '<=', now())
                ->update(['scheduled_open_at' => null]);
            if ($claimed !== 1) {
                return;
            }

            $audit->logAs(null, null, 'posting.scheduled_open', $posting, ['scheduled_open_at' => $posting->scheduled_open_at?->toIso8601String()], ['scheduled_open_at' => null]);
            $mail->mode() === 'sync'
                ? \App\Jobs\SendPostingFloatedMails::dispatchSync($posting->id)
                : \App\Jobs\SendPostingFloatedMails::dispatch($posting->id);
            $opened++;
        });
    $this->info("{$opened} scheduled job profile(s) opened.");
})->purpose('Open job profiles scheduled for later and notify eligible students (S6.2)');

\Illuminate\Support\Facades\Schedule::command('placement:open-scheduled')->everyMinute()->withoutOverlapping();
