<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Partner dashboard specific columns added to existing tours table
            $table->string('difficulty_level', 20)->nullable();
            $table->jsonb('itinerary')->nullable();
            $table->jsonb('inclusions')->nullable();
            $table->string('meeting_point')->nullable();
            $table->foreignId('active_draft_id')->nullable()->constrained('tour_drafts')->nullOnDelete();
            $table->decimal('price_from', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'difficulty_level',
                'itinerary',
                'inclusions',
                'meeting_point',
                'active_draft_id',
                'price_from',
                'currency',
                'submitted_at',
                'published_at',
                'archived_at',
            ]);
        });
    }
};
