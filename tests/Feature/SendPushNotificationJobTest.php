<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Exception\Messaging\MessagingError;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class SendPushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_is_pushed_to_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create(['password' => Hash::make('password')]);
        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'test_fcm_token',
            'platform' => 'android',
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test_type',
            'title' => 'Test Title',
            'body' => 'Test Body',
            'data' => ['type' => 'test'],
        ]);

        SendPushNotificationJob::dispatch($notification, ['test_fcm_token']);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_job_is_pushed_with_correct_data(): void
    {
        Queue::fake();

        $user = User::factory()->create(['password' => Hash::make('password')]);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'activity_created',
            'title' => 'Aktivitas Baru',
            'body' => 'Ada aktivitas baru',
            'data' => ['activity_id' => 42],
        ]);

        SendPushNotificationJob::dispatch($notification, ['token1', 'token2']);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_revokes_token_on_messaging_error(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $token = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'invalid_fcm_token',
            'platform' => 'android',
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test_type',
            'title' => 'Test Title',
            'body' => 'Test Body',
            'data' => ['type' => 'test'],
        ]);

        $messagingError = new MessagingError('NotRegistered');
        $target = MessageTarget::with('token', 'invalid_fcm_token');
        $failureReport = SendReport::failure($target, $messagingError);

        $sendReport = MulticastSendReport::withItems([$failureReport]);

        $firebaseService = Mockery::mock(FirebaseService::class);
        $firebaseService->shouldReceive('sendToTokens')->andReturn($sendReport);

        $job = new SendPushNotificationJob($notification, ['invalid_fcm_token']);
        $job->handle($firebaseService);

        $token->refresh();
        $this->assertNotNull($token->revoked_at);
    }

    public function test_revokes_token_on_not_found_exception(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $token = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'unregistered_fcm_token',
            'platform' => 'android',
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test_type',
            'title' => 'Test Title',
            'body' => 'Test Body',
            'data' => ['type' => 'test'],
        ]);

        $messagingError = new NotFound('NotRegistered');
        $target = MessageTarget::with('token', 'unregistered_fcm_token');
        $failureReport = SendReport::failure($target, $messagingError);

        $sendReport = MulticastSendReport::withItems([$failureReport]);

        $firebaseService = Mockery::mock(FirebaseService::class);
        $firebaseService->shouldReceive('sendToTokens')->andReturn($sendReport);

        $job = new SendPushNotificationJob($notification, ['unregistered_fcm_token']);
        $job->handle($firebaseService);

        $token->refresh();
        $this->assertNotNull($token->revoked_at);
    }
}
