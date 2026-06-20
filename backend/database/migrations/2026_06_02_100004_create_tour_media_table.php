<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            $table->string('type', 20)->notNull();
            $table->string('url')->notNull();
            $table->string('thumbnail_url')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_media');
    }
};
