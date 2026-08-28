<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/*
| Validates the Spec-016 remediation (CR-009): the duplicate, no-throttle
| `Route::prefix('blog')` group that shadowed the throttled group has been
| removed. Laravel matches the first registered route for a method+URI, so the
| former dead-code block would always win and the intended throttle:blog /
| throttle:blog_detail limiters never applied. This test asserts the live
| route now enforces throttle:blog by observing a 429 once the budget is spent.
|
| The 'blog' limiter is temporarily tightened so the threshold is reached in a
| handful of requests, then restored to the production 120/min definition so the
| override cannot leak into subsequent test files in the same process.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    RateLimiter::for('blog', fn () => Limit::perMinute(3)->by('blog-throttle-test'));
});

afterEach(function () {
    // Restore the production definition so other test files are unaffected.
    RateLimiter::for('blog', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
});

test('blog list endpoint enforces the throttle:blog rate limiter', function () {
    makeBlogCategory();
    makeBlogPost();

    // Three requests fit within the tightened 3/min budget.
    for ($i = 0; $i < 3; $i++) {
        $this->getJson('/api/public/blog?locale=en')->assertOk();
    }

    // The fourth request exceeds the budget → 429. Before the fix the
    // no-throttle duplicate route would have matched first and returned 200.
    $this->getJson('/api/public/blog?locale=en')->assertStatus(429);
});
