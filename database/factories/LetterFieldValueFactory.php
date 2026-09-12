<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterField;
use App\Models\LetterFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterFieldValue>
 */
class LetterFieldValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'letter_field_id' => LetterField::factory(),
            'value' => fake()->sentence(),
        ];
    }
}
