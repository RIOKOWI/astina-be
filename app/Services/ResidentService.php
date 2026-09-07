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
        return $resident->load([
            'user.roles',
            'households' => function ($q) {
                $q->wherePivot('is_current', true)->with('media');
            },
            'media',
        ]);
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

    public function create(array $data): Resident
    {
        $resident = Resident::create([
            'nik' => $data['nik'],
            'full_name' => $data['full_name'],
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'],
            'religion' => $data['religion'] ?? null,
            'marital_status' => $data['marital_status'],
            'occupation' => $data['occupation'] ?? null,
            'last_education' => $data['last_education'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => 'active',
            'no_kk' => null,
            'left_at' => null,
            'joined_at' => $data['joined_at'] ?? now()->toDateString(),
        ]);

        return $resident->load('user');
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
