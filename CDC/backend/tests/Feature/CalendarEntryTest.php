<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_manages_only_its_own_entries(): void
    {
        foreach (['admin', 'student', 'company', 'mentor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Sanctum::actingAs($user);

            $id = $this->postJson('/api/calendar/entries', ['title' => "Call ($role)", 'starts_at' => '2026-10-20T09:00:00+05:30', 'note' => 'Prep'])
                ->assertCreated()
                ->assertJsonPath('item.type', 'personal')
                ->json('item.id');

            $this->getJson('/api/calendar/entries?month=2026-10')->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', "Call ($role)");
            $this->getJson('/api/calendar/entries?month=2026-11')->assertJsonCount(0, 'items');

            $this->patchJson("/api/calendar/entries/$id", ['title' => 'Moved', 'starts_at' => '2026-10-21T10:00:00+05:30'])->assertOk()->assertJsonPath('item.title', 'Moved');
        }

        $mine = CalendarEntry::firstWhere('title', 'Moved');
        $other = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($other);
        $this->getJson('/api/calendar/entries?month=2026-10')->assertJsonCount(0, 'items');
        $this->patchJson("/api/calendar/entries/{$mine->id}", ['title' => 'x', 'starts_at' => '2026-10-21T10:00:00+05:30'])->assertNotFound();
        $this->deleteJson("/api/calendar/entries/{$mine->id}")->assertNotFound();
        $this->postJson('/api/calendar/entries', ['title' => '', 'starts_at' => 'nope'])->assertStatus(422);

        Sanctum::actingAs($mine->user);
        $this->deleteJson("/api/calendar/entries/{$mine->id}")->assertOk();
        $this->assertModelMissing($mine);
    }

    public function test_overrides_change_only_the_users_own_view_of_automatic_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = \App\Models\CampusEvent::create(['title' => 'Nimbus PPT', 'event_type' => 'ppt', 'starts_at' => '2026-10-10T11:30:00Z', 'audience_type' => 'all', 'created_by' => $admin->id]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/calendar?month=2026-10')->assertJsonPath('items.0.key', "event:{$event->id}");

        $this->putJson('/api/calendar/overrides', ['key' => "event:{$event->id}", 'hidden' => false, 'title' => 'Nimbus talk', 'starts_at' => '2026-11-02T10:00:00+05:30', 'link' => '/admin/events'])
            ->assertOk()->assertJsonPath('override.title', 'Nimbus talk');
        $this->putJson('/api/calendar/overrides', ['key' => "event:{$event->id}", 'hidden' => true, 'title' => 'Nimbus talk', 'starts_at' => '2026-11-02T10:00:00+05:30'])->assertOk();
        $this->getJson('/api/calendar/overrides')->assertJsonCount(1, 'overrides')->assertJsonPath('overrides.0.hidden', true);
        $this->putJson('/api/calendar/overrides', ['key' => 'users:1', 'hidden' => true, 'title' => 'x', 'starts_at' => '2026-11-02'])->assertStatus(422);
        $this->putJson('/api/calendar/overrides', ['key' => 'event:1', 'hidden' => true, 'title' => 'x', 'starts_at' => '2026-11-02', 'link' => 'https://evil.test'])->assertStatus(422);

        // The event itself is unchanged, and nobody else sees the override.
        $this->assertSame('Nimbus PPT', $event->fresh()->title);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/calendar/overrides')->assertJsonCount(0, 'overrides');
        $this->deleteJson("/api/calendar/overrides?key=event:{$event->id}")->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson('/api/calendar/overrides')->assertJsonCount(1, 'overrides');
        $this->deleteJson("/api/calendar/overrides?key=event:{$event->id}")->assertOk();
        $this->getJson('/api/calendar/overrides')->assertJsonCount(0, 'overrides');
    }

    public function test_done_drag_colour_and_reminders(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);
        $soon = now()->addMinutes(20)->startOfMinute();

        $id = $this->postJson('/api/calendar/entries', ['title' => 'Mock interview', 'starts_at' => $soon->toIso8601String(), 'color' => 'teal', 'remind_minutes' => 30])
            ->assertCreated()->assertJsonPath('item.color', 'teal')->assertJsonPath('item.done', false)->json('item.id');
        $this->postJson('/api/calendar/entries', ['title' => 'x', 'starts_at' => $soon->toIso8601String(), 'color' => 'pink'])->assertStatus(422);

        // Mark done and undo; drag sends only the time.
        $this->patchJson("/api/calendar/entries/$id", ['done' => true])->assertOk()->assertJsonPath('item.done', true);
        $this->patchJson("/api/calendar/entries/$id", ['done' => false])->assertOk()->assertJsonPath('item.done', false);
        $this->patchJson("/api/calendar/entries/$id", ['starts_at' => $soon->toIso8601String()])->assertOk()->assertJsonPath('item.title', 'Mock interview');

        // An automatic item: done and a reminder, without freezing its title or time.
        $event = \App\Models\CampusEvent::create(['title' => 'Pre-placement talk', 'event_type' => 'ppt', 'starts_at' => now()->addMinutes(5), 'audience_type' => 'all', 'published_at' => now()]);
        $this->putJson('/api/calendar/overrides', ['key' => "event:{$event->id}", 'label' => 'Pre-placement talk', 'remind_minutes' => 10])->assertOk()
            ->assertJsonPath('override.title', null)->assertJsonPath('override.at', null)->assertJsonPath('override.done', false);

        $reminders = app(\App\Services\CalendarReminderService::class);
        $this->assertSame(2, $reminders->sendDue());
        $this->assertSame(0, $reminders->sendDue());
        $this->assertSame(2, \App\Models\PortalNotification::where('user_id', $student->id)->where('title', 'like', 'Reminder:%')->count());

        // Done items are not reminded; moving an entry re-arms its reminder.
        $this->patchJson("/api/calendar/entries/$id", ['done' => true, 'starts_at' => $soon->copy()->addMinute()->toIso8601String()])->assertOk();
        $this->assertSame(0, $reminders->sendDue());
        $this->putJson('/api/calendar/overrides', ['key' => "event:{$event->id}", 'done' => true])->assertOk()->assertJsonPath('override.done', true)->assertJsonPath('override.remind_minutes', 10);
    }
}
