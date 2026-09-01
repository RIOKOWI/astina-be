<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ResidentService
{
    public function getList(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Resident::query();

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('full_name')->paginate($perPage);
    }

    public function getDetail(Resident $resident): Resident
    {
        return $resident;
    }

    public function getAuthenticatedResident(int $userId): ?Resident
    {
        return Resident::whereHas('user', function (Builder $q) use ($userId) {
            $q->where('id', $userId);
        })->first();
    }
}
