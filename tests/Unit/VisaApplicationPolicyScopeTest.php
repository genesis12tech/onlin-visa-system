<?php

namespace Tests\Unit;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'senior_officer', 'guard_name' => 'web']);
        Role::create(['name' => 'case_officer', 'guard_name' => 'web']);
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_senior_officer_can_view_any_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $this->assertTrue((new VisaApplicationPolicy)->viewAny($officer));
    }

    public function test_senior_officer_can_view_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->view($officer, $application));
    }

    public function test_case_officer_cannot_view_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertFalse((new VisaApplicationPolicy)->view($officer, $application));
    }

    public function test_senior_officer_can_request_additional_info(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_case_officer_can_request_additional_info_on_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: $officer->id);

        $this->assertTrue((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_case_officer_cannot_request_additional_info_on_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertFalse((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_senior_officer_can_schedule_appointment(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->scheduleAppointment($officer, $application));
    }

    public function test_case_officer_can_schedule_appointment_on_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: $officer->id);

        $this->assertTrue((new VisaApplicationPolicy)->scheduleAppointment($officer, $application));
    }

    public function test_case_officer_cannot_schedule_appointment_on_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertFalse((new VisaApplicationPolicy)->scheduleAppointment($officer, $application));
    }

    private function makeApplication(?int $assigned_officer_id): VisaApplication
    {
        $application = new VisaApplication([
            'assigned_officer_id' => $assigned_officer_id,
        ]);
        $application->exists = true;

        return $application;
    }
}
