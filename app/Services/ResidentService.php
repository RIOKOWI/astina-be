<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ResidentService
{
    public function getList(?string $search = null, ?string $status = null, ?string $hasAccount = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Resident::with(['user' => function ($q) {
            $q->with('roles');
        }]);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($hasAccount !== null) {
            $has = in_array($hasAccount, ['1', 'true'], true);
            if ($has) {
                $query->whereHas('user');
            } else {
                $query->whereDoesntHave('user');
            }
        }

        return $query->orderBy('full_name')->paginate($perPage);
    }

    public function getDetail(Resident $resident): Resident
    {
        return $resident->load(['user.roles', 'households' => function ($q) {
            $q->wherePivot('is_current', true);
        }]);
    }

    public function getAuthenticatedResident(int $userId): ?Resident
    {
        return Resident::whereHas('user', function (Builder $q) use ($userId) {
            $q->where('id', $userId);
        })->first();
    }

    public function updateOwnResident(Resident $resident, array $data): Resident
    {
        $resident->update($data);

        return $resident;
    }

    public function updateResident(Resident $resident, array $data): Resident
    {
        // Only sync phone/email if resident has no linked user account
        if ((isset($data['phone']) || isset($data['email'])) && ! $resident->user) {
            $resident->update($data);
        } else {
            // Filter out phone/email if user has account (they're managed via UserService)
            $safeData = collect($data)->except(['phone', 'email'])->toArray();
            $resident->update($safeData);
        }

        return $resident->load(['user.roles', 'households' => function ($q) {
            $q->wherePivot('is_current', true);
        }]);
    }
}
