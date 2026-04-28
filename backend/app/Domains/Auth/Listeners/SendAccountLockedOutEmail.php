<?php

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\AccountLockedOut;
use App\Mail\AccountLockedOutMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendAccountLockedOutEmail implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [10, 60, 300];

    /**
     * Handle the event.
     */
    public function handle(AccountLockedOut $event): void
    {
        Mail::to($event->user->email)->send(
            new AccountLockedOutMail($event->user)
        );
    }
}
