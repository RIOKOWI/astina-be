<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    public function test_login_success(): void
    {
        $password = 'secret123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'phone',
                        'email',
                        'is_active',
                        'roles',
                        'resident',
                    ],
                ],
                'meta',
            ]);

        $this->assertEquals('Bearer', $response->json('data.token_type'));
        $this->assertEquals($user->id, $response->json('data.user.id'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'last_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_with_roles(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $role = Role::factory()->warga()->create();
        $user->roles()->attach($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.roles.0.code', 'warga');
    }

    public function test_login_with_resident(): void
    {
        $resident = Resident::factory()->create();
        $user = User::factory()->create([
            'resident_id' => $resident->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.resident.id', $resident->id)
            ->assertJsonPath('data.user.resident.full_name', $resident->full_name);
    }

    public function test_login_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Nomor HP atau password salah.',
            ]);
    }

    public function test_login_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '089999999999',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Nomor HP atau password salah.',
            ]);
    }

    public function test_login_inactive_user(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Nomor HP atau password salah.',
            ]);
    }

    public function test_login_validation_phone_required(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['phone'],
            ])
            ->assertJsonPath('errors.phone.0', 'The phone field is required.');
    }

    public function test_login_validation_password_required(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['password'],
            ])
            ->assertJsonPath('errors.password.0', 'The password field is required.');
    }

    public function test_me_success(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $role = Role::factory()->warga()->create();
        $user->roles()->attach($role);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data pengguna berhasil diambil.',
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.phone', $user->phone)
            ->assertJsonPath('data.roles.0.code', 'warga');

        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_me_no_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_logout_success(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');
        $tokenId = (int) explode('|', $token)[0];

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_logout_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    public function test_user_has_role_relationship(): void
    {
        $user = User::factory()->create();
        $role1 = Role::factory()->warga()->create();
        $role2 = Role::factory()->rt()->create();
        $user->roles()->attach([$role1->id, $role2->id]);

        $this->assertCount(2, $user->roles);
        $this->assertTrue($user->roles->contains($role1));
        $this->assertTrue($user->roles->contains($role2));
    }

    public function test_role_can_access_users(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->warga()->create();
        $user->roles()->attach($role);

        $this->assertCount(1, $role->users);
        $this->assertTrue($role->users->contains($user));
    }
}
