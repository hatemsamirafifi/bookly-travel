<?php

namespace App\Domains\Auth\Listeners;

use App\Models\AuthAuditLog;
use Illuminate\Support\Str;

class LogAuthEvent
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $eventTypeMap = [
            'TravelerRegistered' => 'registration',
            'TravelerLoggedIn' => 'login_success',
            'LoginFailed' => 'login_failed',
            'AccountLockedOut' => 'account_lockout',
            'PasswordReset' => 'password_reset_completed',
            'PasswordChanged' => 'password_changed',
            'EmailVerified' => 'email_verified',
            'GuestConvertedToAccount' => 'guest_converted',
        ];

        $classBasename = class_basename($event);
        $eventType = $eventTypeMap[$classBasename] ?? Str::snake($classBasename);

        $userId = null;
        if (property_exists($event, 'user') && $event->user) {
            $userId = $event->user->id;
        }

        $metadata = [];
        if (property_exists($event, 'email') && $event->email) {
            $metadata['email'] = hash_hmac('sha256', strtolower(trim($event->email)), config('app.key'));
        }
        if (property_exists($event, 'rejectedDueToLockout') && $event->rejectedDueToLockout) {
            $metadata['rejected_due_to_lockout'] = true;
        }

        AuthAuditLog::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ? Str::limit(request()->userAgent(), 500) : null,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}
