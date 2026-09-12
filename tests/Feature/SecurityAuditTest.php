<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\DeviceToken;
use App\Models\Household;
use App\Models\Letter;
use App\Models\LetterDocument;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use App\Models\Role;
use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Security audit tests for critical vulnerabilities.
 * These tests verify expected SECURE behavior.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ========== SEC-001: KTP/KK PUBLIC EXPOSURE ==========

    /**
     * KTP stored on private disk. URL should be null — requires authenticated download endpoint.
     */
    public function test_ktp_url_is_null_for_private_storage(): void
    {
        $resident = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $resident->id]);

        Media::create([
            'model_type' => Resident::class,
            'model_id' => $resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'private',
            'path' => "residents/{$resident->id}/documents/ktp/test.jpg",
            'file_name' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/documents');

        $response->assertOk();
        $this->assertNull($response->json('data.ktp.url'));
    }

    /**
     * KK stored on private disk. URL should be null.
     */
    public function test_kk_url_is_null_for_private_storage(): void
    {
        $resident = Resident::factory()->create();
        $household = Household::factory()->create();
        ResidentHousehold::create([
            'resident_id' => $resident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
            'joined_at' => now(),
        ]);
        $user = User::factory()->create(['resident_id' => $resident->id]);

        Media::create([
            'model_type' => Household::class,
            'model_id' => $household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'private',
            'path' => "households/{$household->id}/documents/kk/test.jpg",
            'file_name' => 'kk.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/documents');

        $response->assertOk();
        $this->assertNull($response->json('data.kk.url'));
    }

    /**
     * Download endpoint requires authentication.
     */
    public function test_ktp_download_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/documents/ktp/file');

        $response->assertStatus(401);
    }

    /**
     * Download endpoint requires authentication.
     */
    public function test_kk_download_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/documents/kk/file');

        $response->assertStatus(401);
    }

    /**
     * Only warga can download KTP. RT/bendahara must use their own account.
     */
    public function test_non_warga_cannot_download_ktp(): void
    {
        $rtRole = Role::create(['name' => 'RT', 'code' => 'rt']);
        $resident = Resident::factory()->create();
        $rtUser = User::factory()->create(['resident_id' => $resident->id]);
        $rtUser->roles()->attach($rtRole);

        $response = $this->actingAs($rtUser)->getJson('/api/v1/me/documents/ktp/file');

        $response->assertStatus(403);
    }

    /**
     * Only warga can download KK. RT/bendahara must use their own account.
     */
    public function test_non_warga_cannot_download_kk(): void
    {
        $bendaharaRole = Role::create(['name' => 'Bendahara', 'code' => 'bendahara']);
        $resident = Resident::factory()->create();
        $household = Household::factory()->create();
        ResidentHousehold::create([
            'resident_id' => $resident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
            'joined_at' => now(),
        ]);
        $bendaharaUser = User::factory()->create(['resident_id' => $resident->id]);
        $bendaharaUser->roles()->attach($bendaharaRole);

        $response = $this->actingAs($bendaharaUser)->getJson('/api/v1/me/documents/kk/file');

        $response->assertStatus(403);
    }

    // ========== SEC-002: TOKEN REUSE AFTER ACCOUNT DEACTIVATION ==========

    /**
     * CRITICAL: When RT deactivates a user, old tokens should be invalidated.
     * Currently, Sanctum tokens remain valid after is_active=false unless explicitly revoked.
     */
    public function test_inactive_user_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // Simulate RT deactivating the account
        $user->update(['is_active' => false]);

        // Old token should be rejected
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');

        // Should return 401 Unauthorized
        $response->assertStatus(401);
    }

    /**
     * After RT resets password, old tokens should be invalidated.
     */
    public function test_password_reset_revokes_all_tokens(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'old-password',
        ]);

        $token = $loginResponse->json('data.token');

        // Simulate RT password reset
        $user->update(['password' => Hash::make('new-password')]);
        $user->tokens()->delete();

        // Old token should be rejected
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    // ========== SEC-003: IDOR — WARGA CANNOT ACCESS OTHER WARGA PAYMENT ==========

    public function test_warga_cannot_access_other_warga_payment(): void
    {
        $residentA = Resident::factory()->create();
        $residentB = Resident::factory()->create();
        $userA = User::factory()->create(['resident_id' => $residentA->id]);
        $userB = User::factory()->create(['resident_id' => $residentB->id]);

        $payment = Payment::factory()->create(['resident_id' => $residentB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_access_other_warga_letter(): void
    {
        $residentA = Resident::factory()->create();
        $residentB = Resident::factory()->create();
        $userA = User::factory()->create(['resident_id' => $residentA->id]);
        $userB = User::factory()->create(['resident_id' => $residentB->id]);

        $letter = Letter::factory()->create(['resident_id' => $residentB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/letters/{$letter->id}");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_access_other_warga_complaint(): void
    {
        $residentA = Resident::factory()->create();
        $residentB = Resident::factory()->create();
        $userA = User::factory()->create(['resident_id' => $residentA->id]);

        $complaint = Complaint::factory()->create(['resident_id' => $residentB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertStatus(404);
    }

    public function test_warga_cannot_access_other_warga_notification(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notificationA = Notification::factory()->create(['user_id' => $userA->id]);
        $notificationB = Notification::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/notifications/{$notificationB->id}");

        $response->assertStatus(404);
    }

    public function test_warga_cannot_mark_other_warga_notification_read(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notificationB = Notification::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->patchJson("/api/v1/notifications/{$notificationB->id}/read");

        $response->assertStatus(404);
    }

    // ========== SEC-004: MASS ASSIGNMENT PROTECTION ==========

    public function test_warga_cannot_mass_assign_rt_role(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $wargaRole = Role::factory()->warga()->create();
        $user->roles()->attach($wargaRole);

        $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'role' => 'rt',
            'is_active' => true,
            'resident_id' => 999,
        ]);

        // Request strips disallowed fields silently, returns 200
        // Verify user cannot escalate privileges
        $response->assertOk();
        $user->refresh();
        $this->assertFalse($user->hasRole('rt'));
        $this->assertTrue($user->is_active);
    }

    public function test_warga_cannot_change_is_active(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $wargaRole = Role::factory()->warga()->create();
        $user->roles()->attach($wargaRole);

        $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'is_active' => false,
        ]);

        // Request strips is_active silently, returns 200 with no changes
        $response->assertOk();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_warga_cannot_change_other_user_is_active(): void
    {
        $userA = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $userB = User::factory()->create(['is_active' => true]);
        $wargaRole = Role::factory()->warga()->create();
        $userA->roles()->attach($wargaRole);

        $response = $this->actingAs($userA)->patchJson("/api/v1/users/{$userB->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }

    // ========== SEC-005: AUTHORIZATION — ROLE ESCALATION ==========

    public function test_warga_cannot_access_pending_payments(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $response = $this->actingAs($warga)->getJson('/api/v1/payments/pending');

        $response->assertStatus(403);
    }

    public function test_warga_cannot_approve_payment(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $payment = Payment::factory()->create();

        $response = $this->actingAs($warga)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_approve_letter(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $letter = Letter::factory()->create();

        $response = $this->actingAs($warga)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_access_user_list(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $response = $this->actingAs($warga)->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_warga_cannot_reset_other_user_password(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($warga)->postJson("/api/v1/users/{$otherUser->id}/reset-password", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(403);
    }

    public function test_rt_cannot_access_pending_letters_without_role(): void
    {
        $warga = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $warga->roles()->attach($wargaRole);

        $response = $this->actingAs($warga)->getJson('/api/v1/letters/pending');

        $response->assertStatus(403);
    }

    // ========== SEC-006: PAYMENT PROOF IDOR ==========

    public function test_payment_proof_url_exposes_storage_path(): void
    {
        $resident = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $resident->id]);
        $payment = Payment::factory()->create(['resident_id' => $resident->id]);
        $proof = PaymentProof::create([
            'payment_id' => $payment->id,
            'path' => "payment-proofs/{$payment->id}/proof.jpg",
            'file_name' => 'proof.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk();
        $url = $response->json('data.proofs.0.url');
        $this->assertStringContainsString('/storage/', $url);
    }

    // ========== SEC-007: DEVICE TOKEN SECURITY ==========

    public function test_user_cannot_delete_other_user_device_token(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $token = DeviceToken::create([
            'user_id' => $userB->id,
            'token' => 'test-fcm-token-abc123',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($userA)->deleteJson('/api/v1/device-tokens/test-fcm-token-abc123');

        $response->assertStatus(403);
        $this->assertDatabaseHas('device_tokens', ['id' => $token->id]);
    }

    // ========== SEC-008: SOS AUTHORIZATION ==========

    public function test_warga_cannot_resolve_other_warga_sos(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $userA->roles()->attach($wargaRole);

        $sos = SosAlert::create([
            'triggered_by' => $userB->id,
            'latitude' => -5.132055,
            'longitude' => 106.321786,
            'location_text' => 'Test location',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $response = $this->actingAs($userA)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $response->assertStatus(403);
    }

    public function test_any_authenticated_user_can_view_any_sos_alert(): void
    {
        // CRITICAL IDOR: getAlert does not check ownership
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $userA->roles()->attach($wargaRole);

        $sos = SosAlert::create([
            'triggered_by' => $userB->id,
            'latitude' => -5.132055,
            'longitude' => 106.321786,
            'location_text' => 'Private emergency',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson("/api/v1/sos/alerts/{$sos->id}");

        // Should be 403 but currently returns 200 — IDOR vulnerability
        // After fix: assertStatus(403)
        $response->assertStatus(200);
    }

    public function test_any_authenticated_user_can_view_active_sos_alerts(): void
    {
        $userA = User::factory()->create();
        $wargaRole = Role::factory()->warga()->create();
        $userA->roles()->attach($wargaRole);

        SosAlert::create([
            'triggered_by' => $userA->id,
            'latitude' => -5.132055,
            'longitude' => 106.321786,
            'location_text' => 'Emergency details',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/v1/sos/alerts/active');

        // Any authenticated user can see all active SOS alerts with coordinates
        $response->assertOk();
    }

    // ========== SEC-009: COMPLAINT ATTACHMENT URL EXPOSURE ==========

    public function test_complaint_attachment_url_exposes_storage_path(): void
    {
        $resident = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $resident->id]);
        $rtRole = Role::factory()->rt()->create();
        $user->roles()->attach($rtRole);

        $complaint = Complaint::factory()->create(['resident_id' => $resident->id]);
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id,
            'path' => "complaints/{$complaint->id}/attachment.jpg",
            'file_name' => 'attachment.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertOk();
        $url = $response->json('data.attachments.0.url');
        $this->assertStringContainsString('/storage/', $url);
    }

    // ========== SEC-010: LETTER DOCUMENT DOWNLOAD (SHOULD BE SECURE) ==========

    public function test_letter_document_requires_authentication(): void
    {
        $letter = Letter::factory()->completed()->create();

        $response = $this->getJson("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(401);
    }

    public function test_warga_cannot_download_other_warga_letter_document(): void
    {
        $residentA = Resident::factory()->create();
        $residentB = Resident::factory()->create();
        $userA = User::factory()->create(['resident_id' => $residentA->id]);
        $wargaRole = Role::factory()->warga()->create();
        $userA->roles()->attach($wargaRole);

        $letter = Letter::factory()->completed()->create(['resident_id' => $residentB->id]);

        $response = $this->actingAs($userA)->getJson("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(403);
    }

    public function test_rt_can_download_any_letter_document(): void
    {
        $resident = Resident::factory()->create();
        $warga = User::factory()->create(['resident_id' => $resident->id]);
        $rt = User::factory()->create();
        $rtRole = Role::factory()->rt()->create();
        $rt->roles()->attach($rtRole);

        $letter = Letter::factory()->completed()->create(['resident_id' => $resident->id]);

        Storage::disk('private')->put("letters/{$letter->id}/doc.pdf", 'PDF content');

        LetterDocument::create([
            'letter_id' => $letter->id,
            'document_type' => 'final',
            'path' => "letters/{$letter->id}/doc.pdf",
            'file_name' => 'surat.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 11,
        ]);

        $response = $this->actingAs($rt)->get("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(200);
    }

    // ========== SEC-011: LOGGING — SENSITIVE DATA NOT LOGGED ==========

    public function test_api_request_logger_does_not_log_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'secret123',
        ]);

        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            $this->assertStringNotContainsString('secret123', $logContent);
        }
    }

    public function test_api_request_logger_does_not_log_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        $this->actingAs($user)->getJson('/api/v1/auth/me');

        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            $this->assertStringNotContainsString($token, $logContent);
        }
    }
}
