<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stamp>
 */
class StampFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'stamped_by' => User::factory(),
            'stamp_path' => 'stamps/'.fake()->uuid().'.png',
            'stamped_at' => now(),
            'created_at' => now(),
        ];
    }
}
