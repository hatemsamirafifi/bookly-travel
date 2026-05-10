<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('stripe_refund_id')->nullable()->index();
            $table->string('type', 20);
            $table->integer('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('pending');
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand', 20)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
