<?php

namespace App\Jobs;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

class SendPasswordResetEmail implements ShouldQueue
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
        $token = Password::createToken($this->user);

        $resetUrl = url('/auth/reset-password?email=' . urlencode($this->user->email) . '&token=' . $token);

        try {
            \Mail::to($this->user->email)->send(
                new PasswordResetMail($this->user, $resetUrl)
            );
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
