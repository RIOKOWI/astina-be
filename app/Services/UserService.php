<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
}
