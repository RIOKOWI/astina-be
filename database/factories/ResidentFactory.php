<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            'no_kk' => fake()->numerify('################'),
            'full_name' => fake()->name(),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female']),
            'religion' => fake()->randomElement(['islam', 'kristen', 'katolik', 'hindu', 'buddha', 'khonghucu']),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'occupation' => fake()->jobTitle(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'status' => 'active',
            'joined_at' => now(),
            'left_at' => null,
        ];
    }
}
