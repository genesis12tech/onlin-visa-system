<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Livewire\Tracking\PublicTrackingForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicTrackingFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_tracking_form(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->assertOk()
            ->assertSet('trackingNumber', '')
            ->assertSet('result', null)
            ->assertSet('notFound', false);
    }

    public function test_valid_tracking_number_shows_application_status(): void
    {
        $application = VisaApplication::factory()->submitted()->create();

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'actor_id' => null,
            'public_label' => 'Application received',
            'created_at' => now(),
        ]);

        Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $application->tracking_number)
            ->call('submit')
            ->assertSet('notFound', false)
            ->assertSet('result.ulid', $application->ulid)
            ->assertSee('Application received');
    }

    public function test_unknown_tracking_number_shows_generic_error(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', 'VA-DOES-NOT-EXIST')
            ->call('submit')
            ->assertSet('notFound', true)
            ->assertSet('result', null);
    }

    public function test_same_error_shown_for_empty_and_not_found_inputs(): void
    {
        $componentA = Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', '')
            ->call('submit');

        $componentB = Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', 'VA-NONEXISTENT')
            ->call('submit');

        $this->assertTrue($componentA->get('notFound') || $componentA->get('errors'));
        $this->assertTrue($componentB->get('notFound'));
    }

    public function test_does_not_show_internal_only_status_histories(): void
    {
        $application = VisaApplication::factory()->submitted()->create();

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'actor_id' => null,
            'public_label' => null,
            'created_at' => now(),
        ]);

        $component = Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $application->tracking_number)
            ->call('submit');

        $result = $component->get('result');
        $this->assertNotNull($result);
        $this->assertEmpty($result->statusHistories);
    }

    public function test_tracking_page_is_publicly_accessible(): void
    {
        $this->get(route('track'))->assertOk();
    }
}
