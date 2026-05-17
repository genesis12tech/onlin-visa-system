<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clear the webhook rate limiter to prevent state leaking into other tests.
        // The ThrottleRequests middleware stores the key as md5($limiterName . $limit->key).
        RateLimiter::clear(md5('webhook'.'127.0.0.1'));
        parent::tearDown();
    }

    public function test_stripe_webhook_returns_429_after_rate_limit(): void
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
