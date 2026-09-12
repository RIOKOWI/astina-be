<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResidentApiTest extends TestCase
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

    // --- Resident List ---

    public function test_rt_can_view_resident_list(): void
    {
        $rtUser = $this->makeRtUser();
        Resident::factory()->count(3)->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/residents');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data warga berhasil diambil.'])
            ->assertJsonStructure([
                'success', 'message', 'data' => [['id', 'full_name', 'nik', 'phone', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_warga_cannot_view_resident_list(): void
    {
        $wargaUser = $this->makeWargaUser();
        Resident::factory()->count(2)->create();

        $response = $this->actingAsUser($wargaUser)->getJson('/api/v1/residents');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat daftar warga.',
            ]);
    }

    public function test_unauthenticated_cannot_view_resident_list(): void
    {
        $response = $this->getJson('/api/v1/residents');

        $response->assertUnauthorized();
    }

    public function test_resident_list_pagination(): void
    {
        $rtUser = $this->makeRtUser();
        Resident::factory()->count(20)->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/residents?per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonCount(5, 'data');
    }

    public function test_resident_list_search(): void
    {
        $rtUser = $this->makeRtUser();
        Resident::factory()->create(['full_name' => 'Budi Santoso']);
        Resident::factory()->create(['full_name' => 'Siti Aminah']);

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/residents?search=budi');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Budi Santoso', $response->json('data.0.full_name'));
    }

    public function test_resident_list_filter_by_status(): void
    {
        $rtUser = $this->makeRtUser();
        Resident::factory()->create(['status' => 'active']);
        Resident::factory()->create(['status' => 'inactive']);

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/residents?status=inactive');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('inactive', $response->json('data.0.status'));
    }

    // --- Resident Detail ---

    public function test_rt_can_view_resident_detail(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Andi Saputra']);

        $response = $this->actingAsUser($rtUser)->getJson("/api/v1/residents/{$resident->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data warga berhasil diambil.',
            ])
            ->assertJsonPath('data.id', $resident->id)
            ->assertJsonPath('data.full_name', 'Andi Saputra')
            ->assertJsonStructure([
                'data' => ['id', 'nik', 'full_name', 'birth_place', 'birth_date', 'gender', 'religion', 'occupation', 'marital_status', 'phone', 'email', 'status'],
            ]);
    }

    public function test_warga_cannot_view_resident_detail(): void
    {
        $wargaUser = $this->makeWargaUser();
        $resident = Resident::factory()->create();

        $response = $this->actingAsUser($wargaUser)->getJson("/api/v1/residents/{$resident->id}");

        $response->assertStatus(403);
    }

    public function test_resident_not_found_returns_404(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/residents/99999');

        $response->assertStatus(404);
    }

    // --- My Resident ---

    public function test_warga_can_view_own_resident(): void
    {
        $resident = Resident::factory()->create(['full_name' => 'Budi Santoso']);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/resident');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.full_name', 'Budi Santoso');
    }

    public function test_user_without_resident_returns_null_data(): void
    {
        $user = $this->makeWargaUser();

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/resident');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    // --- My Household ---

    public function test_warga_can_view_own_household(): void
    {
        $headResident = Resident::factory()->create(['full_name' => 'Budi Santoso']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => '3271234567890001',
        ]);
        $headResident->households()->attach($household, ['relationship' => 'head', 'is_current' => true]);
        $user = $this->makeUserWithResident($headResident, 'warga');

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/household');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.no_kk', '3271234567890001')
            ->assertJsonStructure([
                'data' => ['id', 'no_kk', 'address', 'rt', 'rw', 'postal_code', 'status', 'head_resident' => ['id', 'full_name', 'phone'], 'members'],
            ]);
    }

    public function test_warga_without_household_returns_null(): void
    {
        $resident = Resident::factory()->create();
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/household');

        $response->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_user_without_resident_cannot_view_household(): void
    {
        $user = $this->makeWargaUser();

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/household');

        $response->assertOk()
            ->assertJson(['data' => null]);
    }

    // --- Household List ---

    public function test_rt_can_view_household_list(): void
    {
        $rtUser = $this->makeRtUser();
        Household::factory()->count(3)->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/households');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [['id', 'no_kk', 'address', 'rt', 'rw', 'postal_code', 'status', 'head_resident', 'member_count']],
                'meta',
            ]);
    }

    public function test_warga_cannot_view_household_list(): void
    {
        $wargaUser = $this->makeWargaUser();
        Household::factory()->count(2)->create();

        $response = $this->actingAsUser($wargaUser)->getJson('/api/v1/households');

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    // --- Household Detail ---

    public function test_rt_can_view_household_detail(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['full_name' => 'Pak RT']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => '3271234567890002',
        ]);
        $headResident->households()->attach($household, ['relationship' => 'head', 'is_current' => true]);

        $response = $this->actingAsUser($rtUser)->getJson("/api/v1/households/{$household->id}");

        $response->assertOk()
            ->assertJsonPath('data.no_kk', '3271234567890002')
            ->assertJsonPath('data.head_resident.full_name', 'Pak RT');
    }

    public function test_warga_cannot_view_household_detail(): void
    {
        $wargaUser = $this->makeWargaUser();
        $household = Household::factory()->create();

        $response = $this->actingAsUser($wargaUser)->getJson("/api/v1/households/{$household->id}");

        $response->assertStatus(403);
    }

    public function test_household_not_found_returns_404(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/households/99999');

        $response->assertStatus(404);
    }

    // --- Current household members only ---

    public function test_household_shows_only_current_members(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create(['full_name' => 'Pak RT']);
        $currentMember = Resident::factory()->create(['full_name' => 'Current Member']);
        $oldMember = Resident::factory()->create(['full_name' => 'Old Member']);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
        ]);
        $headResident->households()->attach($household, ['relationship' => 'head', 'is_current' => true]);
        $currentMember->households()->attach($household, ['relationship' => 'child', 'is_current' => true]);
        $oldMember->households()->attach($household, ['relationship' => 'child', 'is_current' => false]);

        $response = $this->actingAsUser($rtUser)->getJson("/api/v1/households/{$household->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.members'));
        $names = collect($response->json('data.members'))->pluck('full_name')->toArray();
        $this->assertContains('Pak RT', $names);
        $this->assertContains('Current Member', $names);
        $this->assertNotContains('Old Member', $names);
    }

    // --- Helper methods ---

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

    private function makeUserWithResident(Resident $resident, string $roleCode): User
    {
        $role = Role::factory()->{$roleCode}()->create();
        $user = User::factory()->create([
            'resident_id' => $resident->id,
            'password' => Hash::make('password'),
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
