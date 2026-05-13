<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompleteBookingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNotNull('tour_date')
            ->where('tour_date', '<', now()->startOfDay())
            ->each(function (Booking $booking) {
                $booking->update(['status' => Booking::STATUS_COMPLETED]);
            });
    }
}
