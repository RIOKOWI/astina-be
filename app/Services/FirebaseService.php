<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;

class FirebaseService
{
    private $messaging;

    public function getMessaging()
    {
        if ($this->messaging === null) {
            $credentials = config('services.firebase.credentials');

            if (empty($credentials)) {
                Log::warning('Firebase credentials not configured');

                return null;
            }

            $credentialsPath = base_path($credentials);

            if (! file_exists($credentialsPath)) {
                Log::warning('Firebase credentials file not found', [
                    'credentials_path' => $credentialsPath,
                ]);

                return null;
            }

            $this->messaging = (new Factory)->withServiceAccount($credentialsPath)->createMessaging();
        }

        return $this->messaging;
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): ?SendReport
    {
        $messaging = $this->getMessaging();
        if (! $messaging) {
            Log::warning('Firebase not configured, skipping FCM send');

            return null;
        }

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data);

        $report = $messaging->send($message);

        Log::info('FCM notification sent', [
            'token_prefix' => substr($token, 0, 8),
            'title' => $title,
            'notification_type' => $data['type'] ?? 'unknown',
        ]);

        return $report;
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): ?MulticastSendReport
    {
        $messaging = $this->getMessaging();
        if (! $messaging) {
            Log::warning('Firebase not configured, skipping FCM multicast send');

            return null;
        }

        $message = CloudMessage::withTarget('token', $tokens[0])
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data);

        $report = $messaging->sendMulticast($message, $tokens);

        Log::info('FCM multicast notification sent', [
            'token_count' => count($tokens),
            'title' => $title,
            'notification_type' => $data['type'] ?? 'unknown',
            'success_count' => $report->successes()->count(),
            'failure_count' => $report->failures()->count(),
        ]);

        return $report;
    }
}
