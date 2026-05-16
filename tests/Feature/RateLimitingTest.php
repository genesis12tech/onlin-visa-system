<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('webhook');
        RateLimiter::clear('document-download');
    }

    public function test_webhook_rate_limiter_is_defined(): void
    {
        $this->assertNotNull(RateLimiter::limiter('webhook'));
    }

    public function test_document_download_rate_limiter_is_defined(): void
    {
        $this->assertNotNull(RateLimiter::limiter('document-download'));
    }

    public function test_stripe_webhook_returns_429_after_limit_exceeded(): void
    {
        // Exhaust the limit
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/webhooks/stripe');
        }

        // 61st request should be rate limited
        $response = $this->postJson('/webhooks/stripe');
        $response->assertStatus(429);
    }
}
