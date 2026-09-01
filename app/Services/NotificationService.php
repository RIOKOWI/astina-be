<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendToUser(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $tokens = $this->getActiveTokens($user);

        if ($tokens->isEmpty()) {
            Log::warning('No active device tokens for user', ['user_id' => $user->id]);

            return;
        }

        SendPushNotificationJob::dispatch($notification, $tokens->pluck('token')->toArray());
    }

    public function sendToUsers(Collection $users, string $type, string $title, string $body, array $data = []): void
    {
        $userIds = $users->pluck('id')->toArray();

        $notifications = [];
        foreach ($users as $user) {
            $notifications[] = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        }

        $tokensByUser = DeviceToken::whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->get()
            ->groupBy('user_id');

        foreach ($notifications as $notification) {
            $tokens = $tokensByUser->get($notification->user_id, collect());
            if ($tokens->isEmpty()) {
                continue;
            }
            SendPushNotificationJob::dispatch($notification, $tokens->pluck('token')->toArray());
        }
    }

    public function sendToAllActiveUsers(string $type, string $title, string $body, array $data = []): void
    {
        $users = User::where('is_active', true)->with('deviceTokens')->get();
        $this->sendToUsers($users, $type, $title, $body, $data);
    }

    private function getActiveTokens(User $user): Collection
    {
        return $user->deviceTokens()->whereNull('revoked_at')->get();
    }
}
