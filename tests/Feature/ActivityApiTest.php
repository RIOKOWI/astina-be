<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\Activity;
use App\Models\ActivityRead;
use App\Models\DeviceToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    private function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function makeRtUser(): User
    {
        $role = Role::factory()->rt()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeWargaUser(): User
    {
        $role = Role::factory()->warga()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    // --- List ---

    public function test_authenticated_user_can_view_activity_list(): void
    {
        $user = $this->makeWargaUser();
        Activity::factory()->published()->count(3)->create();

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_view_activity_list(): void
    {
        $response = $this->getJson('/api/v1/activities');

        $response->assertStatus(401);
    }

    public function test_activity_list_shows_only_published_for_warga(): void
    {
        $user = $this->makeWargaUser();
        Activity::factory()->published()->count(2)->create();
        Activity::factory()->draft()->count(1)->create();
        Activity::factory()->cancelled()->count(1)->create();

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rt_can_see_all_activities_including_draft(): void
    {
        $rt = $this->makeRtUser();
        Activity::factory()->published()->count(2)->create();
        Activity::factory()->draft()->count(1)->create();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_activity_list_pagination(): void
    {
        $user = $this->makeWargaUser();
        Activity::factory()->published()->count(25)->create();

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_activity_list_search(): void
    {
        $user = $this->makeWargaUser();
        Activity::factory()->published()->create(['title' => 'Kerja Bakti RT 05']);
        Activity::factory()->published()->create(['title' => 'Posyandu Balita']);

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities?search=Kerja');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kerja Bakti RT 05');
    }

    public function test_activity_list_status_filter_for_rt(): void
    {
        $rt = $this->makeRtUser();
        Activity::factory()->published()->count(2)->create();
        Activity::factory()->draft()->count(3)->create();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/activities?status=draft');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_activity_list_ordered_by_created_at_desc(): void
    {
        $user = $this->makeWargaUser();
        $a = Activity::factory()->published()->create(['title' => 'First', 'created_at' => now()->subDay()]);
        $b = Activity::factory()->published()->create(['title' => 'Second', 'created_at' => now()]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Second')
            ->assertJsonPath('data.1.title', 'First');
    }

    // --- Detail ---

    public function test_warga_can_view_published_activity_detail(): void
    {
        $user = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($user)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Aktivitas berhasil diambil.'])
            ->assertJsonPath('data.id', $activity->id)
            ->assertJsonPath('data.title', $activity->title);
    }

    public function test_warga_cannot_view_draft_activity_detail(): void
    {
        $user = $this->makeWargaUser();
        $activity = Activity::factory()->draft()->create();

        $response = $this->actingAsUser($user)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_rt_can_view_any_activity_detail(): void
    {
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->draft()->create();

        $response = $this->actingAsUser($rt)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $activity->id);
    }

    public function test_activity_detail_includes_attachments(): void
    {
        $user = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($user)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'description', 'location', 'start_at', 'end_at',
                    'status', 'attachments', 'created_by', 'created_at',
                ],
            ]);
    }

    public function test_activity_not_found_returns_404(): void
    {
        $user = $this->makeWargaUser();

        $response = $this->actingAsUser($user)->getJson('/api/v1/activities/99999');

        $response->assertStatus(404);
    }

    // --- Create ---

    public function test_rt_can_create_activity(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/activities', [
            'title' => 'Kerja Bakti RT 05',
            'description' => 'Membersihkan lingkungan RT.',
            'location' => 'Lapangan RT 05',
            'start_at' => '2026-09-06 07:00:00',
            'end_at' => '2026-09-06 10:00:00',
            'status' => 'published',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Aktivitas berhasil dibuat.'])
            ->assertJsonPath('data.title', 'Kerja Bakti RT 05');

        $this->assertDatabaseHas('activities', [
            'title' => 'Kerja Bakti RT 05',
            'created_by' => $rt->id,
        ]);
    }

    public function test_warga_cannot_create_activity(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/activities', [
            'title' => 'Kerja Bakti',
            'start_at' => '2026-09-06 07:00:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_activity(): void
    {
        $response = $this->postJson('/api/v1/activities', [
            'title' => 'Kerja Bakti',
            'start_at' => '2026-09-06 07:00:00',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_activity_validation(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/activities', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'start_at']);
    }

    public function test_create_activity_end_at_must_be_after_start_at(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/activities', [
            'title' => 'Test',
            'start_at' => '2026-09-06 10:00:00',
            'end_at' => '2026-09-06 07:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_create_activity_does_not_accept_created_by_from_client(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $otherUser = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAsUser($rt)->postJson('/api/v1/activities', [
            'title' => 'Test',
            'start_at' => '2026-09-06 07:00:00',
            'created_by' => $otherUser->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activities', [
            'title' => 'Test',
            'created_by' => $rt->id,
        ]);
    }

    // --- Update ---

    public function test_rt_can_update_activity(): void
    {
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create(['title' => 'Old Title']);

        $response = $this->actingAsUser($rt)->putJson("/api/v1/activities/{$activity->id}", [
            'title' => 'New Title',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Aktivitas berhasil diperbarui.'])
            ->assertJsonPath('data.title', 'New Title');
    }

    public function test_warga_cannot_update_activity(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($warga)->putJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    // --- Delete ---

    public function test_rt_can_delete_activity(): void
    {
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Aktivitas berhasil dihapus.']);

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_warga_cannot_delete_activity(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($warga)->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertStatus(403);
    }

    // --- Mark as Read ---

    public function test_warga_can_mark_activity_as_read(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $response = $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Aktivitas berhasil ditandai telah dibaca.']);

        $this->assertDatabaseHas('activity_reads', [
            'activity_id' => $activity->id,
            'user_id' => $warga->id,
        ]);
    }

    public function test_duplicate_read_does_not_create_duplicate_record(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");
        $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");
        $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");

        $count = ActivityRead::where('activity_id', $activity->id)
            ->where('user_id', $warga->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_cannot_mark_draft_activity_as_read(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->draft()->create();

        $response = $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_cannot_mark_as_read(): void
    {
        $activity = Activity::factory()->published()->create();

        $response = $this->postJson("/api/v1/activities/{$activity->id}/read");

        $response->assertStatus(401);
    }

    // --- Notification ---

    public function test_creating_published_activity_dispatches_notification(): void
    {
        Queue::fake();

        $rt = $this->makeRtUser();
        DeviceToken::create(['user_id' => $rt->id, 'token' => 'test_token', 'platform' => 'android']);

        $this->actingAsUser($rt)->postJson('/api/v1/activities', [
            'title' => 'Kerja Bakti',
            'start_at' => '2026-09-06 07:00:00',
            'status' => 'published',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_creating_draft_activity_does_not_dispatch_notification(): void
    {
        Queue::fake();

        $rt = $this->makeRtUser();

        $this->actingAsUser($rt)->postJson('/api/v1/activities', [
            'title' => 'Draft Activity',
            'start_at' => '2026-09-06 07:00:00',
            'status' => 'draft',
        ]);

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_updating_draft_to_published_dispatches_notification(): void
    {
        Queue::fake();

        $rt = $this->makeRtUser();
        DeviceToken::create(['user_id' => $rt->id, 'token' => 'test_token', 'platform' => 'android']);
        $activity = Activity::factory()->draft()->create();

        $this->actingAsUser($rt)->putJson("/api/v1/activities/{$activity->id}", [
            'status' => 'published',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    // --- Attachment Upload ---

    public function test_rt_can_upload_attachment(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create();

        $file = UploadedFile::fake()->create('poster.pdf', 1024, 'application/pdf');

        $response = $this->actingAsUser($rt)->postJson("/api/v1/activities/{$activity->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Lampiran berhasil diupload.'])
            ->assertJsonStructure([
                'data' => ['id', 'file_name', 'mime_type', 'file_size', 'url', 'created_at'],
            ]);

        $this->assertDatabaseHas('activity_attachments', [
            'activity_id' => $activity->id,
            'file_name' => 'poster.pdf',
        ]);
    }

    public function test_warga_cannot_upload_attachment(): void
    {
        Storage::fake('public');
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $file = UploadedFile::fake()->create('poster.pdf', 1024);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_attachment_rejects_invalid_file_type(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create();

        $file = UploadedFile::fake()->create('script.exe', 1024, 'application/x-msdownload');

        $response = $this->actingAsUser($rt)->postJson("/api/v1/activities/{$activity->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_attachment_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create();

        // 10MB + 1KB - just over the 10240 KB limit
        $file = UploadedFile::fake()->create('large.pdf', 10241);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/activities/{$activity->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_rt_can_delete_attachment(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $activity = Activity::factory()->published()->create();

        $file = UploadedFile::fake()->create('poster.pdf', 1024, 'application/pdf');
        $activity->attachments()->create([
            'path' => 'activities/test.pdf',
            'file_name' => 'poster.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $attachment = $activity->attachments()->first();

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/activities/{$activity->id}/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Lampiran berhasil dihapus.']);
    }

    public function test_warga_cannot_delete_attachment(): void
    {
        Storage::fake('public');
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        $activity->attachments()->create([
            'path' => 'activities/test.pdf',
            'file_name' => 'poster.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $attachment = $activity->attachments()->first();

        $response = $this->actingAsUser($warga)->deleteJson("/api/v1/activities/{$activity->id}/attachments/{$attachment->id}");

        $response->assertStatus(403);
    }

    // --- is_read in response ---

    public function test_activity_list_includes_is_read_status(): void
    {
        $warga = $this->makeWargaUser();
        $activity = Activity::factory()->published()->create();

        // Not read yet
        $response = $this->actingAsUser($warga)->getJson('/api/v1/activities');
        $response->assertJsonPath('data.0.is_read', false);

        // Mark as read
        $this->actingAsUser($warga)->postJson("/api/v1/activities/{$activity->id}/read");

        // Now read
        $response = $this->actingAsUser($warga)->getJson('/api/v1/activities');
        $response->assertJsonPath('data.0.is_read', true);
    }
}
