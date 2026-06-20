<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->nullable()
                ->constrained('tours')
                ->cascadeOnDelete();
            $table->foreignId('partner_id')
                ->constrained('partners')
                ->cascadeOnDelete();
            $table->jsonb('payload')->notNull();
            $table->string('status', 20)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('auto_saved_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['tour_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_drafts');
    }
};
