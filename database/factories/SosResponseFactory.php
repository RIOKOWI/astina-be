<?php

namespace Database\Factories;

use App\Models\SosAlert;
use App\Models\SosResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SosResponse>
 */
class SosResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sos_alert_id' => SosAlert::factory(),
            'user_id' => User::factory(),
            'response' => fake()->randomElement(['coming', 'not_available']),
            'responded_at' => now(),
            'created_at' => now(),
        ];
    }
}
