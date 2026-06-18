<?php

namespace Tests\Feature;

use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_mark_notifications_read(): void
    {
        $user = User::factory()->create([
            'role' => 'company',
            'company_id' => null,
        ]);

        $notification = PortalNotification::create([
            'user_id' => $user->id,
            'title' => 'Test',
            'message' => 'Test notification',
            'type' => 'info',
        ]);

        PortalNotification::create([
            'user_id' => $user->id,
            'title' => 'Unread 2',
            'message' => 'Second notification',
            'type' => 'warning',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/notifications');

        $response->assertOk();
        $response->assertJsonCount(2, 'notifications');
        $response->assertJsonPath('unread_count', 2);

        $markReadResponse = $this->patchJson("/api/auth/notifications/{$notification->id}/read");

        $markReadResponse->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);

        $markAllResponse = $this->patchJson('/api/auth/notifications/read-all');

        $markAllResponse->assertOk();
        $this->assertSame(0, PortalNotification::where('user_id', $user->id)->whereNull('read_at')->count());
    }
}
