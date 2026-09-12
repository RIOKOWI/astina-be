<?php

namespace Database\Factories;

use App\Models\DueBill;
use App\Models\Payment;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'due_bill_id' => DueBill::factory(),
            'resident_id' => Resident::factory(),
            'amount' => 50000,
            'method' => fake()->randomElement(['cash', 'transfer', 'ewallet', 'other']),
            'status' => 'pending',
            'paid_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => 'Bukti pembayaran tidak jelas',
        ]);
    }
}
