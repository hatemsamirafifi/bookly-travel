<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends the Sanctum token's `expires_at` on each authenticated request,
 * implementing sliding-window session expiry (FR-012: 7 days of inactivity).
 *
 * Sanctum's built-in `expiration` config prunes tokens whose `last_used_at`
 * is older than the configured minutes. This middleware additionally pushes
 * `expires_at` forward so the token remains valid as long as the user is
 * active. Tokens that have already expired are rejected by Sanctum's guard
 * before this middleware runs.
 */
class RefreshTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $expiration = config('sanctum.expiration');

            if ($expiration) {
                $token->forceFill([
                    'expires_at' => now()->addMinutes($expiration),
                ])->save();
            }
        }

        return $next($request);
    }
}
