<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'reference_no' => 'CMP-TEST-'.Str::random(8),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['facility', 'security', 'cleanliness', 'noise', 'dispute', 'other']),
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'approved_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'approved_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'approved_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'approved_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function facility(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'facility',
        ]);
    }

    public function security(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'security',
        ]);
    }
}
