<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EGM-005 Slice S1 — explicit departures (design §3.2.2, decisions D3, D4).
 *
 * `tour_departures` holds concrete bookable occurrences of a tour option:
 * an authoritative UTC `starts_at` plus the IANA `timezone` and cached local
 * date/time used for display. Capacity is independent per departure (no
 * shared pools). `recurrence_rule_id` traces the availability rule that
 * generated the row and is nulled (not cascaded) when the rule is removed.
 *
 * New table only — nothing existing is touched, so the migration is
 * non-destructive and fully reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            $table->foreignId('tour_option_id')
                ->constrained('tour_options')
                ->cascadeOnDelete();
            // UTC authoritative departure moment.
            $table->timestampTz('starts_at');
            // IANA timezone of the operating location (e.g. Europe/Rome).
            $table->string('timezone', 64)->default('UTC');
            // Cached local calendar date / start time (display surface).
            $table->date('local_date');
            $table->time('local_time');
            // Independent per-departure capacity (D4).
            $table->integer('capacity');
            // Hours prior to starts_at when bookings close.
            $table->integer('booking_cutoff_hours')->default(2);
            // 'active', 'cancelled', 'completed'.
            $table->string('status', 32)->default('active');
            $table->foreignId('recurrence_rule_id')
                ->nullable()
                ->constrained('availability_rules')
                ->nullOnDelete();
            $table->timestamps();

            // One departure per option at a given UTC instant.
            $table->unique(['tour_option_id', 'starts_at']);
            // Public availability lookups.
            $table->index(['tour_id', 'tour_option_id', 'local_date', 'status']);
            $table->index(['starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_departures');
    }
};
