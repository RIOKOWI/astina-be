<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['payment_approved', 'letter_submitted', 'complaint_created']),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(8),
            'data' => [],
            'read_at' => null,
        ];
    }
}
