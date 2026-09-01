<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => fake()->unique()->word(),
        ];
    }

    public function warga(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Warga',
            'code' => 'warga',
        ]);
    }

    public function rt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'RT',
            'code' => 'rt',
        ]);
    }

    public function bendahara(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bendahara',
            'code' => 'bendahara',
        ]);
    }
}
