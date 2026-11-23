<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EGM-005 Slice S1 — tiered option pricing + booking links (design §3.2.3–4,
 * decisions D3, D4, D5).
 *
 * `pricing_tiers` becomes option-scoped: `tour_option_id` (nullable — legacy
 * tour-level tiers keep working), `price_amount` in integer minor units
 * (cents, D5), `currency`, and `sort_order`. `bookings` gains the explicit
 * departure reference, the resolved pricing tier, and the immutable
 * `pricing_snapshot` so a past booking always shows the price it was sold at.
 *
 * Every column is nullable/default-backed; bookings reference the new tables
 * with nullOnDelete so historical rows survive drops. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->foreignId('tour_option_id')
                ->nullable()
                ->constrained('tour_options')
                ->cascadeOnDelete();
            // Authoritative per-person price in integer minor units (cents).
            // Null → derived from the legacy decimal `price` (× 100).
            $table->integer('price_amount')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->integer('sort_order')->default(0);

            $table->index(['tour_option_id', 'min_participants', 'max_participants']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('tour_departure_id')
                ->nullable()
                ->constrained('tour_departures')
                ->nullOnDelete();
            $table->foreignId('pricing_tier_id')
                ->nullable()
                ->constrained('pricing_tiers')
                ->nullOnDelete();
            $table->jsonb('pricing_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tour_departure_id');
            $table->dropConstrainedForeignId('pricing_tier_id');
            $table->dropColumn('pricing_snapshot');
        });

        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->dropIndex(['tour_option_id', 'min_participants', 'max_participants']);
            $table->dropConstrainedForeignId('tour_option_id');
            $table->dropColumn(['price_amount', 'currency', 'sort_order']);
        });
    }
};
