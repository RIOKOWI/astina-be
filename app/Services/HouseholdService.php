<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function create(array $data): Household
    {
        return DB::transaction(function () use ($data) {
            $headResident = Resident::where('id', $data['head_resident_id'])->lockForUpdate()->first();

            if ($headResident->status !== 'active') {
                abort(409, 'Warga ini tidak aktif dan tidak dapat menjadi kepala keluarga.');
            }

            $hasCurrent = ResidentHousehold::where('resident_id', $headResident->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->exists();

            if ($hasCurrent) {
                abort(409, 'Warga ini sudah memiliki household aktif.');
            }

            $household = Household::create([
                'no_kk' => $data['no_kk'],
                'head_resident_id' => $headResident->id,
                'address' => $data['address'],
                'rt' => $data['rt'],
                'rw' => $data['rw'],
                'postal_code' => $data['postal_code'] ?? null,
                'status' => 'active',
            ]);

            ResidentHousehold::create([
                'resident_id' => $headResident->id,
                'household_id' => $household->id,
                'relationship' => 'head',
                'joined_at' => now()->toDateString(),
                'left_at' => null,
                'is_current' => true,
            ]);

            $headResident->update(['no_kk' => $household->no_kk]);

            return $household->load(['headResident', 'residents' => function ($q) {
                $q->wherePivot('is_current', true);
            }]);
        });
    }

    public function addMember(Household $household, array $data): Resident
    {
        return DB::transaction(function () use ($household, $data) {
            if ($household->status !== 'active') {
                abort(409, 'Household ini tidak aktif.');
            }

            $resident = Resident::where('id', $data['resident_id'])->lockForUpdate()->first();

            if ($resident->status !== 'active') {
                abort(409, 'Warga ini tidak aktif dan tidak dapat加入 household.');
            }

            $alreadyCurrentMember = ResidentHousehold::where('resident_id', $resident->id)
                ->where('household_id', $household->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->exists();

            if ($alreadyCurrentMember) {
                abort(409, 'Warga ini sudah menjadi anggota household ini.');
            }

            $hasCurrent = ResidentHousehold::where('resident_id', $resident->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->exists();

            if ($hasCurrent) {
                abort(409, 'Warga ini sudah memiliki household aktif.');
            }

            ResidentHousehold::create([
                'resident_id' => $resident->id,
                'household_id' => $household->id,
                'relationship' => $data['relationship'],
                'joined_at' => $data['joined_at'] ?? now()->toDateString(),
                'left_at' => null,
                'is_current' => true,
            ]);

            $resident->update(['no_kk' => $household->no_kk]);

            return $resident;
        });
    }

    public function removeMember(Household $household, Resident $resident): void
    {
        DB::transaction(function () use ($household, $resident) {
            $membership = ResidentHousehold::where('resident_id', $resident->id)
                ->where('household_id', $household->id)
                ->whereNull('left_at')
                ->first();

            if (! $membership) {
                abort(404, 'Warga tidak ditemukan sebagai anggota aktif household ini.');
            }

            if ($membership->relationship === 'head') {
                abort(409, 'Kepala keluarga tidak dapat dikeluarkan sebelum kepala keluarga diganti.');
            }

            $membership->update([
                'is_current' => false,
                'left_at' => now()->toDateString(),
            ]);

            $resident->update(['no_kk' => null]);
        });
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
