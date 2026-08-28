<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('status', 20)->default('draft');
            $table->jsonb('title');
            $table->jsonb('body');
            $table->jsonb('excerpt')->nullable();
            $table->jsonb('meta_description')->nullable();
            $table->string('cover_image_url', 255)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_featured']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'published_at']);
            $table->index('author_id');
            $table->index('blog_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
