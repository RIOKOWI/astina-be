<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendSosNotificationJob;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Role;
use App\Models\SosAlert;
use App\Models\SosResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SosApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        Queue::fake();
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

    private function attachCurrentHousehold(Resident $resident, Household $household): void
    {
        $resident->households()->attach($household->id, [
            'relationship' => 'child',
            'joined_at' => now(),
            'left_at' => null,
            'is_current' => true,
        ]);
    }

    // =========================================================
    // CREATE SOS - AUTHORIZATION
    // =========================================================

    public function test_guest_cannot_create_sos(): void
    {
        $response = $this->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_warga_can_create_sos(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'location_text' => 'Rumah warga',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SOS alert created successfully')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('sos_alerts', [
            'triggered_by' => $warga->id,
            'status' => 'active',
        ]);
    }

    public function test_triggered_by_comes_from_authenticated_user(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertJsonPath('data.triggered_by.id', $warga->id);
        $this->assertEquals($warga->id, SosAlert::first()->triggered_by);
    }

    public function test_response_contains_triggered_by_info(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('triggered_by', $data);
        $this->assertArrayHasKey('id', $data['triggered_by']);
        $this->assertArrayHasKey('name', $data['triggered_by']);
    }

    // =========================================================
    // CURRENT HOUSEHOLD
    // =========================================================

    public function test_sos_response_contains_current_household(): void
    {
        $resident = Resident::factory()->create(['full_name' => 'Budi Santoso']);
        $household = Household::factory()->create([
            'no_kk' => '1234567890123456',
            'address' => 'Jl. Mawar No. 12',
            'rt' => '005',
            'rw' => '003',
            'postal_code' => '12345',
        ]);
        $this->attachCurrentHousehold($resident, $household);
        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $data = $response->json('data');
        $this->assertNotNull($data['household']);
        $this->assertEquals($household->id, $data['household']['id']);
        $this->assertEquals('Jl. Mawar No. 12', $data['household']['address']);
        $this->assertEquals('005', $data['household']['rt']);
        $this->assertEquals('003', $data['household']['rw']);
        $this->assertEquals('12345', $data['household']['postal_code']);
    }

    public function test_only_current_household_returned_not_old(): void
    {
        $resident = Resident::factory()->create();
        $oldHousehold = Household::factory()->create(['address' => 'Jl. Lama No. 1']);
        $currentHousehold = Household::factory()->create(['address' => 'Jl. Baru No. 2']);

        $resident->households()->attach($oldHousehold->id, [
            'relationship' => 'child',
            'joined_at' => now()->subYears(2),
            'left_at' => now()->subMonth(),
            'is_current' => false,
        ]);
        $resident->households()->attach($currentHousehold->id, [
            'relationship' => 'child',
            'joined_at' => now()->subMonth(),
            'left_at' => null,
            'is_current' => true,
        ]);

        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $data = $response->json('data');
        $this->assertEquals('Jl. Baru No. 2', $data['household']['address']);
        $this->assertNotEquals('Jl. Lama No. 1', $data['household']['address'] ?? null);
    }

    public function test_resident_without_current_household_can_still_create_sos(): void
    {
        $resident = Resident::factory()->create();
        $oldHousehold = Household::factory()->create();
        $resident->households()->attach($oldHousehold->id, [
            'relationship' => 'child',
            'joined_at' => now()->subYear(),
            'left_at' => now()->subMonth(),
            'is_current' => false,
        ]);
        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sos_alerts', ['triggered_by' => $warga->id]);
        $this->assertNull($response->json('data.household'));
    }

    public function test_resident_without_any_household_can_still_create_sos(): void
    {
        $resident = Resident::factory()->create();
        $warga = $this->makeWargaUser($resident);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.household'));
    }

    public function test_response_contains_location(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
            'location_text' => 'Depan pintu gerbang',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('location', $data);
        $this->assertEquals(-6.2, $data['location']['latitude']);
        $this->assertEquals(106.8, $data['location']['longitude']);
        $this->assertEquals('Depan pintu gerbang', $data['location']['text']);
    }

    public function test_response_does_not_expose_sensitive_fields(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('device_token', $data);
        $this->assertArrayNotHasKey('token', $data);
    }

    // =========================================================
    // DUPLICATE ACTIVE SOS
    // =========================================================

    public function test_user_with_active_sos_cannot_create_second(): void
    {
        $warga = $this->makeWargaUser();
        SosAlert::factory()->create(['triggered_by' => $warga->id, 'status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Anda sudah memiliki SOS aktif.');
    }

    public function test_user_with_resolved_sos_can_create_new(): void
    {
        $warga = $this->makeWargaUser();
        SosAlert::factory()->resolved()->create(['triggered_by' => $warga->id]);

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(2, SosAlert::where('triggered_by', $warga->id)->count());
    }

    // =========================================================
    // VALIDATION
    // =========================================================

    public function test_latitude_below_minimum_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -91,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(422);
    }

    public function test_latitude_above_maximum_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => 91,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(422);
    }

    public function test_longitude_below_minimum_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => -181,
        ]);

        $response->assertStatus(422);
    }

    public function test_longitude_above_maximum_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 181,
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_latitude_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'longitude' => 106.8,
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_longitude_returns_422(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
        ]);

        $response->assertStatus(422);
    }

    public function test_location_text_optional(): void
    {
        $warga = $this->makeWargaUser();

        $response = $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.location.text'));
    }

    // =========================================================
    // ACTIVE SOS
    // =========================================================

    public function test_get_active_returns_active_sos(): void
    {
        $user = $this->makeWargaUser();
        SosAlert::factory()->create(['triggered_by' => $user->id, 'status' => 'active']);
        SosAlert::factory()->resolved()->create();

        $response = $this->actingAsUser($user)->getJson('/api/v1/sos/alerts/active');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Active SOS alerts retrieved successfully');
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    public function test_active_excludes_resolved(): void
    {
        $user = $this->makeWargaUser();
        SosAlert::factory()->resolved()->create(['triggered_by' => $user->id]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/sos/alerts/active');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_active_returns_401_for_guest(): void
    {
        $response = $this->getJson('/api/v1/sos/alerts/active');
        $response->assertStatus(401);
    }

    public function test_active_returns_household(): void
    {
        $resident = Resident::factory()->create();
        $household = Household::factory()->create(['address' => 'Jl. Test No. 1']);
        $this->attachCurrentHousehold($resident, $household);
        $warga = $this->makeWargaUser($resident);
        SosAlert::factory()->create(['triggered_by' => $warga->id, 'status' => 'active']);

        $response = $this->actingAsUser($warga)->getJson('/api/v1/sos/alerts/active');

        $response->assertStatus(200);
        $data = $response->json('data.0');
        $this->assertNotNull($data['household']);
        $this->assertEquals('Jl. Test No. 1', $data['household']['address']);
    }

    // =========================================================
    // DETAIL
    // =========================================================

    public function test_get_detail_returns_sos(): void
    {
        $user = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['triggered_by' => $user->id, 'status' => 'active']);

        $response = $this->actingAsUser($user)->getJson("/api/v1/sos/alerts/{$sos->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $sos->id);
    }

    public function test_get_detail_returns_household(): void
    {
        $resident = Resident::factory()->create(['full_name' => 'Andi Wijaya']);
        $household = Household::factory()->create(['address' => 'Jl. Soekarno No. 5']);
        $this->attachCurrentHousehold($resident, $household);
        $warga = $this->makeWargaUser($resident);
        $sos = SosAlert::factory()->create(['triggered_by' => $warga->id]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/sos/alerts/{$sos->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotNull($data['household']);
        $this->assertEquals('Jl. Soekarno No. 5', $data['household']['address']);
    }

    public function test_get_detail_returns_404_for_nonexistent(): void
    {
        $user = $this->makeWargaUser();

        $response = $this->actingAsUser($user)->getJson('/api/v1/sos/alerts/99999');

        $response->assertStatus(404);
    }

    public function test_get_detail_returns_401_for_guest(): void
    {
        $sos = SosAlert::factory()->create();

        $response = $this->getJson("/api/v1/sos/alerts/{$sos->id}");

        $response->assertStatus(401);
    }

    // =========================================================
    // RESOLVE
    // =========================================================

    public function test_rt_can_resolve_sos(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $sos = SosAlert::factory()->create(['triggered_by' => $warga->id, 'status' => 'active']);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SOS alert resolved successfully')
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('sos_alerts', [
            'id' => $sos->id,
            'status' => 'resolved',
            'resolved_by' => $rt->id,
        ]);
    }

    public function test_warga_cannot_resolve_sos(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $response->assertStatus(403);
    }

    public function test_resolved_sos_cannot_be_resolved_again(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $sos = SosAlert::factory()->resolved()->create(['triggered_by' => $warga->id]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $response->assertStatus(409);
    }

    public function test_resolve_populates_resolved_at(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $sos = SosAlert::factory()->create(['triggered_by' => $warga->id, 'status' => 'active']);

        $this->actingAsUser($rt)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $this->assertNotNull($sos->fresh()->resolved_at);
    }

    public function test_resolve_populates_resolved_by(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $sos = SosAlert::factory()->create(['triggered_by' => $warga->id, 'status' => 'active']);

        $this->actingAsUser($rt)->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $this->assertEquals($rt->id, $sos->fresh()->resolved_by);
    }

    public function test_resolve_returns_401_for_guest(): void
    {
        $sos = SosAlert::factory()->create();

        $response = $this->postJson("/api/v1/sos/alerts/{$sos->id}/resolve");

        $response->assertStatus(401);
    }

    // =========================================================
    // RESPONSE / ACKNOWLEDGEMENT
    // =========================================================

    public function test_valid_response_accepted(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'coming',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SOS response recorded successfully')
            ->assertJsonPath('data.response', 'coming');

        $this->assertDatabaseHas('sos_responses', [
            'sos_alert_id' => $sos->id,
            'user_id' => $warga->id,
            'response' => 'coming',
        ]);
    }

    public function test_not_available_response_accepted(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'not_available',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.response', 'not_available');
    }

    public function test_response_user_id_comes_from_auth(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'coming',
        ]);

        $this->assertEquals($warga->id, SosResponse::first()->user_id);
    }

    public function test_invalid_enum_rejected(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'maybe_later',
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_response_rejected(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);
        SosResponse::factory()->create([
            'sos_alert_id' => $sos->id,
            'user_id' => $warga->id,
            'response' => 'coming',
        ]);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'not_available',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Anda sudah merespons SOS ini.');
    }

    public function test_response_401_for_guest(): void
    {
        $sos = SosAlert::factory()->create();

        $response = $this->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'coming',
        ]);

        $response->assertStatus(401);
    }

    public function test_response_detail_includes_user_info(): void
    {
        $warga = $this->makeWargaUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/sos/alerts/{$sos->id}/responses", [
            'response' => 'coming',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($warga->id, $data['user']['id']);
    }

    // =========================================================
    // NOTIFICATION / FCM
    // =========================================================

    public function test_notification_job_dispatched_after_sos_creation(): void
    {
        Queue::fake();

        $warga = $this->makeWargaUser();

        $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        Queue::assertPushed(SendSosNotificationJob::class);
    }

    // =========================================================
    // TRANSACTION
    // =========================================================

    public function test_failed_sos_creation_rolls_back(): void
    {
        Queue::fake();

        $warga = $this->makeWargaUser();
        $initialCount = SosAlert::count();

        // Try to create with invalid data (will fail validation)
        $this->actingAsUser($warga)->postJson('/api/v1/sos/alerts', [
            'latitude' => -6.2,
            // missing longitude
        ]);

        $this->assertEquals($initialCount, SosAlert::count());
        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    // =========================================================
    // PAGINATION
    // =========================================================

    public function test_active_sos_returns_pagination_meta(): void
    {
        $user = $this->makeWargaUser();
        for ($i = 0; $i < 3; $i++) {
            SosAlert::factory()->create(['triggered_by' => $user->id, 'status' => 'active']);
        }

        $response = $this->actingAsUser($user)->getJson('/api/v1/sos/alerts/active');

        $response->assertStatus(200);
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('current_page', $response->json('meta'));
        $this->assertArrayHasKey('total', $response->json('meta'));
    }

    // =========================================================
    // DETAIL WITH RESPONSES
    // =========================================================

    public function test_detail_shows_responses(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $sos = SosAlert::factory()->create(['status' => 'active']);
        SosResponse::factory()->create([
            'sos_alert_id' => $sos->id,
            'user_id' => $warga->id,
            'response' => 'coming',
        ]);
        SosResponse::factory()->create([
            'sos_alert_id' => $sos->id,
            'user_id' => $rt->id,
            'response' => 'coming',
        ]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/sos/alerts/{$sos->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('responses', $data);
        $this->assertCount(2, $data['responses']);
    }
}
