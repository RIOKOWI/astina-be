<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'no_kk' => fake()->unique()->numerify('################'),
            'head_resident_id' => null,
            'address' => fake()->streetAddress().', RT '.fake()->numberBetween(1, 99).'/RW '.fake()->numberBetween(1, 99),
            'rt' => fake()->numerify('00#'),
            'rw' => fake()->numerify('00#'),
            'postal_code' => fake()->postcode(),
            'status' => 'active',
        ];
    }
}
