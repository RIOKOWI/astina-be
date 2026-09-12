<?php

namespace Database\Factories;

use App\Models\Due;
use App\Models\DueBill;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DueBill>
 */
class DueBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'due_id' => Due::factory(),
            'resident_id' => Resident::factory(),
            'amount' => 50000,
            'due_date' => now()->addMonth(),
            'status' => 'unpaid',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
        ]);
    }
}
