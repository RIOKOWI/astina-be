<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Letter>
 */
class LetterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_no' => 'SK/' . fake()->unique()->numerify('RT05/######'),
            'letter_type_id' => LetterType::factory(),
            'resident_id' => Resident::factory(),
            'submitted_by' => User::factory(),
            'purpose' => fake()->optional()->sentence(),
            'status' => 'draft',
            'rejection_reason' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
        ]);
    }
}
