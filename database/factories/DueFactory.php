<?php

namespace Database\Factories;

use App\Models\Due;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Iuran RT',
            'description' => 'Iuran bulanan warga RT 005',
            'amount' => 50000,
            'frequency' => 'monthly',
            'start_date' => now()->startOfYear(),
            'end_date' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
