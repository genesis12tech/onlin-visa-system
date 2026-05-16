<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'CorrectPass1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'WrongPass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->post(route('login'), [
            'email' => 'nobody@example.com',
            'password' => 'AnyPass123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mfa_enabled_user_is_redirected_to_challenge(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CorrectPass1'),
            'two_factor_enabled' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'CorrectPass1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('mfa.challenge'));
    }
}
