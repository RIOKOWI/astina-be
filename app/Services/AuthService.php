<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $phone, string $password): User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user || ! password_verify($password, $user->password)) {
            abort(401, 'Nomor HP atau password salah.');
        }

        if (! $user->is_active) {
            abort(401, 'Nomor HP atau password salah.');
        }

        $user->update(['last_login_at' => now()]);

        $user->load(['roles', 'resident']);

        return $user;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function updateAccount(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);

            $resident = $user->resident;
            if ($resident && (isset($data['phone']) || isset($data['email']))) {
                $sync = [];
                if (isset($data['phone'])) {
                    $sync['phone'] = $data['phone'];
                }
                if (isset($data['email'])) {
                    $sync['email'] = $data['email'];
                }
                $resident->update($sync);
            }

            return $user->load(['roles', 'resident']);
        });
    }

    public function changePassword(User $user, string $newPassword): User
    {
        $user->update(['password' => Hash::make($newPassword)]);
        // Revoke all tokens - simpler and safer
        $user->tokens()->delete();

        return $user;
    }

    public function verifyCurrentPassword(User $user, string $password): bool
    {
        return password_verify($password, $user->password);
    }
}
