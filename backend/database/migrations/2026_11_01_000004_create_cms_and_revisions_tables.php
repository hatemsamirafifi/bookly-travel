<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS platform content + revisions (Admin 2.0 Phase G, T-G01).
 *
 * Adds the append-only `content_revisions` version history, the blog tagging
 * system (`blog_tags` + `blog_post_tags`), and additive columns:
 * - `static_pages`: canonical_url, scheduled_at, lock_version (optimistic concurrency for CMS editors), meta_title (SEO), og_image_media_id
 * - `blog_posts`: canonical_url (never overwrite existing translations), lock_version
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedBigInteger('version_number');
            $table->jsonb('snapshot');
            $table->jsonb('diff')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['revisionable_type', 'revisionable_id', 'version_number'], 'content_revisions_unique_version');
            $table->index(['revisionable_type', 'revisionable_id']);
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->jsonb('name');
            $table->timestamps();
        });

        Schema::create('blog_post_tags', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->primary(['blog_post_id', 'blog_tag_id']);
        });

        Schema::table('static_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('static_pages', 'canonical_url')) {
                $table->string('canonical_url', 255)->nullable();
                $table->timestamp('scheduled_at')->nullable()->after('published_at');
                $table->unsignedBigInteger('lock_version')->default(0)->after('published_at');
                $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();

                $table->index(['status', 'scheduled_at']);
            }

            if (! Schema::hasColumn('static_pages', 'meta_title')) {
                $table->jsonb('meta_title')->nullable();
            }
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'canonical_url')) {
                $table->string('canonical_url', 255)->nullable();
                $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();

                if (! Schema::hasColumn('blog_posts', 'cover_media_id')) {
                    $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete()->after('cover_image_url');
                    $table->index('cover_media_id');
                }

                if (! Schema::hasColumn('blog_posts', 'lock_version')) {
                    $table->unsignedBigInteger('lock_version')->default(0)->after('published_at');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tags');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('content_revisions');

        Schema::table('blog_posts', function (Blueprint $table) {
            if (Schema::hasColumn('blog_posts', 'canonical_url')) {
                $table->dropColumn(['canonical_url', 'og_image_media_id', 'cover_media_id']);

                if (Schema::hasColumn('blog_posts', 'lock_version')) {
                    $table->dropColumn('lock_version');
                }
            }
        });

        Schema::table('static_pages', function (Blueprint $table) {
            if (Schema::hasColumn('static_pages', 'canonical_url')) {
                $table->dropColumn(['canonical_url', 'scheduled_at', 'lock_version', 'og_image_media_id']);
            }

            if (Schema::hasColumn('static_pages', 'meta_title')) {
                $table->dropColumn('meta_title');
            }
        });
    }
};
