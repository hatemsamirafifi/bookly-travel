<?php

use App\Http\Middleware\RateLimitSearchMiddleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

// Spec 006 — the public search & discovery surface uses
// RateLimitSearchMiddleware (alias `rate.limit`), which emits the contract
// 429 body `{message, retry_after}` + `X-RateLimit-*` headers + localized
// message (search-api.md:120, category-destination-api.md:170). Keys are
// `rate_limit:{type}:{ip}`; clear them via the public `keyFor()` helper so the
// suite is isolated regardless of the internal key format.
beforeEach(function () {
    RateLimiter::clear(RateLimitSearchMiddleware::keyFor('search', '127.0.0.1'));
    RateLimiter::clear(RateLimitSearchMiddleware::keyFor('detail', '127.0.0.1'));
    RateLimiter::clear(RateLimitSearchMiddleware::keyFor('listing', '127.0.0.1'));
    RateLimiter::clear(RateLimitSearchMiddleware::keyFor('homepage', '127.0.0.1'));
    RateLimiter::clear(RateLimitSearchMiddleware::keyFor('sitemap', '127.0.0.1'));
});

it('returns 429 when search rate limit is exceeded', function () {
    // Hit the endpoint up to the configured limit (60/min for search)
    $limit = 60;
    for ($i = 0; $i < $limit; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    // The next request should be rate limited
    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429);
});

it('returns 429 with the contract JSON body when rate limited', function () {
    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('message', 'Too many search requests. Please wait and try again shortly.')
            ->has('retry_after')
            ->etc()
        );
});

it('includes Retry-After and X-RateLimit headers in the rate limit response', function () {
    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    getJson('/api/public/search/tours?locale=en')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('exposes X-RateLimit headers on successful responses', function () {
    getJson('/api/public/search/tours?locale=en')
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('localizes the rate limit message for the requested locale', function () {
    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=es');
    }

    getJson('/api/public/search/tours?locale=es')
        ->assertStatus(429)
        ->assertJsonPath(
            'message',
            'Demasiadas solicitudes de búsqueda. Espere e inténtelo de nuevo en breve.'
        );
});

it('different endpoints have separate rate limits', function () {
    // Exhaust search rate limit
    for ($i = 0; $i < 60; $i++) {
        getJson('/api/public/search/tours?locale=en');
    }

    // Search should be rate limited
    getJson('/api/public/search/tours?locale=en')->assertStatus(429);

    // But tour detail should still work (different bucket: 120/min)
    $response = getJson('/api/public/tours/any-tour?locale=en');
    // May return 404 (not found) but NOT 429 (rate limited)
    expect($response->status())->not->toBe(429);
});