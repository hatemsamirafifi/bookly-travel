<?php

use App\Domains\Booking\Jobs\AnonymizeExpiredBookingData;
use App\Domains\Booking\Jobs\CompleteBookingJob;
use App\Domains\Payment\Jobs\ExpirePendingBookingsJob;
use App\Jobs\AnonymizeStaleGuestIdentities;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-029: anonymize personal identifiers on bookings 90+ days past tour date
Schedule::job(new AnonymizeExpiredBookingData)
    ->dailyAt('02:00')
    ->name('anonymize-expired-booking-data')
    ->withoutOverlapping();

Schedule::job(new ExpirePendingBookingsJob)
    ->everyFiveMinutes()
    ->name('expire-pending-bookings')
    ->withoutOverlapping(10);

Schedule::job(new CompleteBookingJob)
    ->hourly()
    ->name('complete-bookings')
    ->withoutOverlapping();

// Anonymize stale guest identities with last booking > 24 months and no future bookings
Schedule::job(new AnonymizeStaleGuestIdentities)
    ->dailyAt('03:00')
    ->name('anonymize-stale-guest-identities')
    ->withoutOverlapping();

Schedule::command('partner-invitations:expire')
    ->daily()
    ->name('expire-partner-invitations')
    ->withoutOverlapping();
