<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterDocument>
 */
class LetterDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'document_type' => fake()->randomElement(['generated', 'attachment', 'final']),
            'path' => 'letter-documents/'.fake()->uuid().'.pdf',
            'file_name' => 'surat-'.fake()->unique()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(50000, 500000),
        ];
    }
}
