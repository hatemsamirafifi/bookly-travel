<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('traveler_id')->constrained('users');
            $table->foreignId('tour_id')->constrained('tours');
            $table->date('tour_date');
            $table->integer('participant_count');
            $table->integer('price_per_person');
            $table->integer('total_price');
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('confirmed');
            $table->string('idempotency_key', 36)->unique();
            $table->text('cancellation_policy')->nullable();
            $table->integer('cancellation_window_hours')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('confirmation_email_sent_at')->nullable();
            $table->string('locale', 2)->default('en');
            $table->timestamps();

            $table->index(['traveler_id', 'created_at'], 'bookings_traveler_id_created_at_idx');
            $table->index(['tour_id', 'tour_date'], 'bookings_tour_id_tour_date_idx');
            $table->index('status', 'bookings_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
