<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        putenv('SUPER_ADMIN_EMAIL=');
        putenv('SUPER_ADMIN_PASSWORD=');
        putenv('SUPER_ADMIN_NAME=');
        parent::tearDown();
    }

    public function test_creates_super_admin_from_env_variables(): void
    {
        putenv('SUPER_ADMIN_EMAIL=owner@example.com');
        putenv('SUPER_ADMIN_PASSWORD=secret123');
        putenv('SUPER_ADMIN_NAME=System Owner');

        $this->seed(SuperAdminSeeder::class);

        $user = User::where('email', 'owner@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertEquals('System Owner', $user->name);
    }

    public function test_skips_when_env_variables_are_missing(): void
    {
        putenv('SUPER_ADMIN_EMAIL=');
        putenv('SUPER_ADMIN_PASSWORD=');

        $this->seed(SuperAdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_is_idempotent_on_repeated_runs(): void
    {
        putenv('SUPER_ADMIN_EMAIL=owner@example.com');
        putenv('SUPER_ADMIN_PASSWORD=secret123');

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
    }
}
