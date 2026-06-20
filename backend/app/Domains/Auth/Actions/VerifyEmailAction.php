<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\EmailVerified;
use App\Models\User;

class VerifyEmailAction
{
    /**
     * Verify a traveler's email address.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function execute(User $user, string $hash): array
    {
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'Email already verified.',
            ];
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return [
                'success' => false,
                'message' => 'Invalid verification link.',
            ];
        }

        if ($user->markEmailAsVerified()) {
            event(new EmailVerified($user));
        }

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
        ];
    }
}
