<?php

namespace App\Domains\Booking\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitBookingMiddleware
{
    public function handle(Request $request, Closure $next, string $tier = 'create')
    {
        $key = $this->resolveKey($request, $tier);
        $maxAttempts = $tier === 'create' ? 10 : 120;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $availableIn = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many booking attempts. Please wait and try again.',
                'retry_after' => $availableIn,
            ], 429)->withHeaders([
                'Retry-After' => $availableIn,
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }

    private function resolveKey(Request $request, string $tier): string
    {
        $identifier = $request->user()?->id ?? $request->ip();

        return 'rate_limit:booking:' . $tier . ':' . $identifier;
    }
}
