<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'senior_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_case_officer_can_access_officer_panel(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $this->actingAs($officer)->followingRedirects()->get('/officer')->assertSuccessful();
    }

    public function test_senior_officer_can_access_officer_panel(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('senior_officer');

        $this->actingAs($officer)->followingRedirects()->get('/officer')->assertSuccessful();
    }

    public function test_applicant_cannot_access_officer_panel(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $this->actingAs($user)->get('/officer')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_from_officer_panel(): void
    {
        $this->get('/officer')->assertRedirect('/officer/login');
    }

    public function test_case_officer_cannot_access_admin_panel(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $this->actingAs($officer)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_both_panels(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->followingRedirects()->get('/admin')->assertSuccessful();
        $this->actingAs($admin)->followingRedirects()->get('/officer')->assertSuccessful();
    }
}
