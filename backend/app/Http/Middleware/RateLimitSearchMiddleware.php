<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => __('search.rate_limit_exceeded'),
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
                $maxAttempts - $this->limiter->attempts($key)
            );
        }

        return $response;
    }

    protected function resolveKey(Request $request, string $type): string
    {
        return sprintf(
            'rate_limit:%s:%s',
            $type,
            $request->ip()
        );
    }
}
