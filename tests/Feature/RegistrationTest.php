<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_registration_page_is_accessible_to_guests(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_new_user_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertAuthenticated();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertTrue($user->hasRole('applicant'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'DifferentPass123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_cannot_see_register_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }
}
