<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\UpdateApplicationSection;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateApplicationSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_answers_for_new_fields(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Tourism',
            'intended_entry_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
        ]);
        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.intended_entry_date',
        ]);
    }

    public function test_it_updates_existing_answers(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Tourism',
        ]);
        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Business',
        ]);

        $this->assertEquals(
            'Business',
            ApplicationAnswer::where('visa_application_id', $application->ulid)
                ->where('field_key', 'travel_details.travel_purpose')
                ->first()->value
        );
        $this->assertDatabaseCount('application_answers', 1);
    }

    public function test_it_skips_null_values(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Tourism',
            'intended_entry_date' => null,
        ]);

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
        ]);
        $this->assertDatabaseMissing('application_answers', [
            'field_key' => 'travel_details.intended_entry_date',
        ]);
    }

    public function test_it_does_not_touch_other_sections(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', ['travel_purpose' => 'Tourism']);
        UpdateApplicationSection::run($application, 'background', ['previous_visa_refusal' => 'no']);

        $this->assertDatabaseCount('application_answers', 2);
    }
}
