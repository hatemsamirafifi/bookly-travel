<?php

namespace App\Domains\Auth\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $email  The email attempted (which could optionally be tied to a User)
     * @param  User|null  $user  The user account if it existed
     * @param  bool  $rejectedDueToLockout  Whether the attempt was rejected due to an active lockout
     */
    public function __construct(
        public string $email,
        public ?User $user = null,
        public bool $rejectedDueToLockout = false,
    ) {}
}
