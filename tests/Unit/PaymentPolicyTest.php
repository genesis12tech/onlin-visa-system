<?php

namespace Tests\Unit;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Policies\PaymentPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['finance_officer', 'admin', 'super_admin', 'applicant'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_finance_officer_can_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertTrue((new PaymentPolicy)->viewAny($user));
    }

    public function test_admin_can_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue((new PaymentPolicy)->viewAny($user));
    }

    public function test_applicant_cannot_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->assertFalse((new PaymentPolicy)->viewAny($user));
    }

    public function test_finance_officer_cannot_create_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertFalse((new PaymentPolicy)->create($user));
    }

    public function test_finance_officer_cannot_update_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertFalse((new PaymentPolicy)->update($user, new Payment));
    }

    public function test_applicant_cannot_view_a_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->assertFalse((new PaymentPolicy)->view($user, new Payment));
    }

    public function test_applicant_can_download_own_invoice_receipt(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $profile = ApplicantProfile::factory()->create(['user_id' => $user->id]);
        $application = VisaApplication::factory()->submitted()->create(['applicant_profile_id' => $profile->ulid]);
        $payment = Payment::factory()->succeeded()->create(['visa_application_id' => $application->ulid]);
        $invoice = Invoice::factory()->create(['payment_id' => $payment->ulid]);

        $this->assertTrue((new PaymentPolicy)->downloadReceipt($user, $invoice));
    }

    public function test_applicant_cannot_download_other_applicants_invoice_receipt(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $otherProfile = ApplicantProfile::factory()->create();
        $application = VisaApplication::factory()->submitted()->create(['applicant_profile_id' => $otherProfile->ulid]);
        $payment = Payment::factory()->succeeded()->create(['visa_application_id' => $application->ulid]);
        $invoice = Invoice::factory()->create(['payment_id' => $payment->ulid]);

        $this->assertFalse((new PaymentPolicy)->downloadReceipt($user, $invoice));
    }

    public function test_finance_officer_can_download_any_receipt(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $invoice = Invoice::factory()->create();

        $this->assertTrue((new PaymentPolicy)->downloadReceipt($user, $invoice));
    }
}
