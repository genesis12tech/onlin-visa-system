<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
