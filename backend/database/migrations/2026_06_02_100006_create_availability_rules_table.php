<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            $table->string('rule_type', 20)->notNull();
            $table->jsonb('days_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->smallInteger('capacity')->notNull();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};