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
     * The number of minutes until the verification link expires.
     */
    public const EXPIRATION_MINUTES = 60;

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
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->refresh();

        if ($this->user->hasVerifiedEmail() || $this->user->verification_email_sent_at) {
            return;
        }

        $updated = \App\Models\User::where('id', $this->user->id)
            ->whereNull('verification_email_sent_at')
            ->update(['verification_email_sent_at' => now()]);

        if (! $updated) {
            return;
        }

        // Generate a signed verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'auth.verify',
            now()->addMinutes(self::EXPIRATION_MINUTES),
            [
                'id' => $this->user->id,
                'hash' => sha1($this->user->email),
            ]
        );

        // Send the verification email
        \Mail::to($this->user->email)->send(
            new VerificationMail($this->user, $verificationUrl)
        );
    }
}