<?php

namespace App\Domains\Blog\Jobs;

use App\Domains\Blog\Models\BlogPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledBlogPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $postId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $post = BlogPost::find($this->postId);

        if (! $post) {
            return;
        }

        // Re-validate post scheduling state (still published + scheduled_at <= now)
        if ($post->status === 'published' && $post->scheduled_at && $post->scheduled_at->isPast()) {
            InvalidateBlogCacheJob::dispatch();
            RegenerateSitemapJob::dispatch();
        }
    }
}
