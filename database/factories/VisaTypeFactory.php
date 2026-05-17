<?php

namespace Database\Factories;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisaType>
 */
class VisaTypeFactory extends Factory
{
    protected $model = VisaType::class;

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->words(3, true).' Visa',
            'code' => strtoupper(fake()->unique()->lexify('????_??')),
            'description' => fake()->sentence(),
            'processing_days' => fake()->numberBetween(5, 30),
            'validity_days' => fake()->randomElement([30, 90, 180, 365]),
            'max_entries' => fake()->randomElement(['single', 'multiple', 'unlimited']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
