<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 014 (FR-018, SC-008, R3): voucher freshness columns on bookings.
 *
 * `voucher_generated_at` records when a voucher PDF was last (re)generated.
 * `voucher_content_hash` stores a hash of the voucher-relevant fields
 * (tour_date, participant_count, locale-resolved tour title, total, currency);
 * a mismatch with the current hash triggers regeneration in
 * VoucherService::getOrGenerate(). Both columns are nullable and backfilled
 * NULL — existing bookings simply regenerate on first download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('voucher_generated_at')->nullable()->after('confirmation_email_sent_at');
            $table->string('voucher_content_hash', 64)->nullable()->after('voucher_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['voucher_generated_at', 'voucher_content_hash']);
        });
    }
};
