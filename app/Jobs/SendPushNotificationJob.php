<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\SendReport;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    private Notification $notification;

    private array $tokens;

    public function __construct(Notification $notification, array $tokens)
    {
        $this->notification = $notification;
        $this->tokens = $tokens;
    }

    public function handle(FirebaseService $firebaseService): void
    {
        $tokens = array_filter($this->tokens, fn ($t) => ! empty($t));
        if (empty($tokens)) {
            return;
        }

        try {
            $report = $firebaseService->sendToTokens(
                $tokens,
                $this->notification->title,
                $this->notification->body,
                $this->notification->data ?? []
            );

            if ($report === null) {
                return;
            }

            foreach ($report->failures()->getItems() as $failure) {
                $this->handleFailure($failure);
            }
        } catch (NotFound $e) {
            $this->revokeTokens($tokens);
            Log::warning('FCM notification failed: all tokens invalid', [
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
                'token_count' => count($tokens),
                'error' => $e::class,
            ]);
        } catch (ServerUnavailable $e) {
            Log::warning('FCM unavailable, will retry', [
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        } catch (QuotaExceeded $e) {
            Log::error('FCM quota exceeded', [
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
            ]);
        } catch (ServerError $e) {
            Log::error('FCM server error', [
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
                'error' => 'ServerError',
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FCM notification failed', [
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
                'error' => $e::class,
            ]);
            throw $e;
        }
    }

    private function handleFailure(SendReport $failure): void
    {
        $token = $failure->target()->value();
        $shouldRevoke = $failure->messageWasSentToUnknownToken()
            || $failure->messageTargetWasInvalid();

        if ($shouldRevoke) {
            $this->revokeToken($token);
        }

        Log::warning('FCM token delivery failed', [
            'user_id' => $this->notification->user_id,
            'notification_type' => $this->notification->type,
            'token_prefix' => substr($token, 0, 8),
            'error' => $failure->error() instanceof \Throwable ? $failure->error()::class : 'Unknown',
            'revoked' => $shouldRevoke,
        ]);
    }

    private function revokeToken(string $token): void
    {
        DeviceToken::where('token', $token)->update(['revoked_at' => now()]);
    }

    private function revokeTokens(array $tokens): void
    {
        DeviceToken::whereIn('token', $tokens)->update(['revoked_at' => now()]);
    }
}
