<?php

namespace Database\Factories;

use App\Models\LetterField;
use App\Models\LetterType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterField>
 */
class LetterFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_type_id' => LetterType::factory(),
            'field_key' => 'field_'.fake()->unique()->numberBetween(1, 99999),
            'label' => fake()->words(2, true),
            'field_type' => fake()->randomElement(['text', 'number', 'date', 'textarea', 'select', 'checkbox']),
            'is_required' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
