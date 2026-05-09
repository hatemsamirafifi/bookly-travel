<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('users');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('slug')->unique();
            $table->string('location');
            $table->string('location_slug')->nullable();
            $table->integer('duration_minutes');
            $table->string('duration_label');
            $table->integer('group_size_min')->default(1);
            $table->integer('group_size_max')->default(10);
            $table->integer('price_amount');
            $table->string('status')->default('draft');
            $table->string('cover_image_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['status', 'is_featured']);
            $table->index(['partner_id']);
            $table->index(['category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
