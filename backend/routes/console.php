<?php

use App\Domains\Booking\Jobs\AnonymizeExpiredBookingData;
use App\Domains\Payment\Jobs\ExpirePendingBookingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-029: anonymize personal identifiers on bookings 90+ days past tour date
Schedule::job(new AnonymizeExpiredBookingData())
    ->dailyAt('02:00')
    ->name('anonymize-expired-booking-data')
    ->withoutOverlapping();

Schedule::job(new ExpirePendingBookingsJob())
    ->everyFiveMinutes()
    ->name('expire-pending-bookings')
    ->withoutOverlapping();
