<?php

namespace App\Domains\Auth\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class LoginFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $email The email attempted (which could optionally be tied to a User)
     * @param User|null $user The user account if it existed
     */
    public function __construct(public string $email, public ?User $user = null)
    {
    }
}
