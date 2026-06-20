<?php

namespace App\Jobs;

use App\Mail\VerificationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->refresh();

        if ($this->user->hasVerifiedEmail() || $this->user->verification_email_sent_at) {
            return;
        }

        // Generate a signed verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'auth.verify',
            now()->addMinutes(config('mail.verification.expiration_minutes')),
            [
                'id' => $this->user->id,
                'hash' => sha1($this->user->email),
            ]
        );

        try {
            // Send the verification email first; transport failures will bubble
            // up and trigger Laravel's retry mechanism (tries/backoff).
            \Mail::to($this->user->email)->send(
                new VerificationMail($this->user, $verificationUrl)
            );
        } catch (\Throwable $e) {
            // Re-throw so the job is retried; do NOT mark as sent on failure.
            throw $e;
        }

        // Only mark as sent after the email was successfully dispatched.
        // The conditional update keeps the operation idempotent under
        // concurrent workers.
        $updated = User::where('id', $this->user->id)
            ->whereNull('verification_email_sent_at')
            ->update(['verification_email_sent_at' => now()]);

        if (! $updated) {
            // Another worker already recorded the send; nothing more to do.
            return;
        }
    }
}
