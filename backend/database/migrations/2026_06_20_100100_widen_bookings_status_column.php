<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen bookings.status to accommodate `cancellation_requested` (21 chars),
 * used by the partner cancellation-request flow (Spec 008) and admin booking
 * oversight transitions (Spec 013, FR-009). The original create migration
 * sized the column at varchar(20), which silently truncated the value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE bookings ALTER COLUMN status TYPE varchar(32)');
        }
    }

    public function down(): void
    {
        $affected = DB::table('bookings')
            ->where('status', 'cancellation_requested')
            ->count();

        if ($affected > 0) {
            throw new \RuntimeException(
                "Cannot narrow bookings.status to varchar(20): {$affected} row(s) still use 'cancellation_requested'."
            );
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE bookings ALTER COLUMN status TYPE varchar(20)');
        }
    }
};