<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationPriority;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisaApplicationSpecColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visa_application_has_priority_column_defaulting_to_normal(): void
    {
        $application = VisaApplication::factory()->create();

        $this->assertEquals(ApplicationPriority::Normal, $application->fresh()->priority);
    }

    public function test_visa_application_priority_can_be_set_to_high(): void
    {
        $application = VisaApplication::factory()->create(['priority' => ApplicationPriority::High]);

        $this->assertEquals(ApplicationPriority::High, $application->fresh()->priority);
    }

    public function test_visa_application_has_days_pending_column(): void
    {
        $application = VisaApplication::factory()->create(['days_pending' => 5]);

        $this->assertEquals(5, $application->fresh()->days_pending);
    }

    public function test_visa_application_has_decision_by_column_and_relationship(): void
    {
        $officer = User::factory()->create();
        $application = VisaApplication::factory()->create(['decision_by' => $officer->id]);

        $this->assertTrue($application->fresh()->decisionBy->is($officer));
    }

    public function test_visa_application_supports_soft_delete(): void
    {
        $application = VisaApplication::factory()->create();
        $ulid = $application->ulid;

        $application->delete();

        $this->assertSoftDeleted('visa_applications', ['ulid' => $ulid]);
        $this->assertNull(VisaApplication::find($ulid));
        $this->assertNotNull(VisaApplication::withTrashed()->find($ulid));
    }

    public function test_visa_application_factory_high_priority_state(): void
    {
        $application = VisaApplication::factory()->highPriority()->create();

        $this->assertEquals(ApplicationPriority::High, $application->priority);
    }
}
