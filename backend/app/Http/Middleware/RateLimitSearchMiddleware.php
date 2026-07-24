<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiter for the public search & discovery surface (spec 006).
 *
 * Scoped to the search/detail/listing/homepage/sitemap routes — NOT the auth,
 * booking, or reviews throttlers — so its contract 429 body
 * `{message, retry_after}` + `X-RateLimit-*` headers + localized message
 * (search-api.md / category-destination-api.md) only reaches those endpoints.
 *
 * Keys are namespaced `rate_limit:{type}:{ip}` (exposed via `keyFor()` so the
 * test suite can clear them deterministically without hard-coding the format).
 */
class RateLimitSearchMiddleware
{
    protected RateLimiter $limiter;

    protected array $limits = [
        'search' => 60,
        'detail' => 120,
        'listing' => 120,
        'homepage' => 120,
        'sitemap' => 10,
    ];

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next, string $type = 'search'): Response
    {
        $maxAttempts = $this->limits[$type] ?? 60;
        $key = $this->resolveKey($request, $type);

        // Apply the request locale so the localized 429 message (`__()`) is
        // resolved in the caller's locale. There is no global locale
        // middleware on the public API, so the limiter resolves it itself
        // for its own response; downstream actions read translations explicitly.
        app()->setLocale($this->resolveLocale($request));

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => $this->message($type),
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-RateLimit-Limit', $maxAttempts);
            $response->headers->set(
                'X-RateLimit-Remaining',
                max(0, $maxAttempts - $this->limiter->attempts($key))
            );
        }

        return $response;
    }

    /**
     * Build the cache key for a given type + client IP. Public so tests can
     * clear the limiter state for a specific bucket without guessing the
     * internal format (`RateLimiter::clear(RateLimitSearchMiddleware::keyFor(...))`).
     */
    public static function keyFor(string $type, string $ip): string
    {
        return sprintf('rate_limit:%s:%s', $type, $ip);
    }

    protected function resolveKey(Request $request, string $type): string
    {
        return self::keyFor($type, $request->ip());
    }

    /**
     * Resolve the request locale (validated against `app.supported_locales`)
     * from the `locale` query param these routes all require, falling back to
     * `Accept-Language` then English.
     */
    protected function resolveLocale(Request $request): string
    {
        $locale = $request->query('locale') ?? $request->header('Accept-Language') ?? 'en';

        if (strlen($locale) > 2) {
            $locale = substr($locale, 0, 2);
        }

        $supported = config('app.supported_locales', ['en', 'es', 'it']);

        return in_array($locale, $supported, true) ? $locale : 'en';
    }

    /**
     * Localized 429 message. The search endpoint gets the search-specific
     * wording (search-api.md:124); everything else uses the generic wording
     * (category-destination-api.md:174).
     */
    protected function message(string $type): string
    {
        return $type === 'search'
            ? __('search.rate_limit.search')
            : __('search.rate_limit.default');
    }
}