<?php

namespace Tests\Unit;

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
}
