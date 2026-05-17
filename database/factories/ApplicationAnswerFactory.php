<?php

namespace Database\Factories;

use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAnswer>
 */
class ApplicationAnswerFactory extends Factory
{
    protected $model = ApplicationAnswer::class;

    public function definition(): array
    {
        return [
            'visa_application_id' => VisaApplication::factory(),
            'field_key' => 'travel_details.'.fake()->word(),
            'value' => fake()->word(),
        ];
    }
}
