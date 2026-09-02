<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'path' => 'payment-proofs/'.fake()->uuid().'.jpg',
            'file_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(100000, 2000000),
            'created_at' => now(),
        ];
    }
}
