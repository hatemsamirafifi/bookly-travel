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
        $eventType = class_basename($event);
        
        $userId = null;
        if (isset($event->user) && $event->user) {
            $userId = $event->user->id;
        }

        $metadata = [];
        if (isset($event->email)) {
            $metadata['email'] = $event->email;
        }

        AuthAuditLog::create([
            'user_id' => $userId,
            'event_type' => Str::snake($eventType),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ? Str::limit(request()->userAgent(), 500) : null,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}
