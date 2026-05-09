<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;

class AuditService
{
    public function log(
        Booking $booking,
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $beforeState,
        string $afterState,
        ?array $metadata = null,
    ): BookingAuditLog {
        return BookingAuditLog::create([
            'booking_id' => $booking->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'metadata' => $metadata,
        ]);
    }
}
