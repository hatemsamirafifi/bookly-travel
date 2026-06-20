<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutTravelerAction
{
    /**
     * Revoke the current Sanctum personal access token for the given user.
     *
     * Only the current token is deleted. Other active sessions remain valid.
     *
     * @param  User  $user  The authenticated user
     */
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
