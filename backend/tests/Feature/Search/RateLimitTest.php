<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

it('returns 429 when search rate limit is exceeded', function () {
    // Clear any existing rate limiter state
    RateLimiter::clear('search');

    // Hit the endpoint up to the configured limit (60/min for search)
    $limit = 60;
    for ($i = 0; $i < $limit; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    // The next request should be rate limited
    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429);
});

it('returns 429 with JSON error body when rate limited', function () {
    RateLimiter::clear('search');

    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429)
        ->assertJson(fn (AssertableJson $json) => $json->has('message')
            ->etc()
        );
});

it('includes Retry-After header in rate limit response', function () {
    RateLimiter::clear('search');

    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('different endpoints have separate rate limits', function () {
    RateLimiter::clear('search');
    RateLimiter::clear('detail');

    // Exhaust search rate limit
    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    // Search should be rate limited
    getJson('/api/public/search/tours?locale=en')->assertStatus(429);

    // But tour detail should still work (different bucket: 120/min)
    getJson('/api/public/tours/any-tour?locale=en')
        // May return 404 (not found) but NOT 429 (rate limited)
        ->assertStatus(fn (int $status) => $status !== 429);
});
