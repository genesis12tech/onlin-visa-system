<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerCannotViewAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_applicant_is_forbidden_from_admin_panel(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        $response = $this->actingAs($applicant)->get('/admin');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_from_admin_panel(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect();
    }
}
