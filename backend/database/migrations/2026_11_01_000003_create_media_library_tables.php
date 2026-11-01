<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('media_folders')
                ->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('path', 512)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('storage_key', 512)->unique();
            $table->string('original_filename', 255);
            $table->string('filename', 255);
            $table->string('extension', 16);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum_sha256', 64)->index();
            $table->jsonb('variants')->default('{}');
            $table->jsonb('alt_text')->default('{}');
            $table->jsonb('title')->default('{}');
            $table->jsonb('caption')->default('{}');
            $table->jsonb('focal_point')->nullable();
            $table->string('processing_status', 24)->default('pending');
            $table->string('visibility', 16)->default('public');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('media_asset_tag', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->foreignId('media_tag_id')->constrained('media_tags')->cascadeOnDelete();

            $table->primary(['media_asset_id', 'media_tag_id']);
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('usable_type', 120);
            $table->unsignedBigInteger('usable_id');
            $table->string('field_name', 80);
            $table->timestamps();

            $table->unique(['media_asset_id', 'usable_type', 'usable_id', 'field_name']);
            $table->index(['usable_type', 'usable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_asset_tag');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};
