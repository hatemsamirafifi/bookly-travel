<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // spec-007 F5: snapshot the tour start time at booking so the
            // cancellation-window and no_show cutoffs anchor to the actual
            // start, not `tour_date` midnight. Nullable — legacy rows and
            // bookings whose rule declares no start time fall back to the
            // configured `bookings.default_start_time`.
            $table->time('start_time')->nullable()->after('tour_date');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
    }
};
