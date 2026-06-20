<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Actions\SendVerificationEmailAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ResendVerificationController extends Controller
{
    /**
     * Resend the verification email.
     */
    public function __invoke(Request $request, SendVerificationEmailAction $action): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 400);
        }

        $key = 'resend-verification:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 3600); // 3 attempts per hour

        $action->execute($user);

        return response()->json([
            'message' => 'Verification email sent.',
        ], 200);
    }
}
