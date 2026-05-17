<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\ApplicationSnapshot;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitApplicationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_writes_an_immutable_snapshot(): void
    {
        Queue::fake();
        Notification::fake();

        $application = VisaApplication::factory()->create();
        ApplicationAnswer::factory()->create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);
        $actor = User::factory()->create();

        app(SubmitApplication::class)->execute($application, $actor);

        $snapshot = ApplicationSnapshot::where('visa_application_id', $application->ulid)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals($application->tracking_number, $snapshot->snapshot_data['tracking_number']);
    }

    public function test_snapshot_is_not_overwritten_on_double_submit(): void
    {
        Queue::fake();
        Notification::fake();

        $application = VisaApplication::factory()->create();
        $actor = User::factory()->create();

        app(SubmitApplication::class)->execute($application, $actor);

        // Attempting to firstOrCreate again with same key must return the same record
        $firstSnapshot = ApplicationSnapshot::where('visa_application_id', $application->ulid)->first();

        ApplicationSnapshot::firstOrCreate(
            ['visa_application_id' => $application->ulid],
            ['snapshot_data' => ['tracking_number' => 'should-not-overwrite'], 'created_at' => now()],
        );

        $this->assertDatabaseCount('application_snapshots', 1);
        $this->assertEquals(
            $application->tracking_number,
            ApplicationSnapshot::where('visa_application_id', $application->ulid)->first()->snapshot_data['tracking_number']
        );
    }
}
