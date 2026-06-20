<?php

namespace App\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Jobs\SendPasswordResetEmail;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Handle forgot password request.
     * Always returns the same response to prevent email enumeration.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $normalizedEmail = strtolower(trim($request->input('email')));
        $user = User::where('email', $normalizedEmail)->first();

        if ($user) {
            if ($user->hasVerifiedEmail()) {
                dispatch(new SendPasswordResetEmail($user));
            } else {
                // For unverified emails, send verification email instead
                dispatch(new SendVerificationEmail($user));
            }
        }

        // Always return the same generic response
        return response()->json([
            'message' => 'If the email exists, a reset link has been sent.',
        ], 200);
    }
}
