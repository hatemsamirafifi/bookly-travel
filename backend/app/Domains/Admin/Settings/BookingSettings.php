<?php

namespace App\Domains\Admin\Settings;

use Spatie\LaravelSettings\Settings;

class BookingSettings extends Settings
{
    public bool $allow_guest_checkout = true;
    public int $min_advance_booking_hours = 0;
    public int $default_booking_window_days = 365;
    public int $max_guests_per_booking = 20;
    public int $cancellation_cutoff_hours = 48;
    public int $auto_complete_after_days = 2;

    public static function group(): string
    {
        return 'booking';
    }
}