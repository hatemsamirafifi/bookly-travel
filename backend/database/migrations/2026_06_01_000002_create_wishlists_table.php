<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'tour_id'], 'wishlists_user_tour_unique');
            $table->index('user_id', 'wishlists_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
