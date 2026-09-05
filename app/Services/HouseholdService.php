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

    public function update(Household $household, array $data): Household
    {
        $oldNoKk = $household->no_kk;
        $household->update($data);

        // Mirror no_kk to current resident_household members
        if (isset($data['no_kk']) && $data['no_kk'] !== $oldNoKk) {
            $memberIds = $household->residents()
                ->wherePivot('is_current', true)
                ->wherePivotNull('left_at')
                ->pluck('residents.id');

            Resident::whereIn('id', $memberIds)->update(['no_kk' => $data['no_kk']]);
        }

        return $household->load(['headResident', 'residents' => function ($q) {
            $q->wherePivot('is_current', true);
        }]);
    }
}
