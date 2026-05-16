<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MfaChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_page_requires_mfa_session_key(): void
    {
        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
    }

    public function test_valid_otp_logs_user_in(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $response = $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        // No OTP in cache = expired

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_otp_is_consumed_after_use(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456']);

        $this->assertNull(Cache::get("mfa.otp.{$user->id}"));
    }
}
