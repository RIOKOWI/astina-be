<?php

namespace Database\Factories;

use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SosAlert>
 */
class SosAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'triggered_by' => User::factory(),
            'latitude' => fake()->latitude(-6, -5),
            'longitude' => fake()->longitude(106, 107),
            'location_text' => fake()->sentence(3),
            'status' => 'active',
            'triggered_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }
}
