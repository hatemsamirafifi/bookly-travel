<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostDetailTransformer;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetBlogPostAction
{
    public function __construct(
        protected BlogPostDetailTransformer $transformer
    ) {}

    public function execute(string $slug, string $locale): array
    {
        $post = BlogPost::with([
            'category',
            'author.authorProfile',
            'relatedTours.translations',
            'relatedTours.category',
        ])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            throw new NotFoundHttpException('Blog post not found.');
        }

        // 404 vs 410 gating:
        // draft -> 404
        // published + scheduled_at > now() -> 404
        // archived + published_at IS NULL -> 404
        // archived + published_at IS NOT NULL -> 410
        if ($post->status === 'draft') {
            throw new NotFoundHttpException('Blog post not found.');
        }

        if ($post->status === 'published' && $post->scheduled_at && $post->scheduled_at->isFuture()) {
            throw new NotFoundHttpException('Blog post not found.');
        }

        if ($post->status === 'archived') {
            if ($post->published_at !== null) {
                throw new HttpException(410, 'This blog post is no longer available.');
            }
            throw new NotFoundHttpException('Blog post not found.');
        }

        if ($post->status !== 'published') {
            throw new NotFoundHttpException('Blog post not found.');
        }

        $relatedPosts = $this->resolveRelatedPosts($post);

        return [
            'data' => $this->transformer->transform($post, $locale, $relatedPosts),
        ];
    }

    protected function resolveRelatedPosts(BlogPost $post): Collection
    {
        $sameCategoryPosts = collect();
        if ($post->category_id) {
            $sameCategoryPosts = BlogPost::published()
                ->where('id', '!=', $post->id)
                ->where('category_id', $post->category_id)
                ->with(['category', 'author.authorProfile'])
                ->orderByDesc('published_at')
                ->take(3)
                ->get();
        }

        $needed = 3 - $sameCategoryPosts->count();
        if ($needed <= 0) {
            return $sameCategoryPosts;
        }

        $excludedIds = $sameCategoryPosts->pluck('id')->push($post->id)->all();

        $backfillPosts = BlogPost::published()
            ->whereNotIn('id', $excludedIds)
            ->with(['category', 'author.authorProfile'])
            ->orderByDesc('published_at')
            ->take($needed)
            ->get();

        return $sameCategoryPosts->concat($backfillPosts);
    }
}
