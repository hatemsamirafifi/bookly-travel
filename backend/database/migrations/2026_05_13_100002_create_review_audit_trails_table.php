<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews');
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 20);
            $table->unsignedTinyInteger('old_rating')->nullable();
            $table->unsignedTinyInteger('new_rating')->nullable();
            $table->text('old_comment')->nullable();
            $table->text('new_comment')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_audit_trails');
    }
};
