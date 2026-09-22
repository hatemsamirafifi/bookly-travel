<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('stripe_payment_intent_id');
            $table->text('stripe_invoice_url')->nullable()->after('stripe_invoice_id');
            $table->text('stripe_invoice_pdf')->nullable()->after('stripe_invoice_url');
            $table->string('stripe_invoice_status', 30)->nullable()->after('stripe_invoice_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_invoice_id',
                'stripe_invoice_url',
                'stripe_invoice_pdf',
                'stripe_invoice_status',
            ]);
        });
    }
};
