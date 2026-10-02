<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_translations', function (Blueprint $table) {
            $table->jsonb('important_information')->nullable();
        });

        Schema::create('tour_translation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('locale', 2);
            $table->char('source_hash', 64);
            $table->char('translated_hash', 64)->nullable();
            $table->string('status', 16);
            $table->string('last_error_code', 40)->nullable();
            $table->timestamps();
            $table->unique(['tour_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_translation_states');
        Schema::table('tour_translations', function (Blueprint $table) {
            $table->dropColumn('important_information');
        });
    }
};
