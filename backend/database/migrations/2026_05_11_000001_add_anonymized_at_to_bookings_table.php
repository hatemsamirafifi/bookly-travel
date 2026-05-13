<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add anonymized_at column to bookings table (FR-029).
 *
 * When set, indicates the booking's personal identifiers have been irreversibly
 * anonymized by the AnonymizeExpiredBookingData job (90 days post-tour).
 * The column also acts as the idempotency guard for the anonymization job —
 * rows with a non-null value are skipped on subsequent runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('locale');
            $table->index('anonymized_at', 'bookings_anonymized_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_anonymized_at_idx');
            $table->dropColumn('anonymized_at');
        });
    }
};
