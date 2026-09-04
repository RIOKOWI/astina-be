<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterDocument;
use App\Models\LetterField;
use App\Models\LetterFieldValue;
use App\Models\LetterType;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Signature;
use App\Models\Stamp;
use App\Models\User;
use App\Services\LetterDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LetterApiTest extends TestCase
{
    use RefreshDatabase;

    private User $rtUser;

    private User $wargaUser;

    private Resident $resident;

    private LetterType $letterType;

    private LetterField $requiredField;

    private LetterField $optionalField;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock LetterDocumentService so tests don't require DOMPDF/font rendering
        $this->app->bind(LetterDocumentService::class, function () {
            return new class extends LetterDocumentService
            {
                public function generateForLetter(Letter $letter): LetterDocument
                {
                    $workDir = storage_path('framework/tmp/letters/'.(Str::uuid()->toString()));
                    mkdir($workDir, 0755, true);

                    try {
                        // Create minimal valid PDF directly (no DOMPDF needed in tests)
                        $pdfPath = $workDir.'/output.pdf';
                        file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R>>endobj xref 0 4\n0000000000 65535 f\n0000000015 00000 n\n0000000068 00000 n\n0000000125 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n210\n%%EOF");

                        $filename = $letter->reference_no.'.pdf';
                        $dir = config('letters.generated_path').'/'.$letter->id;
                        Storage::disk('private')->putFileAs($dir, new File($pdfPath), $filename);
                        $storedPath = $dir.'/'.$filename;
                        $fileSize = Storage::disk('private')->size($storedPath);

                        $document = LetterDocument::create([
                            'letter_id' => $letter->id,
                            'document_type' => 'final',
                            'path' => $storedPath,
                            'file_name' => $letter->reference_no.'.pdf',
                            'mime_type' => 'application/pdf',
                            'file_size' => $fileSize,
                        ]);
                    } finally {
                        if (is_dir($workDir)) {
                            $files2 = new \RecursiveIteratorIterator(
                                new \RecursiveDirectoryIterator($workDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                                \RecursiveIteratorIterator::CHILD_FIRST
                            );
                            foreach ($files2 as $f) {
                                if ($f->isDir()) {
                                    rmdir($f->getRealPath());
                                } else {
                                    unlink($f->getRealPath());
                                }
                            }
                            rmdir($workDir);
                        }
                    }

                    return $document;
                }
            };
        });

        $rtRole = Role::create(['name' => 'RT', 'code' => 'rt']);
        $wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);

        $this->rtUser = User::factory()->create();
        $this->rtUser->roles()->attach($rtRole);
        DeviceToken::create(['user_id' => $this->rtUser->id, 'token' => fake()->uuid(), 'platform' => 'android', 'device_name' => 'RT Test Device']);

        $this->resident = Resident::factory()->create();
        $this->wargaUser = User::factory()->create(['resident_id' => $this->resident->id]);
        $this->wargaUser->roles()->attach($wargaRole);
        DeviceToken::create(['user_id' => $this->wargaUser->id, 'token' => fake()->uuid(), 'platform' => 'android', 'device_name' => 'Test Device']);

        $this->letterType = LetterType::factory()->create(['code' => 'SKD', 'name' => 'Surat Keterangan Domisili', 'is_active' => true]);
        $this->requiredField = LetterField::factory()->create([
            'letter_type_id' => $this->letterType->id,
            'field_key' => 'keperluan',
            'label' => 'Keperluan',
            'field_type' => 'textarea',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $this->optionalField = LetterField::factory()->create([
            'letter_type_id' => $this->letterType->id,
            'field_key' => 'catatan',
            'label' => 'Catatan',
            'field_type' => 'text',
            'is_required' => false,
            'sort_order' => 2,
        ]);
    }

    // ========== LETTER TYPE TESTS ==========

    public function test_warga_can_list_active_letter_types(): void
    {
        LetterType::factory()->count(3)->create(['is_active' => true]);
        LetterType::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/letter-types');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('SKD', $codes);
        // 1 from setUp + 3 factories = 4 active
        $this->assertCount(4, $response->json('data'));
    }

    public function test_letter_type_detail_returns_fields(): void
    {
        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letter-types/{$this->letterType->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SKD')
            ->assertJsonPath('data.fields.0.field_key', 'keperluan')
            ->assertJsonPath('data.fields.0.is_required', true);
    }

    public function test_inactive_letter_type_detail_returns_404(): void
    {
        $inactive = LetterType::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letter-types/{$inactive->id}");

        $response->assertStatus(404);
    }

    // ========== AUTH TESTS ==========

    public function test_unauthenticated_cannot_access_letter_types(): void
    {
        $response = $this->getJson('/api/v1/letter-types');
        $response->assertStatus(401);
    }

    public function test_warga_can_create_own_letter(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'purpose' => 'Untuk keperluan administrasi',
            'fields' => [
                'keperluan' => 'Membuat KTP baru',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.reference_no', 'SKD/RT05/'.now()->format('Ym').'/000001');

        $this->assertDatabaseHas('letters', [
            'resident_id' => $this->resident->id,
            'submitted_by' => $this->wargaUser->id,
            'status' => 'submitted',
        ]);
    }

    public function test_resident_id_from_auth_not_request(): void
    {
        Queue::fake();

        // Try to inject different resident_id (should be ignored)
        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'resident_id' => 9999,
            'fields' => ['keperluan' => 'Test'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('letters', [
            'resident_id' => $this->resident->id,
            'submitted_by' => $this->wargaUser->id,
        ]);
    }

    public function test_reference_no_is_unique(): void
    {
        Queue::fake();

        $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'fields' => ['keperluan' => 'First'],
        ]);

        $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'fields' => ['keperluan' => 'Second'],
        ]);

        $refs = Letter::pluck('reference_no');
        $this->assertEquals($refs->unique()->count(), $refs->count());
    }

    public function test_required_field_validation(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'fields' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_field_from_another_letter_type_rejected(): void
    {
        Queue::fake();

        $otherType = LetterType::factory()->create();
        LetterField::factory()->create([
            'letter_type_id' => $otherType->id,
            'field_key' => 'other_field',
            'is_required' => true,
        ]);

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'fields' => [
                'keperluan' => 'Valid',
                'other_field' => 'Invalid',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_letter_type_rejected(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => 9999,
            'fields' => ['keperluan' => 'Test'],
        ]);

        $response->assertStatus(422);
    }

    public function test_warga_only_sees_own_letters(): void
    {
        $otherResident = Resident::factory()->create();
        $otherLetter = Letter::factory()->submitted()->create(['resident_id' => $otherResident->id]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/my/letters');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->toArray();
        $this->assertNotContains($otherLetter->id, $ids);
    }

    public function test_warga_cannot_view_another_resident_letter(): void
    {
        $otherLetter = Letter::factory()->submitted()->create();

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letters/{$otherLetter->id}");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_approve(): void
    {
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_reject(): void
    {
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/reject", [
            'reason' => 'Invalid data',
        ]);

        $response->assertStatus(403);
    }

    public function test_warga_cannot_sign(): void
    {
        $letter = Letter::factory()->approved()->create();
        $file = UploadedFile::fake()->image('sig.png');

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/sign", [
            'signature_image' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_warga_cannot_stamp(): void
    {
        $letter = Letter::factory()->approved()->create();
        $file = UploadedFile::fake()->image('stamp.png');

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        $response->assertStatus(403);
    }

    // ========== APPROVAL TESTS ==========

    public function test_rt_can_approve_submitted_letter(): void
    {
        Queue::fake();

        $letter = Letter::factory()->submitted()->create([
            'resident_id' => $this->resident->id,
            'letter_type_id' => $this->letterType->id,
        ]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('message', 'Surat berhasil disetujui dan dokumen telah dibuat.');

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('letter_approvals', [
            'letter_id' => $letter->id,
            'approved_by' => $this->rtUser->id,
            'action' => 'approved',
        ]);
        $this->assertDatabaseHas('letter_documents', [
            'letter_id' => $letter->id,
            'document_type' => 'final',
        ]);
    }

    public function test_approval_creates_audit_trail(): void
    {
        Queue::fake();
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $approval = LetterApproval::where('letter_id', $letter->id)->first();
        $this->assertNotNull($approval->acted_at);
        $this->assertNotNull($approval->created_at);
    }

    public function test_approve_auto_generates_document_and_marks_completed(): void
    {
        Queue::fake();

        $letter = Letter::factory()->submitted()->create([
            'resident_id' => $this->resident->id,
            'letter_type_id' => $this->letterType->id,
            'purpose' => 'Membuat KTP',
        ]);
        LetterFieldValue::create([
            'letter_id' => $letter->id,
            'letter_field_id' => $this->requiredField->id,
            'value' => 'Membuat KTP',
        ]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('letter_documents', [
            'letter_id' => $letter->id,
            'document_type' => 'final',
        ]);

        $doc = LetterDocument::where('letter_id', $letter->id)->first();
        $this->assertNotNull($doc);
        $this->assertEquals('application/pdf', $doc->mime_type);
        $this->assertStringEndsWith('.pdf', $doc->file_name);
        $this->assertGreaterThan(0, $doc->file_size);
    }

    public function test_duplicate_approve_returns_409(): void
    {
        Queue::fake();
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");
        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertStatus(409);
    }

    public function test_approve_rejected_letter_returns_409(): void
    {
        Queue::fake();
        $letter = Letter::factory()->rejected()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertStatus(409);
    }

    public function test_non_rt_cannot_approve(): void
    {
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        $response->assertStatus(403);
    }

    // ========== REJECTION TESTS ==========

    public function test_rt_can_reject_letter(): void
    {
        Queue::fake();
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/reject", [
            'reason' => 'Data tidak lengkap',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Data tidak lengkap');

        $this->assertDatabaseHas('letter_approvals', [
            'letter_id' => $letter->id,
            'approved_by' => $this->rtUser->id,
            'action' => 'rejected',
            'notes' => 'Data tidak lengkap',
        ]);
    }

    public function test_reject_reason_required(): void
    {
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'The reason field is required.');
    }

    public function test_duplicate_reject_returns_409(): void
    {
        Queue::fake();
        $letter = Letter::factory()->rejected()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/reject", [
            'reason' => 'Another reason',
        ]);

        $response->assertStatus(409);
    }

    public function test_non_rt_cannot_reject(): void
    {
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letters/{$letter->id}/reject", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    // ========== SIGNATURE TESTS ==========

    public function test_rt_can_sign_approved_letter(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        $file = UploadedFile::fake()->image('signature.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/sign", [
            'signature_image' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('signatures', [
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
        ]);
    }

    public function test_sign_requires_signature_image(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create();

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/sign", []);

        $response->assertStatus(422);
    }

    public function test_duplicate_sign_blocked(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        $file = UploadedFile::fake()->image('sig.png');

        Signature::factory()->create([
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
            'signed_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/sign", [
            'signature_image' => $file,
        ]);

        $response->assertStatus(409);
    }

    public function test_sign_non_approved_letter_returns_409(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);
        $file = UploadedFile::fake()->image('sig.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/sign", [
            'signature_image' => $file,
        ]);

        $response->assertStatus(409);
    }

    // ========== STAMP TESTS ==========

    public function test_rt_can_stamp_signed_letter(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        Signature::factory()->create([
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
            'signed_at' => now(),
            'created_at' => now(),
        ]);
        $file = UploadedFile::fake()->image('stamp.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('stamps', [
            'letter_id' => $letter->id,
            'stamped_by' => $this->rtUser->id,
        ]);
    }

    public function test_stamp_requires_signature_first(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        $file = UploadedFile::fake()->image('stamp.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Surat harus ditandatangani terlebih dahulu sebelum distempel.');
    }

    public function test_duplicate_stamp_blocked(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        Signature::factory()->create([
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
            'signed_at' => now(),
            'created_at' => now(),
        ]);
        Stamp::factory()->create([
            'letter_id' => $letter->id,
            'stamped_by' => $this->rtUser->id,
            'stamped_at' => now(),
            'created_at' => now(),
        ]);
        $file = UploadedFile::fake()->image('stamp.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        $response->assertStatus(409);
    }

    public function test_stamp_auto_completes_letter(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        Signature::factory()->create([
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
            'signed_at' => now(),
            'created_at' => now(),
        ]);
        $file = UploadedFile::fake()->image('stamp.png');

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        $response->assertOk();
        $letter->refresh();
        $this->assertEquals('completed', $letter->status);
    }

    // ========== DOCUMENT TESTS ==========

    public function test_owner_can_download_document(): void
    {
        Storage::fake('private');
        $letter = Letter::factory()->completed()->create(['resident_id' => $this->resident->id]);
        LetterDocument::factory()->create([
            'letter_id' => $letter->id,
            'document_type' => 'final',
            'path' => 'letters/generated/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        Storage::disk('private')->put('letters/generated/test.pdf', 'PDF content');

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(200);
    }

    public function test_unauthorized_document_download_blocked(): void
    {
        $letter = Letter::factory()->completed()->create();
        LetterDocument::factory()->create([
            'letter_id' => $letter->id,
            'document_type' => 'final',
            'path' => 'letters/generated/test.pdf',
        ]);

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(403);
    }

    public function test_incomplete_letter_cannot_download_document(): void
    {
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letters/{$letter->id}/document");

        $response->assertStatus(409);
    }

    // ========== NOTIFICATION TESTS ==========

    public function test_submit_dispatches_rt_notification(): void
    {
        Queue::fake();

        $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'fields' => ['keperluan' => 'Test'],
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_approve_dispatches_warga_notification(): void
    {
        Queue::fake();
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/approve");

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_reject_dispatches_warga_notification(): void
    {
        Queue::fake();
        $letter = Letter::factory()->submitted()->create(['resident_id' => $this->resident->id]);

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/reject", [
            'reason' => 'Invalid',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_completion_dispatches_warga_notification(): void
    {
        Queue::fake();
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        Signature::factory()->create([
            'letter_id' => $letter->id,
            'signed_by' => $this->rtUser->id,
            'signed_at' => now(),
            'created_at' => now(),
        ]);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('stamp.png');

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/stamp", [
            'stamp_image' => $file,
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, 2);
    }

    // ========== PENDING LETTERS ==========

    public function test_rt_can_list_pending_letters(): void
    {
        Letter::factory()->count(3)->submitted()->create();
        Letter::factory()->count(2)->approved()->create();

        $response = $this->actingAs($this->rtUser)->getJson('/api/v1/letters/pending');

        $response->assertOk();
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_warga_cannot_access_pending_letters(): void
    {
        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/letters/pending');

        $response->assertStatus(403);
    }

    // ========== RT CAN VIEW LETTER DETAIL ==========

    public function test_rt_can_view_any_letter(): void
    {
        $otherLetter = Letter::factory()->submitted()->create();

        $response = $this->actingAs($this->rtUser)->getJson("/api/v1/letters/{$otherLetter->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ========== SIGNATURE HASH STORED ==========

    public function test_signature_hash_is_stored(): void
    {
        Storage::fake('public');
        $letter = Letter::factory()->approved()->create(['resident_id' => $this->resident->id]);
        $file = UploadedFile::fake()->image('sig.png', 100, 50);

        $this->actingAs($this->rtUser)->postJson("/api/v1/letters/{$letter->id}/sign", [
            'signature_image' => $file,
        ]);

        $signature = Signature::where('letter_id', $letter->id)->first();
        $this->assertNotEmpty($signature->signature_hash);
        $this->assertEquals(64, strlen($signature->signature_hash)); // SHA256 = 64 chars
    }

    // ========== FIELD VALUES SAVED ==========

    public function test_field_values_are_saved(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letters', [
            'letter_type_id' => $this->letterType->id,
            'purpose' => 'Test purpose',
            'fields' => [
                'keperluan' => 'Untuk administrasi kantor',
                'catatan' => 'Optional note',
            ],
        ]);

        $response->assertStatus(201);

        $letter = Letter::first();
        $this->assertCount(2, $letter->fieldValues);

        $keperluan = $letter->fieldValues->firstWhere('letterField.field_key', 'keperluan');
        $this->assertEquals('Untuk administrasi kantor', $keperluan->value);
    }
}
