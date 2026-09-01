<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    public function test_authenticated_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        Notification::create([
            'user_id' => $user->id,
            'type' => 'test_type',
            'title' => 'Test Notification',
            'body' => 'This is a test notification.',
            'data' => ['key' => 'value'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_other_user_notifications(): void
    {
        $user1 = User::factory()->create(['password' => Hash::make('password')]);
        $user2 = User::factory()->create(['password' => Hash::make('password')]);

        Notification::create([
            'user_id' => $user1->id,
            'type' => 'private',
            'title' => 'Private Notification',
            'body' => 'This should not be visible to user2.',
        ]);
        Notification::create([
            'user_id' => $user2->id,
            'type' => 'public',
            'title' => 'Public Notification',
            'body' => 'This should be visible to user2.',
        ]);

        $response = $this->actingAs($user2, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_user_cannot_view_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    public function test_notification_pagination(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 25; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'test',
                'title' => "Notification $i",
                'body' => "Body $i",
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_mark_notification_as_read(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai telah dibaca.',
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_user_notification_as_read(): void
    {
        $user1 = User::factory()->create(['password' => Hash::make('password')]);
        $user2 = User::factory()->create(['password' => Hash::make('password')]);
        $notification = Notification::create([
            'user_id' => $user1->id,
            'type' => 'test',
            'title' => 'Private',
            'body' => 'Private body',
        ]);

        $response = $this->actingAs($user2, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    public function test_mark_nonexistent_notification_as_read_returns_404(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/notifications/99999/read');

        $response->assertStatus(404);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'test',
                'title' => "Notification $i",
                'body' => "Body $i",
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Semua notifikasi berhasil ditandai telah dibaca.',
            ]);

        $this->assertEquals(5, $user->notifications()->whereNotNull('read_at')->count());
    }

    public function test_notification_response_structure(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'activity_created',
            'title' => 'Aktivitas RT Baru',
            'body' => 'Kerja bakti hari Minggu',
            'data' => ['activity_id' => 123],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);

        $data = $response->json('data.0');
        $this->assertEquals($notification->id, $data['id']);
        $this->assertEquals('activity_created', $data['type']);
        $this->assertEquals('Aktivitas RT Baru', $data['title']);
        $this->assertEquals('Kerja bakti hari Minggu', $data['body']);
        $this->assertEquals(['activity_id' => 123], $data['data']);
        $this->assertNull($data['read_at']);
    }
}
