<?php

namespace App\Domains\Blog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class InvalidateBlogCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<string>|null  $locales
     */
    public function __construct(
        public readonly ?array $locales = ['en', 'es', 'it']
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Attempt tag-based flush first (Redis/memcached)
        try {
            Cache::tags(['blog', 'blog_list', 'blog_categories'])->flush();

            return;
        } catch (\Throwable) {
            // Cache driver doesn't support tags — fall through to manual key deletion
        }

        // CR-009: Never call Cache::flush() — it wipes sessions, rate limits, etc.
        // Instead, forget known blog cache key patterns per locale.
        foreach ($this->locales as $locale) {
            // List cache keys are hashed by category+page+per_page, so we can't
            // enumerate them precisely. Use a prefix scan if available, otherwise
            // forget the unfiltered list key (page 1, default per_page) which is
            // the most common. A full solution requires a cache key registry.
            Cache::forget("bookly:blog:list:{$locale}:" . md5(serialize([
                'category' => null,
                'page' => 1,
                'per_page' => 12,
            ])));
        }

        // Sitemap cache is handled by RegenerateSitemapJob, but forget it here too
        // for immediate invalidation.
        Cache::forget('bookly:sitemap:xml');
    }
}
