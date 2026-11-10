<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localized option texts + stable option ordering + booking snapshot locale.
 *
 * `tour_options` gains `sort_order` so option reordering is persistable: the
 * authored order participates in the source hash (reordering must invalidate
 * translations), while price/capacity/start-time/weekdays stay structured-only.
 *
 * `tour_option_translations` holds the generated derivative name/pickup texts,
 * keyed by the stable option id and the platform locale — mirroring the
 * row-per-locale pattern of `tour_translations`. Retired options keep their
 * rows (options are retired, never deleted), so re-activation restores texts.
 *
 * `bookings.tour_option_content_locale` records WHICH locale the snapshotted
 * title/pickup actually came from (`en`/`es`/`it`), so a booking always renders
 * the text the traveler was served, in the requested booking locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_options', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });

        Schema::create('tour_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_option_id')
                ->constrained('tour_options')
                ->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('pickup_instructions')->nullable();
            $table->timestamps();

            $table->unique(['tour_option_id', 'locale']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('tour_option_content_locale', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('tour_option_content_locale');
        });

        Schema::dropIfExists('tour_option_translations');

        Schema::table('tour_options', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
