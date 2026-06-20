<?php

namespace App\Domains\Auth\Actions;

use App\Jobs\SendVerificationEmail;
use App\Models\User;

class SendVerificationEmailAction
{
    /**
     * Dispatch the verification email job to the queue.
     *
     * @param  User  $user  The user to send the verification email to
     */
    public function execute(User $user): void
    {
        SendVerificationEmail::dispatch($user);
    }
}
