<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceTokenApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    public function test_authenticated_user_can_register_device_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'token' => 'fcm_test_token_12345678',
                'platform' => 'android',
                'device_name' => 'Samsung A55',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Device token berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm_test_token_12345678',
            'platform' => 'android',
            'device_name' => 'Samsung A55',
        ]);
    }

    public function test_unauthenticated_user_cannot_register_device_token(): void
    {
        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm_test_token_12345678',
            'platform' => 'android',
        ]);

        $response->assertStatus(401);
    }

    public function test_device_token_validation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'token' => '',
                'platform' => 'windows',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'platform']);
    }

    public function test_duplicate_token_updates_existing_record(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm_duplicate_token',
            'platform' => 'ios',
            'device_name' => 'Old Device',
            'last_used_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'token' => 'fcm_duplicate_token',
                'platform' => 'android',
                'device_name' => 'New Device',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm_duplicate_token',
            'platform' => 'android',
            'device_name' => 'New Device',
        ]);
    }

    public function test_user_can_delete_own_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm_token_to_delete',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/device-tokens/fcm_token_to_delete');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm_token_to_delete',
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm_token_to_delete',
            'revoked_at' => null,
        ]);
    }

    public function test_user_cannot_delete_other_user_token(): void
    {
        $user1 = User::factory()->create(['password' => Hash::make('password')]);
        $user2 = User::factory()->create(['password' => Hash::make('password')]);
        DeviceToken::create([
            'user_id' => $user2->id,
            'token' => 'other_user_token',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->deleteJson('/api/v1/device-tokens/other_user_token');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_delete_nonexistent_token_returns_404(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/device-tokens/nonexistent_token');

        $response->assertStatus(404);
    }

    public function test_platform_must_be_valid_enum(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'token' => 'valid_token_12345678',
                'platform' => 'windows',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }
}
