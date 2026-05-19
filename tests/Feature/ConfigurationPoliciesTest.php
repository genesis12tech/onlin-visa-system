<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\VisaFee;
use App\Models\User;
use App\Policies\CountryPolicy;
use App\Policies\VisaFeePolicy;
use App\Policies\VisaTypePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfigurationPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['super_admin', 'admin', 'finance_officer', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // --- CountryPolicy ---

    public function test_admin_can_view_countries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $country));
    }

    public function test_super_admin_can_view_countries(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $country));
    }

    public function test_applicant_cannot_view_countries(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertFalse($policy->viewAny($applicant));
        $this->assertFalse($policy->view($applicant, $country));
    }

    public function test_finance_officer_cannot_view_countries(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertFalse($policy->viewAny($finance));
        $this->assertFalse($policy->view($finance, $country));
    }

    public function test_admin_can_create_countries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $policy = new CountryPolicy;

        $this->assertTrue($policy->create($admin));
    }

    public function test_super_admin_can_create_countries(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $policy = new CountryPolicy;

        $this->assertTrue($policy->create($superAdmin));
    }

    public function test_applicant_cannot_create_countries(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $policy = new CountryPolicy;

        $this->assertFalse($policy->create($applicant));
    }

    public function test_admin_can_update_countries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertTrue($policy->update($admin, $country));
    }

    public function test_super_admin_can_update_countries(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertTrue($policy->update($superAdmin, $country));
    }

    public function test_applicant_cannot_update_countries(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertFalse($policy->update($applicant, $country));
    }

    public function test_only_super_admin_can_delete_countries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $country = Country::factory()->create();
        $policy = new CountryPolicy;

        $this->assertFalse($policy->delete($admin, $country));
        $this->assertTrue($policy->delete($superAdmin, $country));
    }

    // --- VisaTypePolicy ---

    public function test_admin_can_view_visa_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $visaType));
    }

    public function test_super_admin_can_view_visa_types(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $visaType));
    }

    public function test_applicant_cannot_view_visa_types(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertFalse($policy->viewAny($applicant));
        $this->assertFalse($policy->view($applicant, $visaType));
    }

    public function test_finance_officer_cannot_view_visa_types(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertFalse($policy->viewAny($finance));
        $this->assertFalse($policy->view($finance, $visaType));
    }

    public function test_admin_can_create_visa_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->create($admin));
    }

    public function test_super_admin_can_create_visa_types(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->create($superAdmin));
    }

    public function test_applicant_cannot_create_visa_types(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $policy = new VisaTypePolicy;

        $this->assertFalse($policy->create($applicant));
    }

    public function test_admin_can_update_visa_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->update($admin, $visaType));
    }

    public function test_super_admin_can_update_visa_types(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertTrue($policy->update($superAdmin, $visaType));
    }

    public function test_applicant_cannot_update_visa_types(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertFalse($policy->update($applicant, $visaType));
    }

    public function test_only_super_admin_can_delete_visa_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $policy = new VisaTypePolicy;

        $this->assertFalse($policy->delete($admin, $visaType));
        $this->assertTrue($policy->delete($superAdmin, $visaType));
    }

    // --- VisaFeePolicy ---

    public function test_admin_can_view_visa_fees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $fee));
    }

    public function test_super_admin_can_view_visa_fees(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $fee));
    }

    public function test_finance_officer_can_view_visa_fees(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->viewAny($finance));
        $this->assertTrue($policy->view($finance, $fee));
    }

    public function test_applicant_cannot_view_visa_fees(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->viewAny($applicant));
        $this->assertFalse($policy->view($applicant, $fee));
    }

    public function test_admin_can_create_visa_fees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->create($admin));
    }

    public function test_super_admin_can_create_visa_fees(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->create($superAdmin));
    }

    public function test_finance_officer_cannot_create_visa_fees(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->create($finance));
    }

    public function test_applicant_cannot_create_visa_fees(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->create($applicant));
    }

    public function test_admin_can_update_visa_fees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->update($admin, $fee));
    }

    public function test_super_admin_can_update_visa_fees(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertTrue($policy->update($superAdmin, $fee));
    }

    public function test_finance_officer_cannot_update_visa_fees(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->update($finance, $fee));
    }

    public function test_applicant_cannot_update_visa_fees(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->update($applicant, $fee));
    }

    public function test_only_super_admin_can_delete_visa_fees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $visaType = VisaType::factory()->create();
        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Standard Fee',
            'amount' => 15000,
            'effective_from' => now()->toDateString(),
        ]);
        $policy = new VisaFeePolicy;

        $this->assertFalse($policy->delete($admin, $fee));
        $this->assertTrue($policy->delete($superAdmin, $fee));
    }
}
