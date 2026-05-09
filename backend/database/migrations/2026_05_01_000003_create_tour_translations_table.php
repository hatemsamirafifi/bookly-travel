<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->jsonb('highlights')->nullable();
            $table->jsonb('inclusions')->nullable();
            $table->jsonb('exclusions')->nullable();
            $table->text('meeting_point')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_translations');
    }
};
