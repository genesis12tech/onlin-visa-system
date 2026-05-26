<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\CountryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CountryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_can_delete_any_country(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue((new CountryPolicy)->deleteAny($user));
    }

    public function test_admin_cannot_delete_any_country(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertFalse((new CountryPolicy)->deleteAny($user));
    }
}
