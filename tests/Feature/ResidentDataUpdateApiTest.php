<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResidentDataUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneCounter = 0;

    private int $nikCounter = 3200000000000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        $this->phoneCounter = 0;
        $this->nikCounter = 3200000000000000;
    }

    private function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function nextPhone(): string
    {
        return '08'.str_pad((string) (++$this->phoneCounter), 10, '0', STR_PAD_LEFT);
    }

    private function nextNik(): string
    {
        return (string) ($this->nikCounter++);
    }

    private function makeRtUser(): User
    {
        $role = Role::factory()->create(['name' => 'RT '.\uniqid(), 'code' => 'rt']);
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeWargaUser(): User
    {
        $role = Role::factory()->create(['name' => 'Warga '.\uniqid(), 'code' => 'warga']);
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeUserWithResident(Resident $resident, string $roleCode = 'warga'): User
    {
        $role = Role::factory()->create(['name' => ucfirst($roleCode).' '.\uniqid(), 'code' => $roleCode]);
        $user = User::factory()->create([
            'resident_id' => $resident->id,
            'password' => Hash::make('password'),
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    // =========================================================
    // PATCH /api/v1/auth/me — warga update account phone/email
    // =========================================================

    public function test_warga_can_update_account_phone_with_password_verification(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $newPhone = $this->nextPhone();

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $newPhone,
            'current_password' => 'password',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals($newPhone, $response->json('data.phone'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => $newPhone]);
    }

    public function test_warga_can_update_account_email_with_password_verification(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'email' => 'new@example.com',
            'current_password' => 'password',
        ]);

        $response->assertOk();
        $this->assertEquals('new@example.com', $response->json('data.email'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
    }

    public function test_warga_can_update_phone_and_email_together(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $newPhone = $this->nextPhone();

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $newPhone,
            'email' => 'new@example.com',
            'current_password' => 'password',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => $newPhone,
            'email' => 'new@example.com',
        ]);
    }

    public function test_update_account_requires_password_when_changing(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $this->nextPhone(),
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_update_account_rejects_wrong_password(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $this->nextPhone(),
            'current_password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Password saat ini salah', $response->json('errors.current_password.0'));
    }

    public function test_update_account_without_changes_does_not_require_password(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $user->phone,
            'email' => $user->email,
        ]);

        $response->assertOk();
    }

    public function test_update_account_phones_synced_to_linked_resident(): void
    {
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $user = $this->makeUserWithResident($resident, 'warga');
        $newPhone = $this->nextPhone();

        $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $newPhone,
            'email' => 'synced@example.com',
            'current_password' => 'password',
        ]);

        $resident->refresh();
        $this->assertEquals($newPhone, $resident->phone);
        $this->assertEquals('synced@example.com', $resident->email);
    }

    public function test_update_account_phone_must_be_unique(): void
    {
        $existingPhone = $this->nextPhone();
        User::factory()->create(['phone' => $existingPhone]);
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'phone' => $existingPhone,
            'current_password' => 'password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Nomor HP sudah digunakan', $response->json('errors.phone.0'));
    }

    public function test_update_account_email_must_be_unique(): void
    {
        $existingEmail = 'existing-'.uniqid().'@example.com';
        User::factory()->create(['email' => $existingEmail]);
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/me', [
            'email' => $existingEmail,
            'current_password' => 'password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Email sudah digunakan', $response->json('errors.email.0'));
    }

    public function test_update_account_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/auth/me', [
            'phone' => $this->nextPhone(),
            'current_password' => 'password',
        ]);

        $response->assertUnauthorized();
    }

    // =========================================================
    // PATCH /api/v1/auth/password — warga change password
    // =========================================================

    public function test_warga_can_change_password(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Password saat ini salah', $response->json('errors.current_password.0'));
    }

    public function test_change_password_requires_confirmation(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'different-secret',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Konfirmasi password tidak cocok', $response->json('errors.password.0'));
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertUnauthorized();
    }

    // =========================================================
    // PATCH /api/v1/me/resident — warga update own resident data
    // =========================================================

    public function test_warga_can_update_own_resident(): void
    {
        $resident = Resident::factory()->create([
            'full_name' => 'Budi Santoso',
            'occupation' => 'Guru',
            'last_education' => null,
        ]);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/me/resident', [
            'full_name' => 'Budi Santoso Updated',
            'occupation' => 'Guru SD',
            'last_education' => 'S1',
        ]);

        $response->assertOk();
        $this->assertEquals('Budi Santoso Updated', $response->json('data.full_name'));
        $this->assertEquals('Guru SD', $response->json('data.occupation'));
        $this->assertEquals('S1', $response->json('data.last_education'));
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'full_name' => 'Budi Santoso Updated',
            'last_education' => 'S1',
        ]);
    }

    public function test_warga_can_update_nik(): void
    {
        $resident = Resident::factory()->create(['nik' => $this->nextNik()]);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/me/resident', [
            'nik' => $this->nextNik(),
        ]);

        $response->assertOk();
    }

    public function test_warga_can_update_birth_info(): void
    {
        $resident = Resident::factory()->create([
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-15',
        ]);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/me/resident', [
            'birth_place' => 'Jakarta',
            'birth_date' => '1991-06-20',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'birth_place' => 'Jakarta',
            'birth_date' => '1991-06-20',
        ]);
    }

    public function test_warga_can_update_gender_and_marital_status(): void
    {
        $resident = Resident::factory()->create([
            'gender' => 'male',
            'marital_status' => 'single',
        ]);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/me/resident', [
            'gender' => 'female',
            'marital_status' => 'married',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'gender' => 'female',
            'marital_status' => 'married',
        ]);
    }

    public function test_warga_cannot_update_disallowed_fields(): void
    {
        $resident = Resident::factory()->create(['status' => 'active']);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->patchJson('/api/v1/me/resident', [
            'status' => 'inactive',
            'phone' => $this->nextPhone(),
            'email' => 'hack@example.com',
            'resident_id' => 999,
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('errors'));
    }

    public function test_warga_nik_must_be_unique(): void
    {
        $nik = $this->nextNik();
        $resident1 = Resident::factory()->create(['nik' => $nik]);
        $resident2Nik = $this->nextNik();
        $resident2 = Resident::factory()->create(['nik' => $resident2Nik]);
        $user1 = $this->makeUserWithResident($resident1, 'warga');

        $response = $this->actingAsUser($user1)->patchJson('/api/v1/me/resident', [
            'nik' => $resident2Nik,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_own_resident_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/me/resident', [
            'full_name' => 'Hacked',
        ]);

        $response->assertUnauthorized();
    }

    // =========================================================
    // PATCH /api/v1/residents/{id} — RT update resident
    // =========================================================

    public function test_rt_can_update_resident(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Old Name', 'occupation' => 'Petani']);

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'full_name' => 'New Name',
            'occupation' => 'Dokter',
            'last_education' => 'S2',
        ]);

        $response->assertOk();
        $this->assertEquals('New Name', $response->json('data.full_name'));
        $this->assertEquals('Dokter', $response->json('data.occupation'));
        $this->assertEquals('S2', $response->json('data.last_education'));
    }

    public function test_rt_can_update_resident_status(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('residents', ['id' => $resident->id, 'status' => 'inactive']);
    }

    public function test_rt_cannot_update_phone_on_resident_with_linked_user(): void
    {
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $this->makeUserWithResident($resident, 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'phone' => $this->nextPhone(),
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('errors'));
    }

    public function test_rt_cannot_update_email_on_resident_with_linked_user(): void
    {
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $this->makeUserWithResident($resident, 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'email' => 'hack@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_rt_can_update_phone_on_resident_without_linked_user(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $newPhone = $this->nextPhone();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'phone' => $newPhone,
            'email' => 'resident@example.com',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'phone' => $newPhone,
            'email' => 'resident@example.com',
        ]);
    }

    public function test_warga_cannot_update_resident(): void
    {
        $wargaUser = $this->makeWargaUser();
        $resident = Resident::factory()->create();

        $response = $this->actingAsUser($wargaUser)->patchJson("/api/v1/residents/{$resident->id}", [
            'full_name' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_update_resident(): void
    {
        $resident = Resident::factory()->create();

        $response = $this->patchJson("/api/v1/residents/{$resident->id}", [
            'full_name' => 'Hacked',
        ]);

        $response->assertUnauthorized();
    }

    public function test_resident_update_returns_404_for_nonexistent(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson('/api/v1/residents/99999', [
            'full_name' => 'Ghost',
        ]);

        $response->assertStatus(404);
    }

    // =========================================================
    // PATCH /api/v1/households/{id} — RT update household
    // =========================================================

    public function test_rt_can_update_household(): void
    {
        $rtUser = $this->makeRtUser();
        $headResident = Resident::factory()->create();
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => $this->nextNik(),
            'address' => 'Jl. Lama No. 1',
        ]);
        $headResident->households()->attach($household, ['relationship' => 'head', 'is_current' => true]);

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/households/{$household->id}", [
            'address' => 'Jl. Baru No. 2',
            'rt' => '006',
        ]);

        $response->assertOk();
        $this->assertEquals('Jl. Baru No. 2', $response->json('data.address'));
        $this->assertEquals('006', $response->json('data.rt'));
    }

    public function test_rt_update_household_no_kk_mirrors_to_current_members(): void
    {
        $rtUser = $this->makeRtUser();
        $noKk = $this->nextNik();
        $headResident = Resident::factory()->create(['no_kk' => $noKk]);
        $member1 = Resident::factory()->create(['no_kk' => $noKk]);
        $member2 = Resident::factory()->create(['no_kk' => $noKk]);
        $household = Household::factory()->create([
            'head_resident_id' => $headResident->id,
            'no_kk' => $noKk,
        ]);
        $headResident->households()->attach($household, ['relationship' => 'head', 'is_current' => true]);
        $member1->households()->attach($household, ['relationship' => 'spouse', 'is_current' => true]);
        $member2->households()->attach($household, ['relationship' => 'child', 'is_current' => false]);
        $newNoKk = $this->nextNik();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/households/{$household->id}", [
            'no_kk' => $newNoKk,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('households', ['id' => $household->id, 'no_kk' => $newNoKk]);
        $this->assertDatabaseHas('residents', ['id' => $headResident->id, 'no_kk' => $newNoKk]);
        $this->assertDatabaseHas('residents', ['id' => $member1->id, 'no_kk' => $newNoKk]);
        // Non-current member should NOT be updated
        $this->assertDatabaseHas('residents', ['id' => $member2->id, 'no_kk' => $noKk]);
    }

    public function test_warga_cannot_update_household(): void
    {
        $wargaUser = $this->makeWargaUser();
        $headResident = Resident::factory()->create();
        $household = Household::factory()->create(['head_resident_id' => $headResident->id]);

        $response = $this->actingAsUser($wargaUser)->patchJson("/api/v1/households/{$household->id}", [
            'address' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_update_household(): void
    {
        $household = Household::factory()->create();

        $response = $this->patchJson("/api/v1/households/{$household->id}", [
            'address' => 'Hacked',
        ]);

        $response->assertUnauthorized();
    }

    public function test_household_update_returns_404_for_nonexistent(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson('/api/v1/households/99999', [
            'address' => 'Ghost',
        ]);

        $response->assertStatus(404);
    }

    public function test_household_no_kk_must_be_unique(): void
    {
        $rtUser = $this->makeRtUser();
        $noKk = $this->nextNik();
        $head1 = Resident::factory()->create();
        $head2 = Resident::factory()->create();
        Household::factory()->create(['head_resident_id' => $head1->id, 'no_kk' => $noKk]);
        $household2 = Household::factory()->create(['head_resident_id' => $head2->id, 'no_kk' => $this->nextNik()]);

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/households/{$household2->id}", [
            'no_kk' => $noKk,
        ]);

        $response->assertStatus(422);
    }

    // =========================================================
    // GET /api/v1/users — RT list users
    // =========================================================

    public function test_rt_can_list_users(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Budi']);
        $this->makeUserWithResident($resident, 'warga');
        User::factory()->count(3)->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'phone', 'email', 'is_active', 'roles', 'resident']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_warga_cannot_list_users(): void
    {
        $wargaUser = $this->makeWargaUser();

        $response = $this->actingAsUser($wargaUser)->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertUnauthorized();
    }

    public function test_user_list_pagination(): void
    {
        $rtUser = $this->makeRtUser();
        User::factory()->count(15)->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, $response->json('meta.per_page'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_user_list_search(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Siti Aminah']);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users?search=siti');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($user->id, $response->json('data.0.id'));
    }

    public function test_user_list_filter_by_is_active(): void
    {
        $rtUser = $this->makeRtUser();
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->inactive()->create();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users?is_active=0');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_list_filter_by_role(): void
    {
        $rtUser = $this->makeRtUser();
        $wargaRole = Role::factory()->create(['name' => 'Warga List', 'code' => 'warga']);
        $warga = User::factory()->create();
        $warga->roles()->attach($wargaRole);

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users?role=warga');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // =========================================================
    // GET /api/v1/users/{id} — RT view user detail
    // =========================================================

    public function test_rt_can_view_user_detail(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['full_name' => 'Budi']);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($rtUser)->getJson("/api/v1/users/{$user->id}");

        $response->assertOk();
        $this->assertEquals($user->id, $response->json('data.id'));
        $this->assertEquals('Budi', $response->json('data.resident.full_name'));
    }

    public function test_warga_cannot_view_user_detail(): void
    {
        $wargaUser = $this->makeWargaUser();
        $target = User::factory()->create();

        $response = $this->actingAsUser($wargaUser)->getJson("/api/v1/users/{$target->id}");

        $response->assertStatus(403);
    }

    public function test_user_detail_returns_404_for_nonexistent(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->getJson('/api/v1/users/99999');

        $response->assertStatus(404);
    }

    // =========================================================
    // PATCH /api/v1/users/{id} — RT update user
    // =========================================================

    public function test_rt_can_update_user_phone_and_email(): void
    {
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $user = $this->makeUserWithResident($resident, 'warga');
        $rtUser = $this->makeRtUser();
        $newPhone = $this->nextPhone();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'phone' => $newPhone,
            'email' => 'updated@example.com',
        ]);

        $response->assertOk();
        $this->assertEquals($newPhone, $response->json('data.phone'));
        $this->assertEquals('updated@example.com', $response->json('data.email'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => $newPhone,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_rt_update_user_phone_syncs_to_linked_resident(): void
    {
        $resident = Resident::factory()->create(['phone' => null, 'email' => null]);
        $user = $this->makeUserWithResident($resident, 'warga');
        $rtUser = $this->makeRtUser();
        $newPhone = $this->nextPhone();

        $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'phone' => $newPhone,
            'email' => 'synced@example.com',
        ]);

        $resident->refresh();
        $this->assertEquals($newPhone, $resident->phone);
        $this->assertEquals('synced@example.com', $resident->email);
    }

    public function test_rt_can_deactivate_user(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.is_active'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_rt_can_reactivate_user(): void
    {
        $user = User::factory()->inactive()->create();
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'is_active' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.is_active'));
    }

    public function test_rt_deactivate_user_revokes_tokens(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $user->createToken('test-token');
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'is_active' => false,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_rt_deactivate_user_revokes_device_tokens(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        DeviceToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null]);
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'is_active' => false,
        ]);

        $this->assertNotNull(
            DeviceToken::where('user_id', $user->id)->first()?->revoked_at
        );
    }

    public function test_rt_cannot_deactivate_own_account(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$rtUser->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Anda tidak dapat menonaktifkan', $response->json('errors.is_active.0'));
    }

    public function test_rt_update_user_phone_must_be_unique(): void
    {
        $existingPhone = $this->nextPhone();
        User::factory()->create(['phone' => $existingPhone]);
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson("/api/v1/users/{$user->id}", [
            'phone' => $existingPhone,
        ]);

        $response->assertStatus(422);
    }

    public function test_warga_cannot_update_user(): void
    {
        $wargaUser = $this->makeWargaUser();
        $target = User::factory()->create();

        $response = $this->actingAsUser($wargaUser)->patchJson("/api/v1/users/{$target->id}", [
            'phone' => $this->nextPhone(),
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_update_user(): void
    {
        $target = User::factory()->create();

        $response = $this->patchJson("/api/v1/users/{$target->id}", [
            'phone' => $this->nextPhone(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_nonexistent_user_returns_404(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->patchJson('/api/v1/users/99999', [
            'phone' => $this->nextPhone(),
        ]);

        $response->assertStatus(404);
    }

    // =========================================================
    // POST /api/v1/users/{id}/reset-password — RT reset password
    // =========================================================

    public function test_rt_can_reset_user_password(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'new-reset-password',
            'password_confirmation' => 'new-reset-password',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $user->refresh();
        $this->assertTrue(Hash::check('new-reset-password', $user->password));
    }

    public function test_rt_reset_password_revokes_all_tokens(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $user->createToken('token1');
        $user->createToken('token2');
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'new-reset-password',
            'password_confirmation' => 'new-reset-password',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_rt_reset_password_revokes_device_tokens(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        DeviceToken::factory()->create(['user_id' => $user->id, 'revoked_at' => null]);
        $rtUser = $this->makeRtUser();

        $this->actingAsUser($rtUser)->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'new-reset-password',
            'password_confirmation' => 'new-reset-password',
        ]);

        $this->assertNotNull(
            DeviceToken::where('user_id', $user->id)->first()?->revoked_at
        );
    }

    public function test_warga_cannot_reset_password(): void
    {
        $wargaUser = $this->makeWargaUser();
        $target = User::factory()->create();

        $response = $this->actingAsUser($wargaUser)->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_reset_password(): void
    {
        $target = User::factory()->create();

        $response = $this->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_reset_password_nonexistent_user_returns_404(): void
    {
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson('/api/v1/users/99999/reset-password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(404);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $user = $this->makeUserWithResident(Resident::factory()->create(), 'warga');
        $rtUser = $this->makeRtUser();

        $response = $this->actingAsUser($rtUser)->postJson("/api/v1/users/{$user->id}/reset-password", [
            'password' => 'new-password',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Konfirmasi password tidak cocok', $response->json('errors.password.0'));
    }

    // =========================================================
    // last_education in responses
    // =========================================================

    public function test_resident_detail_includes_last_education(): void
    {
        $rtUser = $this->makeRtUser();
        $resident = Resident::factory()->create(['last_education' => 'S2']);

        $response = $this->actingAsUser($rtUser)->getJson("/api/v1/residents/{$resident->id}");

        $response->assertOk();
        $this->assertEquals('S2', $response->json('data.last_education'));
    }

    public function test_resident_resource_includes_last_education(): void
    {
        $resident = Resident::factory()->create(['last_education' => 'D3']);
        $user = $this->makeUserWithResident($resident, 'warga');

        $response = $this->actingAsUser($user)->getJson('/api/v1/me/resident');

        $response->assertOk();
        $this->assertEquals('D3', $response->json('data.last_education'));
    }
}
