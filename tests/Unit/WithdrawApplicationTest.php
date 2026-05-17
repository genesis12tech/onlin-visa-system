<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\WithdrawApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_status_to_withdrawn(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Draft]);
        $actor = User::factory()->create();

        WithdrawApplication::run($application, $actor);

        $this->assertEquals(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    public function test_it_writes_a_status_history_row(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Submitted]);
        $actor = User::factory()->create();

        WithdrawApplication::run($application, $actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::Submitted->value,
            'to_status' => ApplicationStatus::Withdrawn->value,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_it_throws_when_application_is_already_decided(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Approved]);
        $actor = User::factory()->create();

        $this->expectException(\RuntimeException::class);

        WithdrawApplication::run($application, $actor);
    }

    public function test_it_throws_for_already_withdrawn(): void
    {
        $application = VisaApplication::factory()->withdrawn()->create();
        $actor = User::factory()->create();

        $this->expectException(\RuntimeException::class);

        WithdrawApplication::run($application, $actor);
    }
}
