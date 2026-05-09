<?php

namespace App\Domains\Booking\Services;

use App\Models\Tour;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Atomically checks and reserves availability within an active transaction.
     * Caller MUST wrap this in DB::transaction().
     */
    public function checkAndReserve(Tour $tour, string $date, int $requestedCount): array
    {
        $rows = DB::table('bookings')
            ->where('tour_id', $tour->id)
            ->where('tour_date', $date)
            ->whereIn('status', ['confirmed', 'completed'])
            ->lockForUpdate()
            ->get(['participant_count']);

        $existing = $rows->sum('participant_count');

        $capacity = $tour->group_size_max;
        $remaining = max(0, $capacity - $existing);

        return [
            'available' => $remaining >= $requestedCount,
            'remaining' => $remaining,
        ];
    }
}
