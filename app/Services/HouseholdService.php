<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HouseholdService
{
    public function getList(int $perPage = 15): LengthAwarePaginator
    {
        return Household::with('headResident')
            ->withCount('residents as member_count')
            ->orderBy('no_kk')
            ->paginate($perPage);
    }

    public function getDetail(Household $household): Household
    {
        return $household->load(['headResident', 'residents' => function ($q) {
            $q->wherePivot('is_current', true);
        }]);
    }

    public function getCurrentHousehold(Resident $resident): ?Household
    {
        return $resident->households()
            ->wherePivot('is_current', true)
            ->with(['headResident', 'residents' => function ($q) {
                $q->wherePivot('is_current', true);
            }])
            ->first();
    }
}
