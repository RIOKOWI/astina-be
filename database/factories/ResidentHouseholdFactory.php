<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentHousehold;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentHouseholdFactory extends Factory
{
    protected $model = ResidentHousehold::class;

    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'household_id' => Household::factory(),
            'relationship' => $this->faker->randomElement(['head', 'spouse', 'child', 'parent', 'sibling', 'other']),
            'joined_at' => $this->faker->date(),
            'left_at' => null,
            'is_current' => true,
        ];
    }

    public function head(): static
    {
        return $this->state(['relationship' => 'head']);
    }

    public function spouse(): static
    {
        return $this->state(['relationship' => 'spouse']);
    }

    public function child(): static
    {
        return $this->state(['relationship' => 'child']);
    }

    public function past(): static
    {
        return $this->state(['is_current' => false, 'left_at' => now()->subDays($this->faker->numberBetween(1, 365))]);
    }
}
