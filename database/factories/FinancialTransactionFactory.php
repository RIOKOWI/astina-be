<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'payment_id' => null,
            'type' => fake()->randomElement(['income', 'expense']),
            'amount' => fake()->numberBetween(50000, 500000),
            'category' => fake()->randomElement(['iuran', 'operational', 'kebersihan', 'keamanan', 'perlengkapan', 'lainnya']),
            'description' => fake()->sentence(3),
            'transaction_at' => now(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'category' => 'iuran',
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'category' => 'operational',
        ]);
    }
}
