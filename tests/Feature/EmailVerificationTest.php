<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_notice_shown_to_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_verified_user_redirected_from_notice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('dashboard'));
    }

    public function test_email_can_be_verified_via_signed_url(): void
    {
        Event::fake();
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_verification_fails_with_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong@email.com')]
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
