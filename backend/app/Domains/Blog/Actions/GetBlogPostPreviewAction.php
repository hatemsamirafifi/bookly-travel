<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Services\PreviewTokenService;
use App\Domains\Blog\Transformers\BlogPostDetailTransformer;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GetBlogPostPreviewAction
{
    public function __construct(
        private readonly PreviewTokenService $tokenService,
        private readonly BlogPostDetailTransformer $detailTransformer
    ) {}

    /**
     * Verify HMAC token and return article preview data.
     *
     * @return array<string, mixed>
     */
    public function execute(string $slug, string $token, string $locale = 'en'): array
    {
        $validLocales = ['en', 'es', 'it'];
        if (! in_array($locale, $validLocales, true)) {
            throw new UnprocessableEntityHttpException("Invalid locale '{$locale}'. Supported: en, es, it.");
        }

        // Verify the preview token (HMAC + slug binding + expiry)
        $this->tokenService->verify($slug, $token);

        // Fetch post regardless of status
        $post = BlogPost::with([
            'category',
            'author.authorProfile',
            'relatedTours.translations',
            'relatedTours.category',
        ])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            throw new NotFoundHttpException("Blog post '{$slug}' not found.");
        }

        // Use the detail transformer (same as public detail endpoint)
        $relatedPosts = $this->resolveRelatedPosts($post);

        $data = $this->detailTransformer->transform($post, $locale, $relatedPosts);
        $data['is_preview'] = true;
        $data['status'] = $post->status;

        return $data;
    }

    /**
     * Resolve related posts for the preview (mirrors GetBlogPostAction logic).
     */
    protected function resolveRelatedPosts(BlogPost $post): Collection
    {
        $sameCategoryPosts = collect();
        if ($post->blog_category_id) {
            $sameCategoryPosts = BlogPost::published()
                ->where('id', '!=', $post->id)
                ->where('blog_category_id', $post->blog_category_id)
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
