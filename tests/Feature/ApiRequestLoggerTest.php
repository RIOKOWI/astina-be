<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiRequestLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    public function test_request_produces_log_entry(): void
    {
        $logged = false;
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use (&$logged) {
                $logged = $message === 'API request completed' && isset($context['request_id']) && $context['method'] === 'POST';

                return $logged;
            });

        $this->postJson('/api/v1/auth/login', [
            'phone' => '081211111111',
            'password' => 'password123',
        ]);

        $this->assertTrue($logged, 'Expected API request completed log entry');
    }

    public function test_log_contains_required_fields(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && isset($context['request_id'])
                    && isset($context['method'])
                    && isset($context['path'])
                    && isset($context['status'])
                    && isset($context['duration_ms'])
                    && isset($context['ip'])
                    && isset($context['user_agent']);
            });

        $this->postJson('/api/v1/auth/login', [
            'phone' => '081211111111',
            'password' => 'password123',
        ]);
    }

    public function test_authenticated_request_includes_user_id(): void
    {
        $role = Role::factory()->warga()->create();
        $resident = Resident::factory()->create();
        $user = User::factory()->create(['resident_id' => $resident->id, 'password' => Hash::make('password123')]);
        $user->roles()->attach($role);

        Log::spy();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($user) {
                return $message === 'API request completed'
                    && $context['user_id'] === $user->id
                    && $context['resident_id'] === $user->resident_id;
            });

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/me/resident');
    }

    public function test_unauthenticated_request_has_null_user_and_resident_id(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && $context['user_id'] === null
                    && $context['resident_id'] === null;
            });

        $this->getJson('/api/v1/me/resident');
    }

    public function test_401_response_logs_as_warning(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && $context['status'] === 401;
            });

        $this->getJson('/api/v1/me/resident');
    }

    public function test_403_response_logs_as_warning(): void
    {
        $role = Role::factory()->warga()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && $context['status'] === 403;
            });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/residents');
    }

    public function test_404_response_logs_as_info(): void
    {
        $role = Role::factory()->rt()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        Log::spy();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && $context['status'] === 404;
            });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/residents/99999');
    }

    public function test_422_response_logs_as_warning(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'API request completed'
                    && $context['status'] === 422;
            });

        $this->postJson('/api/v1/auth/login', [
            'phone' => '',
            'password' => '',
        ]);
    }

    public function test_password_not_in_log_context(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return ! isset($context['password'])
                    && ! isset($context['password_confirmation']);
            });

        $this->postJson('/api/v1/auth/login', [
            'phone' => '081211111111',
            'password' => 'password123',
        ]);
    }

    public function test_bearer_token_not_in_log_context(): void
    {
        Log::spy();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return ! isset($context['Authorization'])
                    && ! isset($context['Bearer'])
                    && ! isset($context['token']);
            });

        $this->postJson('/api/v1/auth/login', [
            'phone' => '081211111111',
            'password' => 'password123',
        ]);
    }

    public function test_x_request_id_header_is_returned(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $role = Role::factory()->warga()->create();
        $user->roles()->attach($role);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('X-Request-ID', 'custom-request-id-123')
            ->getJson('/api/v1/me/resident');

        $response->assertHeader('X-Request-ID', 'custom-request-id-123');
    }

    public function test_response_includes_x_request_id_header(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $response->assertHeader('X-Request-ID');
    }
}
