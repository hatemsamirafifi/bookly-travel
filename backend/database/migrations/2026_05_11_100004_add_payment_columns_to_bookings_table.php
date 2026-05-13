<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->index()->after('idempotency_key');
            $table->timestamp('payment_confirmed_at')->nullable()->after('stripe_payment_intent_id');
            $table->timestamp('pending_expires_at')->nullable()->index()->after('payment_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_intent_id', 'payment_confirmed_at', 'pending_expires_at']);
        });
    }
};
