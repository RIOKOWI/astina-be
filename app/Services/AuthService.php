<?php

namespace App\Services;

use App\Models\User;

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
}
