<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EGM-005 Slice S1 — additive draft enhancements (design §3.2.1).
 *
 * `tour_drafts` gains the D1/D2 contract fields: a client-generated
 * `creation_id` (first-save idempotency, D2), a `concurrency_token` for
 * optimistic multi-tab locking (D1), the wizard `current_step`, and the
 * explicitly selected `source_locale` (language-first unselected state is
 * preserved by leaving it null). Every column is nullable, so existing
 * drafts keep working unchanged on the active, non-empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_drafts', function (Blueprint $table) {
            $table->uuid('creation_id')->nullable();
            $table->string('concurrency_token', 64)->nullable();
            $table->string('current_step', 32)->nullable()->default('language');
            $table->string('source_locale', 5)->nullable();

            $table->index(['partner_id', 'creation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tour_drafts', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'creation_id']);
            $table->dropColumn(['creation_id', 'concurrency_token', 'current_step', 'source_locale']);
        });
    }
};
