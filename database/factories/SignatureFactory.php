<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signature>
 */
class SignatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'signed_by' => User::factory(),
            'signature_path' => 'signatures/' . fake()->uuid() . '.png',
            'signature_hash' => fake()->sha256(),
            'signed_at' => now(),
            'created_at' => now(),
        ];
    }
}
