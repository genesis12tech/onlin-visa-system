<?php

namespace Database\Factories;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicantProfile>
 */
class ApplicantProfileFactory extends Factory
{
    protected $model = ApplicantProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'nationality_id' => Country::factory(),
            'country_of_residence_id' => Country::factory(),
            'passport_number' => strtoupper(fake()->bothify('??#######')),
            'passport_expiry_date' => fake()->dateTimeBetween('+1 year', '+10 years')->format('Y-m-d'),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.3)->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->optional(0.7)->state(),
            'postal_code' => fake()->optional(0.7)->postcode(),
        ];
    }
}
