<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResidentAccountProvisioningApiTest extends TestCase
{
    use RefreshDatabase;

    protected function rtUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->rt()->create());

        return $user;
    }

    protected function wargaUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->warga()->create());

        return $user;
    }

    protected function bendaharaUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->bendahara()->create());

        return $user;
    }

    public function test_rt_can_create_account_for_active_resident(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Akun warga berhasil dibuat.',
            ])
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.last_login_at', null)
            ->assertJsonPath('data.roles.0.code', 'warga')
            ->assertJsonPath('data.resident.id', $resident->id);

        $this->assertDatabaseHas('users', [
            'resident_id' => $resident->id,
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('Password123!', $resident->fresh()->user->password));
        $this->assertEquals('081234567890', $resident->fresh()->phone);
        $this->assertEquals('budi@example.com', $resident->fresh()->email);
    }

    public function test_created_account_has_only_warga_role(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertCreated();
        $rolesInResponse = $response->json('data.roles');
        $this->assertCount(1, $rolesInResponse);
        $this->assertEquals('warga', $rolesInResponse[0]['code']);
    }

    public function test_warga_cannot_provision(): void
    {
        $warga = $this->wargaUser();
        $resident = Resident::factory()->create();

        $response = $this->actingAs($warga, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertForbidden();
    }

    public function test_bendahara_cannot_provision(): void
    {
        $bendahara = $this->bendaharaUser();
        $resident = Resident::factory()->create();

        $response = $this->actingAs($bendahara, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_provision(): void
    {
        $resident = Resident::factory()->create();

        $response = $this->postJson("/api/v1/residents/{$resident->id}/account", [
            'phone' => '081234567890',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnauthorized();
    }

    public function test_resident_already_has_active_account(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        $existingUser = User::factory()->create(['resident_id' => $resident->id, 'is_active' => true]);
        $existingUser->roles()->attach(Role::factory()->warga()->create());

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081299999999',
                'email' => 'new@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Warga ini sudah memiliki akun.',
            ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_resident_already_has_inactive_account(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        $existingUser = User::factory()->inactive()->create(['resident_id' => $resident->id]);
        $existingUser->roles()->attach(Role::factory()->warga()->create());

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081299999999',
                'email' => 'new@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Warga ini sudah memiliki akun yang tidak aktif. Aktifkan kembali akun yang ada.',
            ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_inactive_resident_cannot_receive_account(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'inactive']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Akun hanya dapat dibuat untuk warga dengan status aktif.',
            ]);

        $this->assertDatabaseMissing('users', ['resident_id' => $resident->id]);
    }

    public function test_moved_resident_cannot_receive_account(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'moved']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Akun hanya dapat dibuat untuk warga dengan status aktif.',
            ]);
    }

    public function test_duplicate_phone_rejected(): void
    {
        $rt = $this->rtUser();
        User::factory()->create(['phone' => '081234567890']);
        $resident = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Nomor telepon sudah digunakan oleh akun lain.');
    }

    public function test_duplicate_email_rejected(): void
    {
        $rt = $this->rtUser();
        User::factory()->create(['email' => 'used@example.com']);
        $resident = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'used@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email sudah digunakan oleh akun lain.');
    }

    public function test_password_is_hashed(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'PlainTextPassword!',
                'password_confirmation' => 'PlainTextPassword!',
            ]);

        $response->assertCreated();

        $user = User::where('resident_id', $resident->id)->first();
        $this->assertNotNull($user);
        $this->assertNotEquals('PlainTextPassword!', $user->password);
        $this->assertTrue(Hash::check('PlainTextPassword!', $user->password));
    }

    public function test_response_does_not_contain_password(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('password_confirmation', $data);
        $this->assertArrayNotHasKey('password_hash', $data);
    }

    public function test_role_injection_rejected(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'rt',
            ]);

        $response->assertStatus(422);
    }

    public function test_role_id_injection_rejected(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role_id' => 1,
            ]);

        $response->assertStatus(422);
    }

    public function test_resident_id_injection_rejected(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'resident_id' => 999,
            ]);

        $response->assertStatus(422);
    }

    public function test_is_active_injection_rejected(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create(['status' => 'active']);
        Role::factory()->warga()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->postJson("/api/v1/residents/{$resident->id}/account", [
                'phone' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_active' => false,
            ]);

        $response->assertStatus(422);
    }

    public function test_residents_list_with_has_account_true(): void
    {
        $rt = $this->rtUser();
        $withAccount = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $withAccount->id]);
        $user->roles()->attach(Role::factory()->warga()->create());

        $withoutAccount = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->getJson('/api/v1/residents?has_account=1');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($withAccount->id, $ids);
        $this->assertNotContains($withoutAccount->id, $ids);
    }

    public function test_residents_list_with_has_account_false(): void
    {
        $rt = $this->rtUser();
        $withAccount = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $withAccount->id]);
        $user->roles()->attach(Role::factory()->warga()->create());

        $withoutAccount = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->getJson('/api/v1/residents?has_account=0');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($withoutAccount->id, $ids);
        $this->assertNotContains($withAccount->id, $ids);
    }

    public function test_resident_summary_includes_has_account_and_account(): void
    {
        $rt = $this->rtUser();
        $residentWith = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $residentWith->id]);
        $user->roles()->attach(Role::factory()->warga()->create());

        $residentWithout = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->getJson('/api/v1/residents');

        $response->assertOk();

        $withData = collect($response->json('data'))->firstWhere('id', $residentWith->id);
        $this->assertTrue($withData['has_account']);
        $this->assertArrayHasKey('account', $withData);
        $this->assertEquals($user->id, $withData['account']['id']);
        $this->assertEquals(true, $withData['account']['is_active']);

        $withoutData = collect($response->json('data'))->firstWhere('id', $residentWithout->id);
        $this->assertFalse($withoutData['has_account']);
        $this->assertNull($withoutData['account']);
    }

    public function test_resident_detail_includes_account(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $resident->id]);
        $role = Role::factory()->warga()->create();
        $user->roles()->attach($role);

        $response = $this->actingAs($rt, 'sanctum')
            ->getJson("/api/v1/residents/{$resident->id}");

        $response->assertOk()
            ->assertJsonPath('data.account.id', $user->id)
            ->assertJsonPath('data.account.phone', $user->phone)
            ->assertJsonPath('data.account.is_active', true)
            ->assertJsonPath('data.account.roles.0.code', 'warga');
    }

    public function test_resident_detail_account_null_when_no_user(): void
    {
        $rt = $this->rtUser();
        $resident = Resident::factory()->create();

        $response = $this->actingAs($rt, 'sanctum')
            ->getJson("/api/v1/residents/{$resident->id}");

        $response->assertOk()
            ->assertJsonPath('data.account', null);
    }

    public function test_database_rejects_duplicate_resident_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $resident = Resident::factory()->create();

        User::create([
            'resident_id' => $resident->id,
            'phone' => '081211111111',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        User::create([
            'resident_id' => $resident->id,
            'phone' => '081222222222',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }
}
