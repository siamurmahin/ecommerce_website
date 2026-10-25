<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_browsing_does_not_use_up_the_order_limit(): void
    {
        // Plain `throttle:N,M` shares one counter per IP across routes; typing in search must not block checkout.
        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/api/v1/search/suggest?q=attar')->assertOk();
        }

        $this->postJson('/api/v1/orders', [])->assertStatus(422);
    }

    public function test_each_route_keeps_its_own_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/orders', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/orders', [])->assertStatus(429);
        $this->postJson('/api/v1/orders/track', [])->assertStatus(422);
    }
}
