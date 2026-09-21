<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->unique()->after('user_id');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_account_id');
            $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
            $table->boolean('stripe_onboarding_completed')->default(false)->after('stripe_payouts_enabled');
            $table->boolean('stripe_details_submitted')->default(false)->after('stripe_onboarding_completed');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_onboarding_completed',
                'stripe_details_submitted',
            ]);
        });
    }
};
