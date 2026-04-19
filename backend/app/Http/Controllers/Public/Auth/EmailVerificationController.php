<?php

namespace App\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EmailVerificationController
 *
 * Handles the email verification signed URL callback.
 *
 * NOTE: Full implementation is deferred to Phase 9 (Email Verification Flow)
 * per tasks.md T081-T082 in 001-traveler-auth. This stub exists so the named
 * route 'auth.verify' can be registered at boot time without a class-not-found
 * error. The route is required by SendVerificationEmail job for URL generation.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verify the traveler's email address via a signed URL.
     *
     * @todo Full implementation in Phase 9 (VerifyEmailAction, signed URL validation,
     *       email_verified_at update, EmailVerified event dispatch).
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        // Stub: Phase 9 will implement full signed-URL verification logic.
        return response()->json([
            'message' => 'Email verification endpoint — implementation pending (Phase 9).',
        ], 501);
    }
}
