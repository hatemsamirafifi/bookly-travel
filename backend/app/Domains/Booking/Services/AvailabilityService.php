<?php

namespace App\Domains\Booking\Services;

use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Atomically checks and reserves availability within an active transaction.
     * Caller MUST wrap this in DB::transaction().
     *
     * Capacity is counted per departure pool:
     *  - option selected → only bookings for the same tour + option + date
     *    consume that pool, capped by the option's group_size_max (falling back
     *    to the tour's when the option does not narrow it);
     *  - no option → only bookings without an option consume the tour pool,
     *    capped by the tour's group_size_max (legacy behavior, unchanged).
     *
     * The counted status set is the same in both pools: a pending_payment,
     * confirmed or completed booking holds seats.
     */
    public function checkAndReserve(Tour $tour, string $date, int $requestedCount, ?TourOption $option = null): array
    {
        $query = DB::table('bookings')
            ->where('tour_id', $tour->id)
            ->where('tour_date', $date)
            ->whereIn('status', ['pending_payment', 'confirmed', 'completed'])
            ->lockForUpdate();

        if ($option !== null) {
            $query->where('tour_option_id', $option->id);
        } else {
            $query->whereNull('tour_option_id');
        }

        // PostgreSQL requires the row lock to stay inside the aggregate's subquery.
        $existing = DB::query()
            ->fromSub($query->select('participant_count'), 'locked_bookings')
            ->sum('participant_count');

        $capacity = $option !== null
            ? $option->effectiveCapacity($tour)
            : (int) ($tour->group_size_max ?? 10);

        $remaining = max(0, $capacity - $existing);

        return [
            'available' => $remaining >= $requestedCount,
            'remaining' => $remaining,
            'capacity' => $capacity,
        ];
    }
}
