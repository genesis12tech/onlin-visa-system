<?php

namespace Database\Factories;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisaApplication>
 */
class VisaApplicationFactory extends Factory
{
    protected $model = VisaApplication::class;

    public function definition(): array
    {
        return [
            'tracking_number' => 'VA-'.now()->year.'-'.strtoupper(fake()->unique()->lexify('??????')),
            'applicant_profile_id' => ApplicantProfile::factory(),
            'visa_type_id' => VisaType::factory(),
            'form_template_id' => FormTemplate::factory(),
            'status' => ApplicationStatus::Draft,
            'assigned_officer_id' => null,
            'submitted_at' => null,
            'travel_date' => null,
            'decision_at' => null,
            'decision_reason' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Withdrawn]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }

    public function infoRequested(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::AdditionalInfoRequested,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Approved,
            'submitted_at' => now(),
            'decision_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Rejected,
            'submitted_at' => now(),
            'decision_at' => now(),
        ]);
    }
}
