<?php

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\AccountLockedOut;
use App\Mail\AccountLockedOutMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an email notification when a traveler's account is locked out.
 *
 * Idempotent: checks last_lockout_email_sent_at against current locked_until
 * before sending. On retry, if the timestamps match the email was already sent
 * and is skipped. Satisfies constitution's retry-safety mandate (§6).
 */
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
        $user = $event->user->fresh();

        if (
            $user->last_lockout_email_sent_at instanceof \Carbon\Carbon &&
            $user->last_lockout_email_sent_at->equalTo($user->locked_until)
        ) {
            return;
        }

        Mail::to($user->email)->send(
            new AccountLockedOutMail($user)
        );

        $user->update([
            'last_lockout_email_sent_at' => $user->locked_until,
        ]);
    }
}
