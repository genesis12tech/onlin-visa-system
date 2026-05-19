<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerPanelRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['case_officer', 'senior_officer', 'document_verifier', 'finance_officer', 'admin', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_document_verifier_can_access_officer_panel(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('document_verifier');

        $this->actingAs($user)->followingRedirects()->get('/officer')->assertSuccessful();
    }

    public function test_finance_officer_can_access_officer_panel(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('finance_officer');

        $this->actingAs($user)->followingRedirects()->get('/officer')->assertSuccessful();
    }

    public function test_applicant_cannot_access_officer_panel(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $this->actingAs($user)->get('/officer')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/officer')->assertRedirect('/officer/login');
    }
}
