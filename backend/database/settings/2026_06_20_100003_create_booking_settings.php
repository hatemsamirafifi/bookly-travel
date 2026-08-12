<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.allow_guest_checkout', true);
        $this->migrator->add('booking.min_advance_booking_hours', 0);
        $this->migrator->add('booking.default_booking_window_days', 365);
        $this->migrator->add('booking.max_guests_per_booking', 20);
        $this->migrator->add('booking.cancellation_cutoff_hours', 48);
        $this->migrator->add('booking.auto_complete_after_days', 2);
    }
};
