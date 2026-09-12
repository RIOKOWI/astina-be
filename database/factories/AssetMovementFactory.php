<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'created_by' => User::factory(),
            'type' => fake()->randomElement(['in', 'out', 'adjustment']),
            'quantity' => fake()->numberBetween(1, 100),
            'description' => fake()->optional()->sentence(),
            'movement_at' => now(),
            'created_at' => now(),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'in',
            'quantity' => abs(fake()->numberBetween(1, 100)),
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'out',
            'quantity' => abs(fake()->numberBetween(1, 100)),
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'quantity' => fake()->numberBetween(-50, 50),
        ]);
    }
}
