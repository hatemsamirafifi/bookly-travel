<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')
                ->unique()
                ->constrained('partners')
                ->cascadeOnDelete();
            $table->boolean('notify_new_booking')->default(true);
            $table->boolean('notify_cancellation')->default(true);
            $table->boolean('notify_daily_summary')->default(true);
            $table->boolean('notify_review_received')->default(true);
            $table->boolean('notify_tour_status_change')->default(true);
            $table->string('locale', 2)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settings');
    }
};