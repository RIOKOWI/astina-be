<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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
}
