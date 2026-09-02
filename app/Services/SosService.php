<?php

namespace App\Services;

use App\Models\SosAlert;
use App\Models\SosResponse;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SosService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function createAlert(array $data, User $user): SosAlert
    {
        $hasActive = SosAlert::query()
            ->where('triggered_by', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActive) {
            abort(409, 'Anda sudah memiliki SOS aktif.');
        }

        $alert = DB::transaction(function () use ($data, $user) {
            return SosAlert::create([
                'triggered_by' => $user->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'location_text' => $data['location_text'] ?? null,
                'status' => 'active',
                'triggered_at' => now(),
            ]);
        });

        return $alert;
    }

    public function dispatchSosNotification(SosAlert $alert): void
    {
        $this->notificationService->sendToAllActiveUsers(
            'sos_alert',
            'SOS DARURAT',
            'Seorang warga membutuhkan bantuan',
            ['type' => 'sos_alert', 'sos_id' => (string) $alert->id]
        );
    }

    public function getActiveAlerts(int $perPage = 10): LengthAwarePaginator
    {
        return SosAlert::query()
            ->with(['triggerer.resident.households' => function ($q) {
                $q->wherePivot('is_current', true);
            }, 'resolver'])
            ->where('status', 'active')
            ->orderByDesc('triggered_at')
            ->paginate($perPage);
    }

    public function getAlert(SosAlert $sosAlert): SosAlert
    {
        $sosAlert->load([
            'triggerer.resident.households' => function ($q) {
                $q->wherePivot('is_current', true);
            },
            'resolver',
            'responses.user',
        ]);

        return $sosAlert;
    }

    public function resolveAlert(SosAlert $sosAlert, User $user): SosAlert
    {
        if ($sosAlert->status === 'resolved') {
            abort(409, 'SOS sudah resolved.');
        }

        $sosAlert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        return $sosAlert->fresh();
    }

    public function respondToAlert(SosAlert $sosAlert, array $data, User $user): SosResponse
    {
        $existing = SosResponse::query()
            ->where('sos_alert_id', $sosAlert->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existing) {
            abort(409, 'Anda sudah merespons SOS ini.');
        }

        return SosResponse::create([
            'sos_alert_id' => $sosAlert->id,
            'user_id' => $user->id,
            'response' => $data['response'],
            'responded_at' => now(),
            'created_at' => now(),
        ]);
    }
}
