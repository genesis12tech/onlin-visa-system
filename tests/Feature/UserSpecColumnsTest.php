<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserSpecColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_officer_id_column(): void
    {
        $user = User::factory()->create(['officer_id' => 'OFF-001']);

        $this->assertEquals('OFF-001', $user->fresh()->officer_id);
    }

    public function test_officer_id_must_be_unique(): void
    {
        User::factory()->create(['officer_id' => 'OFF-002']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['officer_id' => 'OFF-002']);
    }

    public function test_user_has_last_login_at_column(): void
    {
        $user = User::factory()->create(['last_login_at' => now()]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_last_login_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create(['last_login_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $user->fresh()->last_login_at);
    }
}
