<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings');
            $table->foreignId('tour_id')->constrained('tours');
            $table->foreignId('traveler_id')->constrained('users');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('visible');
            $table->string('locale', 5)->default('en');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'status', 'created_at'], 'idx_tour_status_date');
        });

        // SQLite does not support ALTER TABLE ADD CONSTRAINT CHECK.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_rating_range CHECK (rating >= 1 AND rating <= 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
