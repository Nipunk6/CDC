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
