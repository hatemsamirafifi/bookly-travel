<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2: the single-column `payments.stripe_payment_intent_id` unique index forced
 * `ProcessRefundAction` to append a `_refund` suffix to the refund row's intent
 * id (fragile — breaks webhook lookup-by-intent and is not a real Stripe id).
 *
 * Replace it with a composite unique on (stripe_payment_intent_id, type) so one
 * charge and one refund per intent coexist using the REAL intent id, and add a
 * plain lookup index on stripe_payment_intent_id for the webhook handler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_stripe_payment_intent_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['stripe_payment_intent_id', 'type'], 'payments_intent_type_unique');
            $table->index('stripe_payment_intent_id', 'payments_intent_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_intent_id_idx');
            $table->dropUnique('payments_intent_type_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('stripe_payment_intent_id', 'payments_stripe_payment_intent_id_unique');
        });
    }
};
