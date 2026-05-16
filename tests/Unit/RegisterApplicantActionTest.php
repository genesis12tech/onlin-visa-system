<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\RegisterApplicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegisterApplicantActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_creates_user_with_hashed_password(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'name' => 'Jane Doe']);
        $this->assertNotEquals('secret123', $user->password);
    }

    public function test_assigns_applicant_role(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertTrue($user->hasRole('applicant'));
    }

    public function test_does_not_assign_staff_roles(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_two_factor_disabled_by_default(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertFalse($user->two_factor_enabled);
    }
}
