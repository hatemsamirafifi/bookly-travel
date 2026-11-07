<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;

it('strictly preserves read rate limits across environments and guards against fixture opt-in outside testing/local', function (string $environment, bool $fixtureOptIn, int $configuredLimit, int $expectedLimit) {
    app()['env'] = $environment;
    Config::set('app.browser_test_read_rate_limit', $configuredLimit);
    Config::set('app.allow_admin_e2e_fixtures', $fixtureOptIn);
    $request = Request::create('/api/public/auth/me');

    foreach (['traveler', 'booking.get'] as $name) {
        $limiter = RateLimiter::limiter($name);
        expect($limiter($request)->maxAttempts)->toBe($expectedLimit);
    }

    // Raising read throughput must NEVER change the booking write budget.
    $bookingLimiter = RateLimiter::limiter('booking.create');
    expect($bookingLimiter($request)->maxAttempts)->toBe(10);
})->with([
    'local default' => ['local', false, 120, 120],
    'local browser stack' => ['local', false, 6000, 6000],
    'local cannot lower normal budget' => ['local', false, 0, 120],
    'production without fixture flag' => ['production', false, 6000, 120],
    'production with fixture flag accidentally enabled' => ['production', true, 6000, 120],
    'staging without fixture flag' => ['staging', false, 6000, 120],
    'staging with fixture flag accidentally enabled' => ['staging', true, 6000, 120],
    'testing without opt-in (unit tests)' => ['testing', false, 6000, 120],
    'testing with opt-in (browser CI)' => ['testing', true, 6000, 6000],
])->after(function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', false);
    Config::set('app.browser_test_read_rate_limit', 120);
});

it('strictly preserves auth brute-force protection in production and staging even if fixture opt-in is set', function (string $environment, bool $fixtureOptIn, int $expectedLimit) {
    app()['env'] = $environment;
    Config::set('app.browser_test_read_rate_limit', 6000);
    Config::set('app.allow_admin_e2e_fixtures', $fixtureOptIn);
    $request = Request::create('/api/public/auth/login');

    $authLimiter = RateLimiter::limiter('auth');
    expect($authLimiter($request)->maxAttempts)->toBe($expectedLimit);
})->with([
    'production strict 10/min default' => ['production', false, 10],
    'production strict 10/min even if fixture flag enabled' => ['production', true, 10],
    'staging strict 10/min default' => ['staging', false, 10],
    'staging strict 10/min even if fixture flag enabled' => ['staging', true, 10],
    'testing unit test strict 10/min' => ['testing', false, 10],
    'testing browser CI elevated budget' => ['testing', true, 6000],
    'local browser stack elevated budget' => ['local', false, 6000],
])->after(function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', false);
    Config::set('app.browser_test_read_rate_limit', 120);
});
