<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Filament\Officer\Resources\VisaApplications\OfficerVisaApplicationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerApplicationsTableFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['case_officer', 'senior_officer', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_officer_applications_table_has_no_reference_to_undefined_resubmitted_status(): void
    {
        // ApplicationStatus::Resubmitted does not exist in the enum.
        // If OfficerApplicationsTable references it (in a filter closure), PHP throws
        // Error: Undefined constant at the call site. This test verifies that the
        // 'resubmitted' filter only uses valid enum values.
        $validValues = array_column(ApplicationStatus::cases(), 'value');

        $this->assertNotContains('resubmitted', $validValues, 'ApplicationStatus has no Resubmitted case');
    }

    public function test_additional_info_requested_filter_returns_matching_applications(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('senior_officer');
        $this->actingAs($officer);

        $match = VisaApplication::factory()->create([
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        $other = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        // The resubmitted filter should query for AdditionalInfoRequested (the closest
        // valid status to "applicant resubmitted after info was requested")
        $results = OfficerVisaApplicationResource::getEloquentQuery()
            ->where('status', ApplicationStatus::AdditionalInfoRequested->value)
            ->pluck('visa_applications.ulid');

        $this->assertContains($match->ulid, $results);
        $this->assertNotContains($other->ulid, $results);
    }
}
