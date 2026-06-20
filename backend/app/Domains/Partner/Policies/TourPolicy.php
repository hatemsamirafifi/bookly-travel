<?php

namespace App\Domains\Partner\Policies;

use App\Models\Tour;
use App\Models\User;

class TourPolicy
{
    public function view(User $user, Tour $tour): bool
    {
        return $tour->partner_id === $user->id;
    }

    public function update(User $user, Tour $tour): bool
    {
        return $tour->partner_id === $user->id;
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $tour->partner_id === $user->id;
    }

    public function archive(User $user, Tour $tour): bool
    {
        if ($tour->partner_id !== $user->id) {
            return false;
        }

        // Block archiving if there are future confirmed bookings
        $hasFutureBookings = $tour->bookings()
            ->whereIn('status', ['confirmed'])
            ->where('tour_date', '>=', now()->toDateString())
            ->exists();

        return ! $hasFutureBookings;
    }
}
