<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-level translation state tracking (Admin 2.0 Phase H, T-H01).
 *
 * One row per (translatable model, locale, field): status of the automatic
 * translation pipeline (missing/queued/translating/completed/outdated/failed/
 * needs_review/manually_edited), the SHA-256 hash of the English source the
 * current translation was produced from, provider/model provenance, retry
 * counters, and manual-edit attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_field_states', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 120);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 8);
            $table->string('field_name', 64);
            $table->string('status', 24)->default('missing')->index();
            $table->string('origin', 16)->default('auto');
            $table->char('source_hash', 64)->nullable();
            $table->char('translated_source_hash', 64)->nullable();
            $table->char('translated_hash', 64)->nullable();
            $table->string('provider', 48)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_translated_at')->nullable();
            $table->timestamp('manually_edited_at')->nullable();
            $table->foreignId('manually_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field_name'], 'translation_field_states_unique_field');
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'translation_field_states_model_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_field_states');
    }
};
