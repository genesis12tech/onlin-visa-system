<?php

namespace Database\Factories;

use App\Domain\Identity\Models\OfficerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficerProfile>
 */
class OfficerProfileFactory extends Factory
{
    protected $model = OfficerProfile::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'user_id' => User::factory(),
            'display_initials' => strtoupper(substr($firstName, 0, 1).substr($lastName, 0, 1)),
            'capacity' => fake()->numberBetween(8, 20),
            'specialisations' => fake()->randomElements(['Tourist', 'Business', 'Work', 'Student', 'Medical'], 2),
            'avatar_color' => fake()->randomElement(['indigo', 'blue', 'green', 'amber', 'rose', 'purple']),
            'is_accepting_assignments' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_accepting_assignments' => false]);
    }
}
