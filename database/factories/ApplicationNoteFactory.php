<?php

namespace Database\Factories;

use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationNote>
 */
class ApplicationNoteFactory extends Factory
{
    protected $model = ApplicationNote::class;

    public function definition(): array
    {
        return [
            'visa_application_id' => VisaApplication::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_visible_to_applicant' => false,
            'metadata' => null,
        ];
    }
}
