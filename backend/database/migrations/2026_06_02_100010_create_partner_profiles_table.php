<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')
                ->unique()
                ->constrained('partners')
                ->cascadeOnDelete();
            $table->string('company_name')->notNull();
            $table->text('business_description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('contact_email')->notNull();
            $table->string('contact_phone')->nullable();
            $table->string('website')->nullable();
            $table->jsonb('business_address')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('payout_holder_name')->nullable();
            $table->string('payout_bank_name')->nullable();
            $table->string('payout_account_number')->nullable();
            $table->string('payout_iban')->nullable();
            $table->string('payout_swift_bic')->nullable();
            $table->string('payout_country', 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
