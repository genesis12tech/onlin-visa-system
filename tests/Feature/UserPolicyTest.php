<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_any_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue((new UserPolicy)->viewAny($admin));
    }

    public function test_super_admin_can_view_any_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->assertTrue((new UserPolicy)->viewAny($superAdmin));
    }

    public function test_applicant_cannot_view_any_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        $this->assertFalse((new UserPolicy)->viewAny($applicant));
    }

    public function test_admin_can_view_specific_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->view($admin, $targetUser));
    }

    public function test_super_admin_can_view_specific_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->view($superAdmin, $targetUser));
    }

    public function test_applicant_cannot_view_specific_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->view($applicant, $targetUser));
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue((new UserPolicy)->create($admin));
    }

    public function test_super_admin_can_create_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->assertTrue((new UserPolicy)->create($superAdmin));
    }

    public function test_applicant_cannot_create_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        $this->assertFalse((new UserPolicy)->create($applicant));
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->update($admin, $targetUser));
    }

    public function test_super_admin_can_update_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->update($superAdmin, $targetUser));
    }

    public function test_applicant_cannot_update_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->update($applicant, $targetUser));
    }

    public function test_super_admin_can_delete_other_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->delete($superAdmin, $targetUser));
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->assertFalse((new UserPolicy)->delete($superAdmin, $superAdmin));
    }

    public function test_admin_cannot_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->delete($admin, $targetUser));
    }

    public function test_applicant_cannot_delete_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->delete($applicant, $targetUser));
    }

    public function test_super_admin_can_restore_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->restore($superAdmin, $targetUser));
    }

    public function test_admin_cannot_restore_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->restore($admin, $targetUser));
    }

    public function test_applicant_cannot_restore_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->restore($applicant, $targetUser));
    }

    public function test_super_admin_can_force_delete_other_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $targetUser = User::factory()->create();

        $this->assertTrue((new UserPolicy)->forceDelete($superAdmin, $targetUser));
    }

    public function test_super_admin_cannot_force_delete_themselves(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->assertFalse((new UserPolicy)->forceDelete($superAdmin, $superAdmin));
    }

    public function test_admin_cannot_force_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->forceDelete($admin, $targetUser));
    }

    public function test_applicant_cannot_force_delete_user(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $targetUser = User::factory()->create();

        $this->assertFalse((new UserPolicy)->forceDelete($applicant, $targetUser));
    }
}
