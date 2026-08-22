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
     * @param array<string>|null $locales
     */
    public function __construct(
        public readonly ?array $locales = ['en', 'es', 'it']
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Flush all blog list and category tags/keys
        // Redis tag-based or pattern-based clear
        try {
            Cache::tags(['blog', 'blog_list', 'blog_categories'])->flush();
        } catch (\Throwable) {
            // Fallback for cache stores without tagging support (like file/array)
            Cache::flush();
        }
    }
}
