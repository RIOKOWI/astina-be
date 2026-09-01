<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintComment;
use App\Models\DeviceToken;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintApiTest extends TestCase
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
        $role = Role::query()->where('code', 'rt')->first() ?? Role::factory()->rt()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeWargaUser(?Resident $resident = null): User
    {
        $role = Role::query()->where('code', 'warga')->first() ?? Role::factory()->warga()->create();
        $resident = $resident ?? Resident::factory()->create();
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'resident_id' => $resident->id,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    // --- Create ---

    public function test_warga_can_create_complaint(): void
    {
        Queue::fake();
        $resident = Resident::factory()->create();
        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Lampu jalan mati',
            'description' => 'Lampu jalan depan rumah saya mati.',
            'category' => 'facility',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Laporan berhasil dibuat.'])
            ->assertJsonPath('data.title', 'Lampu jalan mati')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.category', 'facility');

        $this->assertDatabaseHas('complaints', [
            'resident_id' => $resident->id,
            'title' => 'Lampu jalan mati',
            'status' => 'submitted',
        ]);
    }

    public function test_guest_cannot_create_complaint(): void
    {
        $response = $this->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'category' => 'facility',
        ]);

        $response->assertStatus(401);
    }

    public function test_resident_id_comes_from_authenticated_user(): void
    {
        Queue::fake();
        $resident = Resident::factory()->create();
        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'description' => 'Test desc',
            'category' => 'security',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('complaints', [
            'resident_id' => $resident->id,
        ]);
    }

    public function test_reference_no_generated_automatically(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'category' => 'facility',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertStringStartsWith('CMP-'.date('Y').'-', $data['reference_no']);
    }

    public function test_initial_status_is_submitted(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'category' => 'facility',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_create_complaint_requires_title(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'category' => 'facility',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_complaint_requires_category(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_complaint_validates_category(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'category' => 'invalid_category',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_rt_cannot_create_complaint(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/complaints', [
            'title' => 'Test',
            'category' => 'facility',
        ]);

        $response->assertStatus(403);
    }

    // --- List ---

    public function test_warga_only_sees_own_complaints(): void
    {
        $resident1 = Resident::factory()->create();
        $resident2 = Resident::factory()->create();
        $warga1 = $this->makeWargaUser($resident1);
        $warga2 = $this->makeWargaUser($resident2);

        Complaint::factory()->submitted()->create(['resident_id' => $resident1->id]);
        Complaint::factory()->submitted()->create(['resident_id' => $resident1->id]);
        Complaint::factory()->submitted()->create(['resident_id' => $resident2->id]);

        $response = $this->actingAsUser($warga1)->getJson('/api/v1/complaints');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    public function test_rt_can_see_all_complaints(): void
    {
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();

        Complaint::factory()->submitted()->count(3)->create();
        Complaint::factory()->reviewed()->count(2)->create();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/complaints');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_complaint_list_pagination(): void
    {
        $warga = $this->makeWargaUser();
        Complaint::factory()->submitted()->count(25)->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson('/api/v1/complaints?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_complaint_list_status_filter(): void
    {
        $warga = $this->makeWargaUser();
        Complaint::factory()->submitted()->count(3)->create(['resident_id' => $warga->resident_id]);
        Complaint::factory()->reviewed()->count(2)->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson('/api/v1/complaints?status=submitted');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_complaint_list_category_filter(): void
    {
        $warga = $this->makeWargaUser();
        Complaint::factory()->submitted()->facility()->count(2)->create(['resident_id' => $warga->resident_id]);
        Complaint::factory()->submitted()->security()->count(3)->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson('/api/v1/complaints?category=facility');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_complaint_list_search(): void
    {
        $warga = $this->makeWargaUser();
        Complaint::factory()->submitted()->create([
            'resident_id' => $warga->resident_id,
            'title' => 'Lampu jalan mati',
        ]);
        Complaint::factory()->submitted()->create([
            'resident_id' => $warga->resident_id,
            'title' => 'Sampah menumpuk',
        ]);

        $response = $this->actingAsUser($warga)->getJson('/api/v1/complaints?search=Lampu');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lampu jalan mati');
    }

    public function test_unauthenticated_cannot_list_complaints(): void
    {
        $response = $this->getJson('/api/v1/complaints');

        $response->assertStatus(401);
    }

    // --- Show ---

    public function test_warga_can_view_own_complaint(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $complaint->id)
            ->assertJsonPath('data.reference_no', $complaint->reference_no);
    }

    public function test_warga_cannot_view_another_resident_complaint(): void
    {
        $warga = $this->makeWargaUser();
        $otherWarga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $otherWarga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_rt_can_view_any_complaint(): void
    {
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $complaint->id);
    }

    public function test_complaint_detail_includes_attachments_and_comments(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'reference_no', 'title', 'description', 'category',
                    'status', 'rejection_reason', 'submitted_at', 'approved_at',
                    'resolved_at', 'created_at', 'updated_at', 'resident',
                    'assigned_to', 'attachments', 'comments', 'attachment_count',
                ],
            ]);
    }

    // --- Status Update ---

    public function test_rt_can_update_status_to_reviewed(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'reviewed',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'reviewed');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'reviewed',
        ]);
    }

    public function test_rt_can_update_status_to_in_progress(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->reviewed()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'in_progress',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_rt_can_update_status_to_resolved(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->inProgress()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_rt_can_update_status_to_closed(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->resolved()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_rt_can_reject_complaint(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'rejected',
            'rejection_reason' => 'Laporan tidak termasuk wilayah RT.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Laporan tidak termasuk wilayah RT.');
    }

    public function test_rejection_requires_reason(): void
    {
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'rejected',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_warga_cannot_update_status(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        // submitted -> resolved is invalid (must go through reviewed first)
        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_transition_from_closed(): void
    {
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->closed()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'submitted',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_transition_from_rejected(): void
    {
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->rejected()->create();

        $response = $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'reviewed',
        ]);

        $response->assertStatus(409);
    }

    // --- Attachment Upload ---

    public function test_rt_can_upload_attachment(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $file = UploadedFile::fake()->create('foto.jpg', 1024, 'image/jpeg');

        $response = $this->actingAsUser($rt)->postJson("/api/v1/complaints/{$complaint->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Lampiran berhasil diupload.'])
            ->assertJsonStructure([
                'data' => ['id', 'file_name', 'mime_type', 'file_size', 'url', 'created_at'],
            ]);

        $this->assertDatabaseHas('complaint_attachments', [
            'complaint_id' => $complaint->id,
            'file_name' => 'foto.jpg',
        ]);
    }

    public function test_warga_cannot_upload_attachment(): void
    {
        Storage::fake('public');
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $file = UploadedFile::fake()->create('foto.jpg', 1024, 'image/jpeg');

        $response = $this->actingAsUser($warga)->postJson("/api/v1/complaints/{$complaint->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_attachment_rejects_invalid_file_type(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $file = UploadedFile::fake()->create('script.exe', 1024, 'application/x-msdownload');

        $response = $this->actingAsUser($rt)->postJson("/api/v1/complaints/{$complaint->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_attachment_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $file = UploadedFile::fake()->create('large.jpg', 10241, 'image/jpeg');

        $response = $this->actingAsUser($rt)->postJson("/api/v1/complaints/{$complaint->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_rt_can_delete_attachment(): void
    {
        Storage::fake('public');
        $rt = $this->makeRtUser();
        $complaint = Complaint::factory()->submitted()->create();

        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id,
            'path' => 'complaints/1/test.jpg',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/complaints/{$complaint->id}/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Lampiran berhasil dihapus.']);

        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
    }

    public function test_cannot_delete_attachment_belonging_to_another_complaint(): void
    {
        $rt = $this->makeRtUser();
        $complaint1 = Complaint::factory()->submitted()->create();
        $complaint2 = Complaint::factory()->submitted()->create();

        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint2->id,
            'path' => 'complaints/2/test.jpg',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/complaints/{$complaint1->id}/attachments/{$attachment->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // --- Comments ---

    public function test_warga_can_view_comments_on_own_complaint(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);
        ComplaintComment::create([
            'complaint_id' => $complaint->id,
            'user_id' => $warga->id,
            'comment' => 'Ini komentar saya.',
        ]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/complaints/{$complaint->id}/comments");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Ini komentar saya.');
    }

    public function test_warga_cannot_view_comments_on_other_complaint(): void
    {
        $warga1 = $this->makeWargaUser();
        $warga2 = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga2->resident_id]);

        $response = $this->actingAsUser($warga1)->getJson("/api/v1/complaints/{$complaint->id}/comments");

        $response->assertStatus(404);
    }

    public function test_rt_can_view_comments_on_any_complaint(): void
    {
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);
        ComplaintComment::create([
            'complaint_id' => $complaint->id,
            'user_id' => $warga->id,
            'comment' => 'Komentar warga.',
        ]);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/complaints/{$complaint->id}/comments");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_authenticated_user_can_add_comment(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/complaints/{$complaint->id}/comments", [
            'comment' => 'Terima kasih atas responsnya.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.comment', 'Terima kasih atas responsnya.');

        $this->assertDatabaseHas('complaint_comments', [
            'complaint_id' => $complaint->id,
            'user_id' => $warga->id,
            'comment' => 'Terima kasih atas responsnya.',
        ]);
    }

    public function test_rt_can_add_comment(): void
    {
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/complaints/{$complaint->id}/comments", [
            'comment' => 'Kami sedang menangani laporan ini.',
        ]);

        $response->assertStatus(201);
    }

    public function test_comment_requires_content(): void
    {
        $warga = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/complaints/{$complaint->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_warga_cannot_comment_on_other_complaint(): void
    {
        $warga1 = $this->makeWargaUser();
        $warga2 = $this->makeWargaUser();
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga2->resident_id]);

        $response = $this->actingAsUser($warga1)->postJson("/api/v1/complaints/{$complaint->id}/comments", [
            'comment' => 'Ini bukan laporan saya.',
        ]);

        $response->assertStatus(404);
    }

    // --- Notification ---

    public function test_creating_complaint_dispatches_notification_to_rt(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        DeviceToken::create(['user_id' => $rt->id, 'token' => 'test_token', 'platform' => 'android']);

        $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Lampu mati',
            'category' => 'facility',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_status_update_dispatches_notification_to_owner(): void
    {
        Queue::fake();
        $rt = $this->makeRtUser();
        $warga = $this->makeWargaUser();
        DeviceToken::create(['user_id' => $warga->id, 'token' => 'warga_token', 'platform' => 'android']);
        $complaint = Complaint::factory()->submitted()->create(['resident_id' => $warga->resident_id]);

        $this->actingAsUser($rt)->patchJson("/api/v1/complaints/{$complaint->id}/status", [
            'status' => 'reviewed',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    // --- Reference Number ---

    public function test_reference_number_increments(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'First',
            'category' => 'facility',
        ]);

        $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Second',
            'category' => 'facility',
        ]);

        $complaints = Complaint::orderBy('id')->get();
        $ref1 = (int) substr($complaints[0]->reference_no, -6);
        $ref2 = (int) substr($complaints[1]->reference_no, -6);

        $this->assertEquals(1, $ref2 - $ref1);
    }

    public function test_reference_number_unique(): void
    {
        Queue::fake();
        $warga = $this->makeWargaUser();

        $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'First',
            'category' => 'facility',
        ]);

        $this->actingAsUser($warga)->postJson('/api/v1/complaints', [
            'title' => 'Second',
            'category' => 'facility',
        ]);

        $count = Complaint::distinct('reference_no')->count('reference_no');

        $this->assertEquals(2, $count);
    }
}
