<?php

namespace Tests\Unit;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'case_officer', 'guard_name' => 'web']);
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_super_admin_can_view_any_application(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->assertTrue((new VisaApplicationPolicy)->viewAny($admin));
    }

    public function test_case_officer_can_view_any_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $this->assertTrue((new VisaApplicationPolicy)->viewAny($officer));
    }

    public function test_applicant_cannot_view_any_application(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        $this->assertFalse((new VisaApplicationPolicy)->viewAny($applicant));
    }

    public function test_case_officer_can_only_view_assigned_application(): void
    {
        $officer = User::factory()->create();
        $other = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication($officer->id);
        $otherApp = $this->makeApplication($other->id);

        $policy = new VisaApplicationPolicy;

        $this->assertTrue($policy->view($officer, $application));
        $this->assertFalse($policy->view($officer, $otherApp));
    }

    public function test_only_senior_roles_can_approve(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication($officer->id);

        $this->assertFalse((new VisaApplicationPolicy)->approve($officer, $application));
    }

    private function makeApplication(int $officerId): VisaApplication
    {
        $application = new VisaApplication([
            'status' => ApplicationStatus::Submitted,
            'assigned_officer_id' => $officerId,
        ]);
        $application->exists = true;

        return $application;
    }
}
