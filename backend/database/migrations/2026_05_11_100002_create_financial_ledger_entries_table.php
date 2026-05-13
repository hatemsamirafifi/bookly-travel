<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('payment_id')->constrained('payments');
            $table->string('entry_type', 10);
            $table->integer('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('actor', 20);
            $table->string('description', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
            $table->index(['payment_id']);
            $table->index(['entry_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
    }
};
