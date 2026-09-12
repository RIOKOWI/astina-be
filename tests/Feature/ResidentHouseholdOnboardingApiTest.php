<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResidentHouseholdOnboardingApiTest extends TestCase
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

    // ==================== RESIDENT CREATION ====================

    public function test_rt_can_create_resident(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'birth_place' => 'Tangerang',
            'birth_date' => '1990-01-10',
            'gender' => 'male',
            'religion' => 'islam',
            'marital_status' => 'married',
            'occupation' => 'Karyawan Swasta',
            'last_education' => 'S1',
            'phone' => '081234567890',
            'joined_at' => '2026-09-06',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data warga berhasil ditambahkan.',
            ])
            ->assertJsonPath('data.nik', '3271234567890001')
            ->assertJsonPath('data.full_name', 'Budi Santoso')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.no_kk', null)
            ->assertJsonPath('data.left_at', null)
            ->assertJsonPath('data.account', null);

        $this->assertDatabaseHas('residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'status' => 'active',
        ]);
    }

    public function test_warga_cannot_create_resident(): void
    {
        $wargaUser = $this->makeWargaUser();

        $response = $this->actingAsUser($wargaUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $response->assertStatus(403);
    }

    public function test_bendahara_cannot_create_resident(): void
    {
        $bendaharaUser = $this->makeBendaharaUser();

        $response = $this->actingAsUser($bendaharaUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_resident(): void
    {
        $response = $this->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $response->assertUnauthorized();
    }

    public function test_duplicate_nik_returns_422(): void
    {
        $rtUser = $this->makeRtUser();
        Resident::factory()->create(['nik' => '3271234567890001']);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $response->assertStatus(422);
    }

    public function test_client_cannot_inject_status(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
            'status' => 'moved',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('residents', ['nik' => '3271234567890001']);
    }

    public function test_client_cannot_inject_no_kk(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
            'no_kk' => '3271234567890000',
        ]);

        $response->assertStatus(422);
    }

    public function test_client_cannot_inject_left_at(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
            'left_at' => '2026-09-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_client_cannot_inject_household_id(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
            'household_id' => 999,
        ]);

        $response->assertStatus(422);
    }

    public function test_resident_creation_does_not_create_user(): void
    {
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $this->assertDatabaseCount('users', 1); // Only the RT user from factory
    }

    public function test_resident_creation_does_not_create_household(): void
    {
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->postJson('/api/v1/residents', [
            'nik' => '3271234567890001',
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'marital_status' => 'single',
        ]);

        $this->assertDatabaseCount('households', 0);
    }

    // ==================== HOUSEHOLD CREATION ====================

    public function test_rt_can_create_household_with_active_resident_as_head(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Budi Santoso', 'status' => 'active']);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
            'postal_code' => '15111',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data keluarga berhasil dibuat.',
            ])
            ->assertJsonPath('data.no_kk', '3271234567890001')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.head_resident.full_name', 'Budi Santoso');

        $householdId = $response->json('data.id');
        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $resident->id,
            'household_id' => $householdId,
            'relationship' => 'head',
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'no_kk' => '3271234567890001',
        ]);
    }

    public function test_inactive_resident_cannot_become_head(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'inactive']);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('households', ['no_kk' => '3271234567890001']);
    }

    public function test_moved_resident_cannot_become_head(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'moved']);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
        ]);

        $response->assertStatus(409);
    }

    public function test_resident_with_existing_current_household_cannot_become_head(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        $existingHousehold = Household::factory()->create(['no_kk' => '1111111111111111']);
        ResidentHousehold::factory()->create([
            'resident_id' => $resident->id,
            'household_id' => $existingHousehold->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
        ]);

        $response->assertStatus(409);
    }

    public function test_duplicate_no_kk_returns_422(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Household::factory()->create(['no_kk' => '3271234567890001']);

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
        ]);

        $response->assertStatus(422);
    }

    public function test_warga_cannot_create_household(): void
    {
        $wargaUser = $this->makeWargaUser();
        $resident = Resident::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($wargaUser)->postJson('/api/v1/households', [
            'no_kk' => '3271234567890001',
            'head_resident_id' => $resident->id,
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '016',
        ]);

        $response->assertStatus(403);
    }

    // ==================== ADD MEMBER ====================

    public function test_rt_can_add_active_resident_to_active_household(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['full_name' => 'Pak RT', 'status' => 'active']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => '3271234567890001',
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $newMember = Resident::factory()->create(['full_name' => 'Siti Aminah', 'status' => 'active']);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $newMember->id,
            'relationship' => 'spouse',
            'joined_at' => '2026-09-06',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Anggota keluarga berhasil ditambahkan.',
            ])
            ->assertJsonPath('data.full_name', 'Siti Aminah')
            ->assertJsonPath('data.relationship', 'spouse')
            ->assertJsonPath('data.joined_at', '2026-09-06');

        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $newMember->id,
            'household_id' => $household->id,
            'relationship' => 'spouse',
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('residents', [
            'id' => $newMember->id,
            'no_kk' => '3271234567890001',
        ]);
    }

    public function test_resident_already_current_member_same_household_returns_409(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $member = Resident::factory()->create(['status' => 'active']);
        ResidentHousehold::factory()->create([
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $member->id,
            'relationship' => 'spouse',
        ]);

        $response->assertStatus(409);
    }

    public function test_resident_current_member_different_household_returns_409(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        $householdA = Household::factory()->create();
        ResidentHousehold::factory()->create([
            'resident_id' => $resident->id,
            'household_id' => $householdA->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $householdB = Household::factory()->create(['head_resident_id' => $this->createResident()]);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$householdB->id}/members", [
            'resident_id' => $resident->id,
            'relationship' => 'spouse',
        ]);

        $response->assertStatus(409);
    }

    public function test_inactive_resident_cannot_join(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $inactiveResident = Resident::factory()->create(['status' => 'inactive']);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $inactiveResident->id,
            'relationship' => 'spouse',
        ]);

        $response->assertStatus(409);
    }

    public function test_moved_resident_cannot_join(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $movedResident = Resident::factory()->create(['status' => 'moved']);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $movedResident->id,
            'relationship' => 'child',
        ]);

        $response->assertStatus(409);
    }

    public function test_inactive_household_cannot_receive_member(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'status' => 'inactive',
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $newMember = Resident::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $newMember->id,
            'relationship' => 'spouse',
        ]);

        $response->assertStatus(409);
    }

    public function test_warga_cannot_add_member(): void
    {
        $wargaUser = $this->makeWargaUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $newMember = Resident::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($wargaUser)->postJson("/api/v1/households/{$household->id}/members", [
            'resident_id' => $newMember->id,
            'relationship' => 'spouse',
        ]);

        $response->assertStatus(403);
    }

    // ==================== REMOVE MEMBER ====================

    public function test_rt_can_remove_ordinary_current_member(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $member = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => '3271234567890001',
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        $membership = ResidentHousehold::factory()->create([
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        $response = $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$household->id}/members/{$member->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Anggota keluarga berhasil dikeluarkan dari household.',
            ]);

        // Membership row still exists but is_current=false
        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'is_current' => 0,
        ]);

        // Resident still exists
        $this->assertDatabaseHas('residents', ['id' => $member->id]);

        // no_kk cleared
        $this->assertDatabaseHas('residents', ['id' => $member->id, 'no_kk' => null]);
    }

    public function test_remove_non_current_membership_returns_404(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $oldMember = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $oldMember->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => 0,
            'left_at' => now()->subDay(),
        ]);

        $response = $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$household->id}/members/{$oldMember->id}");

        $response->assertStatus(404);
    }

    public function test_remove_current_head_returns_409(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);

        $response = $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$household->id}/members/{$headResident->id}");

        $response->assertStatus(409);

        // Head remains current
        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'is_current' => true,
        ]);
    }

    public function test_warga_cannot_remove_member(): void
    {
        $wargaUser = $this->makeWargaUser();
        $headResident = Resident::factory()->create(['status' => 'active']);
        $member = Resident::factory()->create(['status' => 'active']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        $response = $this->actingAsUser($wargaUser)->deleteJson("/api/v1/households/{$household->id}/members/{$member->id}");

        $response->assertStatus(403);
    }

    // ==================== HISTORY & NO_KK SYNC ====================

    public function test_resident_can_join_another_household_after_leaving(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'active', 'no_kk' => '1111111111111111']);

        // Create head resident and old household
        $headResident = Resident::factory()->create(['status' => 'active']);
        $householdA = Household::factory()->create(['no_kk' => '1111111111111111', 'head_resident_id' => $headResident->id]);
        // Head membership
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $householdA->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        // Our resident as ordinary member (not head)
        ResidentHousehold::factory()->create([
            'resident_id' => $resident->id,
            'household_id' => $householdA->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        // Leave old household
        $response = $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$householdA->id}/members/{$resident->id}");
        $response->assertStatus(200);
        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $resident->id,
            'household_id' => $householdA->id,
            'is_current' => 0,
        ]);

        // Create new household with another resident as head
        $newHead = Resident::factory()->create(['status' => 'active']);
        $householdB = Household::factory()->create(['no_kk' => '2222222222222222', 'head_resident_id' => $newHead->id]);
        ResidentHousehold::factory()->create([
            'resident_id' => $newHead->id,
            'household_id' => $householdB->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);

        // Add resident to new household
        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/households/{$householdB->id}/members", [
            'resident_id' => $resident->id,
            'relationship' => 'child',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('resident_households', [
            'resident_id' => $resident->id,
            'household_id' => $householdB->id,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'no_kk' => '2222222222222222',
        ]);
    }

    public function test_leaving_does_not_change_resident_status(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $member = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id, 'no_kk' => '3271234567890001']);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$household->id}/members/{$member->id}");

        $this->assertDatabaseHas('residents', ['id' => $member->id, 'status' => 'active']);
    }

    public function test_leaving_does_not_deactivate_user(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $member = Resident::factory()->create(['status' => 'active', 'no_kk' => '3271234567890001']);
        $memberUser = User::factory()->create(['resident_id' => $member->id, 'is_active' => true]);
        $household = Household::factory()->create(['head_resident_id' => $headResident->id, 'no_kk' => '3271234567890001']);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $member->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);

        $this->actingAsUser($rtUser)->deleteJson("/api/v1/households/{$household->id}/members/{$member->id}");

        $this->assertDatabaseHas('users', ['id' => $memberUser->id, 'is_active' => true]);
    }

    public function test_household_no_kk_update_syncs_only_current_members(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['status' => 'active', 'no_kk' => '1111111111111111']);
        $currentMember = Resident::factory()->create(['status' => 'active', 'no_kk' => '1111111111111111']);
        $oldMember = Resident::factory()->create(['status' => 'active', 'no_kk' => '1111111111111111']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => '1111111111111111',
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $headResident->id,
            'household_id' => $household->id,
            'relationship' => 'head',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $currentMember->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => true,
        ]);
        ResidentHousehold::factory()->create([
            'resident_id' => $oldMember->id,
            'household_id' => $household->id,
            'relationship' => 'child',
            'is_current' => 0,
            'left_at' => now()->subDay(),
        ]);

        $this->actingAsUser($rtUser)->patchJson("/api/v1/households/{$household->id}", [
            'no_kk' => '2222222222222222',
        ]);

        // Current members updated
        $this->assertDatabaseHas('residents', ['id' => $headResident->id, 'no_kk' => '2222222222222222']);
        $this->assertDatabaseHas('residents', ['id' => $currentMember->id, 'no_kk' => '2222222222222222']);
        // Former member NOT updated
        $this->assertDatabaseHas('residents', ['id' => $oldMember->id, 'no_kk' => '1111111111111111']);
    }

    public function test_newly_created_resident_can_receive_account(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Budi Santoso']);
        Role::factory()->warga()->create();

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/residents/{$resident->id}/account", [
            'phone' => '081234567890',
            'email' => 'budi@astina.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'resident_id' => $resident->id,
            'phone' => '081234567890',
            'is_active' => true,
        ]);
    }

    // ==================== HELPERS ====================

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

    private function makeBendaharaUser(): User
    {
        $role = Role::factory()->bendahara()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function createResident(): Resident
    {
        return Resident::factory()->create();
    }
}
