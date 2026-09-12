<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'AST-'.fake()->unique()->numerify('######'),
            'name' => fake()->word().' '.fake()->randomElement(['RT', 'Warga', 'Komunitas']),
            'category' => fake()->randomElement(['Furniture', 'Elektronik', 'Peralatan', 'ATK', 'Kebersihan', 'Keamanan']),
            'description' => fake()->optional()->sentence(),
            'quantity' => fake()->numberBetween(0, 100),
            'unit' => fake()->randomElement(['pcs', 'unit', 'box', 'lembar', 'set']),
            'purchase_price' => fake()->optional()->randomFloat(2, 10000, 5000000),
            'purchase_date' => fake()->optional()->date(),
            'condition' => fake()->randomElement(['new', 'good', 'fair', 'poor']),
            'status' => fake()->randomElement(['available', 'in_use', 'maintenance', 'retired']),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'available']);
    }

    public function inUse(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'in_use']);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'maintenance']);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'retired']);
    }

    public function furniture(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'Furniture']);
    }

    public function elektronik(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'Elektronik']);
    }
}
