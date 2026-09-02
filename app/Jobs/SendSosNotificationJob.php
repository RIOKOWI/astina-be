<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSosNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $sosAlertId,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendToAllActiveUsers(
            'sos_alert',
            'SOS DARURAT',
            'Seorang warga membutuhkan bantuan',
            ['type' => 'sos_alert', 'sos_id' => (string) $this->sosAlertId]
        );
    }
}
