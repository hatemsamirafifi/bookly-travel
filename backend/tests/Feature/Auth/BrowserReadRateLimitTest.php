<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('allows a higher browser read budget only in an opted-in local stack', function (string $environment, int $configured, int $expected) {
    app()->instance('env', $environment);
    config(['app.browser_test_read_rate_limit' => $configured]);
    $request = Request::create('/api/public/auth/me');

    foreach (['traveler', 'booking.get'] as $name) {
        $limiter = RateLimiter::limiter($name);
        expect($limiter($request)->maxAttempts)->toBe($expected);
    }

    // Raising read throughput must not change the booking write budget.
    $bookingLimiter = RateLimiter::limiter('booking.create');
    expect($bookingLimiter($request)->maxAttempts)->toBe(10);
})->with([
    'local default' => ['local', 120, 120],
    'local browser stack' => ['local', 6000, 6000],
    'local cannot lower the normal budget' => ['local', 0, 120],
    'production ignores browser override' => ['production', 6000, 120],
    'staging ignores browser override' => ['staging', 6000, 120],
    'PHP tests keep normal limits' => ['testing', 6000, 120],
]);
