<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Media;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentDocumentAccessApiTest extends TestCase
{
    use RefreshDatabase;

    private User $rtUser;

    private User $wargaUser;

    private Resident $resident;

    private Household $household;

    private Role $rtRole;

    private Role $wargaRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rtRole = Role::create(['name' => 'RT', 'code' => 'rt']);
        $this->wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);

        $this->resident = Resident::factory()->create();
        $this->household = Household::factory()->create();
        ResidentHousehold::create([
            'resident_id' => $this->resident->id,
            'household_id' => $this->household->id,
            'relationship' => 'spouse',
            'is_current' => true,
            'joined_at' => now(),
        ]);

        $this->rtUser = User::factory()->create();
        $this->rtUser->roles()->attach($this->rtRole);

        $this->wargaUser = User::factory()->create();
        $this->wargaUser->roles()->attach($this->wargaRole);
    }

    public function test_rt_can_download_ktp_of_any_resident(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('residents/1/documents/ktp/ktp.jpg', 'fake-image-data');

        Media::create([
            'model_type' => Resident::class,
            'model_id' => $this->resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'private',
            'path' => 'residents/1/documents/ktp/ktp.jpg',
            'file_name' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->actingAs($this->rtUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/ktp/file");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="ktp.jpg"')
            ->assertHeader('Content-Length', '1024');
    }

    public function test_rt_can_download_kk_of_any_resident(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('households/1/documents/kk/kk.jpg', 'fake-kk-data');

        Media::create([
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'private',
            'path' => 'households/1/documents/kk/kk.jpg',
            'file_name' => 'kk.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
        ]);

        $response = $this->actingAs($this->rtUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/kk/file");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="kk.jpg"')
            ->assertHeader('Content-Length', '2048');
    }

    public function test_rt_gets_404_when_ktp_not_uploaded(): void
    {
        $response = $this->actingAs($this->rtUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/ktp/file");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Dokumen KTP tidak ditemukan.');
    }

    public function test_rt_gets_404_when_no_current_household(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('households/1/documents/kk/kk.jpg', 'fake-kk-data');

        Media::create([
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'private',
            'path' => 'households/1/documents/kk/kk.jpg',
            'file_name' => 'kk.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
        ]);

        ResidentHousehold::where('resident_id', $this->resident->id)->update(['is_current' => false]);

        $response = $this->actingAs($this->rtUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/kk/file");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Household tidak ditemukan.');
    }

    public function test_rt_gets_404_when_kk_not_uploaded(): void
    {
        $response = $this->actingAs($this->rtUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/kk/file");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Dokumen KK tidak ditemukan.');
    }

    public function test_warga_cannot_download_ktp_via_resident_endpoint(): void
    {
        $response = $this->actingAs($this->wargaUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/ktp/file");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak memiliki akses untuk melihat data warga.');
    }

    public function test_warga_cannot_download_kk_via_resident_endpoint(): void
    {
        $response = $this->actingAs($this->wargaUser)
            ->get("/api/v1/residents/{$this->resident->id}/documents/kk/file");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak memiliki akses untuk melihat data warga.');
    }

    public function test_unauthenticated_cannot_download_ktp(): void
    {
        $response = $this->getJson("/api/v1/residents/{$this->resident->id}/documents/ktp/file");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_cannot_download_kk(): void
    {
        $response = $this->getJson("/api/v1/residents/{$this->resident->id}/documents/kk/file");

        $response->assertStatus(401);
    }

    public function test_resident_detail_includes_ktp_metadata(): void
    {
        Storage::fake('private');

        Media::create([
            'model_type' => Resident::class,
            'model_id' => $this->resident->id,
            'collection' => Media::COLLECTION_KTP,
            'disk' => 'private',
            'path' => 'residents/1/documents/ktp/ktp.jpg',
            'file_name' => 'ktp.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->actingAs($this->rtUser)
            ->getJson("/api/v1/residents/{$this->resident->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ktp.exists', true)
            ->assertJsonPath('data.ktp.file_name', 'ktp.jpg')
            ->assertJsonPath('data.ktp.mime_type', 'image/jpeg')
            ->assertJsonPath('data.ktp.file_size', 1024)
            ->assertJsonPath('data.ktp.url', "/api/v1/residents/{$this->resident->id}/documents/ktp/file");
    }

    public function test_resident_detail_includes_kk_metadata(): void
    {
        Storage::fake('private');

        Media::create([
            'model_type' => Household::class,
            'model_id' => $this->household->id,
            'collection' => Media::COLLECTION_KK,
            'disk' => 'private',
            'path' => 'households/1/documents/kk/kk.jpg',
            'file_name' => 'kk.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
        ]);

        $response = $this->actingAs($this->rtUser)
            ->getJson("/api/v1/residents/{$this->resident->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kk.exists', true)
            ->assertJsonPath('data.kk.file_name', 'kk.jpg')
            ->assertJsonPath('data.kk.no_kk', $this->household->no_kk)
            ->assertJsonPath('data.kk.mime_type', 'image/jpeg')
            ->assertJsonPath('data.kk.file_size', 2048)
            ->assertJsonPath('data.kk.url', "/api/v1/residents/{$this->resident->id}/documents/kk/file");
    }

    public function test_resident_detail_shows_no_ktp_when_not_uploaded(): void
    {
        $response = $this->actingAs($this->rtUser)
            ->getJson("/api/v1/residents/{$this->resident->id}");

        $response->assertOk()
            ->assertJsonPath('data.ktp.exists', false);
    }

    public function test_resident_detail_shows_no_kk_when_not_uploaded(): void
    {
        $response = $this->actingAs($this->rtUser)
            ->getJson("/api/v1/residents/{$this->resident->id}");

        $response->assertOk()
            ->assertJsonPath('data.kk.exists', false);
    }
}
