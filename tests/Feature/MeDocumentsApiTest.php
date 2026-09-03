<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Media;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Resident $resident;

    private Household $household;

    private Role $wargaRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);
        $this->resident = Resident::factory()->create(['nik' => '1234567890123456']);
        $this->user = User::factory()->create(['resident_id' => $this->resident->id]);
        $this->user->roles()->attach($this->wargaRole);

        $this->household = Household::factory()->create(['no_kk' => '3210987654321098']);
        ResidentHousehold::create([
            'resident_id' => $this->resident->id,
            'household_id' => $this->household->id,
            'relationship' => 'spouse',
            'is_current' => true,
            'joined_at' => now(),
        ]);
    }

    // ========== GET /me/documents TESTS ==========

    public function test_authenticated_user_can_get_documents_with_ktp_and_kk(): void
    {
        Storage::fake('public');

        Media::create([
            'model_type' => Resident::class,
            'model_id' => $this->resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'public',
            'path' => 'residents/1/documents/ktp/test.jpg',
            'file_name' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Media::create([
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'public',
            'path' => 'households/1/documents/kk/test.jpg',
            'file_name' => 'kk.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/me/documents');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resident.nik', '1234567890123456')
            ->assertJsonPath('data.ktp.file_name', 'ktp.jpg')
            ->assertJsonPath('data.household.no_kk', '3210987654321098')
            ->assertJsonPath('data.kk.file_name', 'kk.jpg');
    }

    public function test_returns_null_when_no_ktp_uploaded(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->getJson('/api/v1/me/documents');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ktp', null)
            ->assertJsonPath('data.kk', null)
            ->assertJsonPath('data.household.no_kk', '3210987654321098');
    }

    public function test_returns_null_when_no_current_household(): void
    {
        Storage::fake('public');
        ResidentHousehold::where('resident_id', $this->resident->id)->update(['is_current' => false]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/me/documents');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.household', null)
            ->assertJsonPath('data.kk', null)
            ->assertJsonPath('data.resident.nik', '1234567890123456');
    }

    public function test_user_without_resident_returns_null_data(): void
    {
        $userNoResident = User::factory()->create(['resident_id' => null]);
        $userNoResident->roles()->attach($this->wargaRole);

        $response = $this->actingAs($userNoResident)->getJson('/api/v1/me/documents');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me/documents');

        $response->assertStatus(401);
    }

    // ========== KTP UPLOAD TESTS ==========

    public function test_user_can_upload_ktp(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('ktp.jpg', 800, 600);

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.file_name', 'ktp.jpg')
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        $this->assertDatabaseHas('media', [
            'model_type' => Resident::class,
            'model_id' => $this->resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'public',
        ]);
    }

    public function test_upload_ktp_invalid_mime_returns_422(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_ktp_oversized_returns_422(): void
    {
        $file = UploadedFile::fake()->image('ktp.jpg')->size(11000); // > 10MB

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_ktp_replaces_existing(): void
    {
        Storage::fake('public');

        Media::create([
            'model_type' => Resident::class,
            'model_id' => $this->resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'public',
            'path' => 'residents/1/documents/ktp/old.jpg',
            'file_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $newFile = UploadedFile::fake()->image('new_ktp.jpg');

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/ktp', [
            'file' => $newFile,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.file_name', 'new_ktp.jpg');

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['file_name' => 'new_ktp.jpg']);
    }

    public function test_user_cannot_upload_ktp_for_another_resident(): void
    {
        Storage::fake('public');

        $otherResident = Resident::factory()->create();
        $otherUser = User::factory()->create(['resident_id' => $otherResident->id]);

        $file = UploadedFile::fake()->image('ktp.jpg');

        $response = $this->actingAs($otherUser)->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertOk();

        // Media must belong to the authenticated user's resident, not the other
        $media = Media::where('collection', Media::COLLECTION_KTP)->first();
        $this->assertEquals($otherResident->id, $media->model_id);
        $this->assertNotEquals($this->resident->id, $media->model_id);
    }

    public function test_user_without_resident_cannot_upload_ktp(): void
    {
        Storage::fake('public');
        $userNoResident = User::factory()->create(['resident_id' => null]);
        $userNoResident->roles()->attach($this->wargaRole);
        $file = UploadedFile::fake()->image('ktp.jpg');

        $response = $this->actingAs($userNoResident)->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Anda belum memiliki data resident.');
    }

    public function test_unauthenticated_cannot_upload_ktp(): void
    {
        $file = UploadedFile::fake()->image('ktp.jpg');

        $response = $this->postJson('/api/v1/me/documents/ktp', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    // ========== KK UPLOAD TESTS ==========

    public function test_user_can_upload_kk(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('kk.jpg', 800, 600);

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.file_name', 'kk.jpg');

        $this->assertDatabaseHas('media', [
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'public',
        ]);
    }

    public function test_upload_kk_invalid_mime_returns_422(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_kk_oversized_returns_422(): void
    {
        $file = UploadedFile::fake()->image('kk.jpg')->size(11000);

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_without_current_household_cannot_upload_kk(): void
    {
        Storage::fake('public');
        ResidentHousehold::where('resident_id', $this->resident->id)->update(['is_current' => false]);

        $file = UploadedFile::fake()->image('kk.jpg');

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_upload_kk_replaces_existing(): void
    {
        Storage::fake('public');

        Media::create([
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'public',
            'path' => 'households/1/documents/kk/old.jpg',
            'file_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $newFile = UploadedFile::fake()->image('new_kk.jpg');

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $newFile,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.file_name', 'new_kk.jpg');

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['file_name' => 'new_kk.jpg']);
    }

    public function test_user_without_resident_cannot_upload_kk(): void
    {
        Storage::fake('public');
        $userNoResident = User::factory()->create(['resident_id' => null]);
        $userNoResident->roles()->attach($this->wargaRole);
        $file = UploadedFile::fake()->image('kk.jpg');

        $response = $this->actingAs($userNoResident)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_cannot_upload_kk(): void
    {
        $file = UploadedFile::fake()->image('kk.jpg');

        $response = $this->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_upload_kk_to_another_household(): void
    {
        Storage::fake('public');

        // User's own current household
        $file = UploadedFile::fake()->image('kk.jpg');

        $response = $this->actingAs($this->user)->postJson('/api/v1/me/documents/kk', [
            'file' => $file,
        ]);

        $response->assertOk();

        // KK must belong to one of the user's current households, never an arbitrary one
        $media = Media::where('collection', Media::COLLECTION_KK)->first();
        $userCurrentHouseholdIds = $this->resident->fresh()
            ->households()
            ->wherePivot('is_current', true)
            ->wherePivotNull('resident_households.left_at')
            ->pluck('households.id')
            ->toArray();

        $this->assertContains($media->model_id, $userCurrentHouseholdIds);
    }
}
