<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_link_request_does_not_reveal_if_email_exists(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('success');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePass1',
            'password_confirmation' => 'NewSecurePass1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecurePass1', $user->fresh()->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewSecurePass1',
            'password_confirmation' => 'NewSecurePass1',
        ])->assertSessionHasErrors('email');
    }
}
