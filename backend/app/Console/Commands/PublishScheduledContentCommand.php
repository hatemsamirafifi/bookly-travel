<?php

namespace App\Console\Commands;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Blog\Jobs\InvalidateBlogCacheJob;
use App\Domains\Blog\Jobs\RegenerateSitemapJob;
use App\Domains\Blog\Models\BlogPost;
use Illuminate\Console\Command;

/**
 * Scheduled content publisher (Admin 2.0 Phase G, T-G02).
 *
 * Publishes CMS content whose publication was scheduled via `scheduled_at`:
 * every minute it flips due scheduled static pages and first-time scheduled
 * blog posts to `published`, stamps `published_at`, records a governance
 * audit entry signed by the system actor, and invalidates dependent caches
 * (blog lists + sitemap). Re-running on an empty due-set is a no-op.
 */
class PublishScheduledContentCommand extends Command
{
    protected $signature = 'cms:publish-scheduled';

    protected $description = 'Publish due scheduled static pages and blog posts, then refresh caches';

    public function handle(GovernanceAuditService $audit): int
    {
        $now = now();

        $staticPages = StaticPage::query()
            ->where('status', StaticPage::STATUS_DRAFT)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($staticPages as $page) {
            $page->status = StaticPage::STATUS_PUBLISHED;
            $page->published_at = $page->published_at ?? $page->scheduled_at;
            $page->save();
        }

        $blogPosts = BlogPost::query()
            ->whereIn('status', [BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED])
            ->whereNull('published_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($blogPosts as $post) {
            $post->status = BlogPost::STATUS_PUBLISHED;
            $post->published_at = $post->scheduled_at;
            $post->save();
        }

        if ($staticPages->isNotEmpty() || $blogPosts->isNotEmpty()) {
            $audit->logSystem('cms.scheduled_publish', null, null, null, [
                'static_page_ids' => $staticPages->pluck('id')->all(),
                'blog_post_ids' => $blogPosts->pluck('id')->all(),
                'scheduled_at_cutoff' => $now->toIso8601String(),
            ]);

            InvalidateBlogCacheJob::dispatch();
            RegenerateSitemapJob::dispatch();
        }

        $published = $staticPages->count() + $blogPosts->count();
        $this->info("Scheduled content published: {$published}.");

        return self::SUCCESS;
    }
}
