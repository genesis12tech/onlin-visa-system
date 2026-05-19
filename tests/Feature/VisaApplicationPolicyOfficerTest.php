<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyOfficerTest extends TestCase
{
    use RefreshDatabase;

    protected VisaApplicationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->policy = new VisaApplicationPolicy;

        foreach (['case_officer', 'senior_officer', 'document_verifier', 'finance_officer', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // approve

    public function test_case_officer_can_approve_their_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, $officer->id);

        $this->assertTrue($this->policy->approve($officer, $application));
    }

    public function test_case_officer_cannot_approve_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertFalse($this->policy->approve($officer, $application));
    }

    public function test_case_officer_cannot_approve_another_officers_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $other = User::factory()->create();
        $application = $this->makeApplication(ApplicationStatus::UnderReview, $other->id);

        $this->assertFalse($this->policy->approve($officer, $application));
    }

    public function test_approve_is_blocked_when_status_is_draft(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::Draft, $officer->id);

        $this->assertFalse($this->policy->approve($officer, $application));
    }

    public function test_senior_officer_can_approve_any_application(): void
    {
        $senior = User::factory()->create();
        $senior->assignRole('senior_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertTrue($this->policy->approve($senior, $application));
    }

    // reject

    public function test_case_officer_can_reject_their_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, $officer->id);

        $this->assertTrue($this->policy->reject($officer, $application));
    }

    public function test_case_officer_cannot_reject_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertFalse($this->policy->reject($officer, $application));
    }

    // reassign

    public function test_senior_officer_can_reassign(): void
    {
        $senior = User::factory()->create();
        $senior->assignRole('senior_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertTrue($this->policy->reassign($senior, $application));
    }

    public function test_case_officer_cannot_reassign(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertFalse($this->policy->reassign($officer, $application));
    }

    // finance_officer

    public function test_finance_officer_cannot_approve(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $application = $this->makeApplication(ApplicationStatus::UnderReview, null);

        $this->assertFalse($this->policy->approve($finance, $application));
    }

    private function makeApplication(ApplicationStatus $status, ?int $officerId): VisaApplication
    {
        return VisaApplication::factory()->create([
            'status' => $status,
            'assigned_officer_id' => $officerId,
        ]);
    }
}
