<?php

namespace App\Services;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function getList(?string $search = null, ?string $isActive = null, ?string $role = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles', 'resident']);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('resident', fn (Builder $rq) => $rq
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%"));
            });
        }

        if ($isActive !== null) {
            $active = in_array($isActive, ['true', '1'], true);
            $query->where('is_active', $active);
        }

        if ($role) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('code', $role));
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getDetail(User $user): User
    {
        return $user->load(['roles', 'resident']);
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $wasActive = $user->is_active;

            $user->update($data);

            if (isset($data['phone']) || isset($data['email'])) {
                $resident = $user->resident;
                if ($resident) {
                    $sync = [];
                    if (isset($data['phone'])) {
                        $sync['phone'] = $data['phone'];
                    }
                    if (isset($data['email'])) {
                        $sync['email'] = $data['email'];
                    }
                    $resident->update($sync);
                }
            }

            // Deactivate
            if (isset($data['is_active']) && $data['is_active'] === false && $wasActive) {
                $user->tokens()->delete();
                $user->deviceTokens()->update(['revoked_at' => now()]);
            }

            return $user->load(['roles', 'resident']);
        });
    }

    public function resetPassword(User $user, string $hashedPassword): User
    {
        return DB::transaction(function () use ($user, $hashedPassword) {
            $user->update(['password' => $hashedPassword]);
            $user->tokens()->delete();
            $user->deviceTokens()->update(['revoked_at' => now()]);

            return $user;
        });
    }

    public function createResidentAccount(Resident $resident, array $data): User
    {
        return DB::transaction(function () use ($resident, $data) {
            $locked = Resident::where('id', $resident->id)->lockForUpdate()->first();

            if ($locked->status !== 'active') {
                abort(409, 'Akun hanya dapat dibuat untuk warga dengan status aktif.');
            }

            if ($locked->user) {
                if ($locked->user->is_active) {
                    abort(409, 'Warga ini sudah memiliki akun.');
                }
                abort(409, 'Warga ini sudah memiliki akun yang tidak aktif. Aktifkan kembali akun yang ada.');
            }

            $wargaRole = Role::where('code', 'warga')->first();
            if (! $wargaRole) {
                Log::error('Role warga tidak ditemukan saat provisioning akun resident', [
                    'resident_id' => $resident->id,
                ]);
                abort(500, 'Konfigurasi sistem tidak valid.');
            }

            $user = User::create([
                'resident_id' => $resident->id,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->roles()->attach($wargaRole->id);

            $resident->update([
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
            ]);

            Log::info('Akun resident berhasil dibuat', [
                'user_id' => $user->id,
                'resident_id' => $resident->id,
                'created_by' => auth()->id(),
            ]);

            return $user->load(['roles', 'resident']);
        });
    }
}
