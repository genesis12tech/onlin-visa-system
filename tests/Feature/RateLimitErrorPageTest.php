<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_error_page_renders_on_429(): void
    {
        // Force a 429 via the stripe webhook rate limiter (60 req/min limit)
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/webhooks/stripe');
        }

        $response = $this->postJson('/webhooks/stripe');
        $response->assertStatus(429);
    }

    public function test_429_blade_view_exists(): void
    {
        $this->assertTrue(
            view()->exists('errors.429'),
            'resources/views/errors/429.blade.php must exist'
        );
    }

    public function test_429_view_renders_without_errors(): void
    {
        $html = view('errors.429')->render();

        $this->assertStringContainsString('Too many requests', $html);
    }
}
