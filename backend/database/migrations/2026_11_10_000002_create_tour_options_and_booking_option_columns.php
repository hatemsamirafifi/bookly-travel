<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive booking-option foundation.
 *
 * `tour_options` holds real, tour-scoped purchasable options (a specific
 * departure/variant with its own price, start time, capacity and pickup
 * instructions). Retiring an option flips `status` to `retired` and stamps
 * `retired_at` — rows are never deleted, so historical bookings keep a stable
 * reference.
 *
 * `bookings` gains a nullable `tour_option_id` plus a snapshot of the option
 * title / pickup instructions so a booking always renders what the traveler
 * bought, even after the option is renamed or retired. Every column is
 * nullable, so tours without options and all pre-existing bookings keep
 * working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            // Authored (source) strings. A later slice localizes these; the
            // booking snapshot stores whatever was authored at booking time.
            $table->string('title');
            $table->text('pickup_instructions')->nullable();
            $table->jsonb('guide_languages')->nullable();
            // Price per person in minor units (cents), same unit as tours.price_amount.
            $table->integer('price_amount');
            // Capacity of this departure pool; falls back to tours.group_size_max.
            $table->smallInteger('group_size_max')->nullable();
            $table->time('start_time')->nullable();
            // Optional 0-6 (Carbon dayOfWeek) allow-list; null/empty = every
            // operating weekday of the tour.
            $table->jsonb('weekdays')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // nullOnDelete: an option is retired rather than removed, but if one
            // is ever hard-deleted the booking (and its snapshot) survives.
            $table->foreignId('tour_option_id')
                ->nullable()
                ->constrained('tour_options')
                ->nullOnDelete();
            $table->string('tour_option_title')->nullable();
            $table->text('tour_option_pickup_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tour_option_id');
            $table->dropColumn(['tour_option_title', 'tour_option_pickup_instructions']);
        });

        Schema::dropIfExists('tour_options');
    }
};
