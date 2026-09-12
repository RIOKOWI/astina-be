<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterApproval>
 */
class LetterApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'approved_by' => User::factory(),
            'action' => fake()->randomElement(['approved', 'rejected']),
            'notes' => fake()->optional()->sentence(),
            'acted_at' => now(),
            'created_at' => now(),
        ];
    }
}
