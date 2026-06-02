<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            $table->string('exception_type', 20)->notNull();
            $table->date('date')->notNull();
            $table->time('start_time')->nullable();
            $table->smallInteger('capacity')->nullable();
            $table->decimal('price_multiplier', 3, 2)->default('1.00');
            $table->string('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};