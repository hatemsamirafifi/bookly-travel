<?php

declare(strict_types=1);

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Services\BlogCache;
use App\Domains\Blog\Transformers\BlogPostTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class ListBlogPostsAction
{
    public function __construct(
        private readonly BlogPostTransformer $transformer,
    ) {}

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int
     *     }
     * }
     */
    public function execute(Request $request, string $locale = 'en'): array
    {
        $categorySlug = $request->query('category');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 12);

        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        $cacheHash = md5(serialize([
            'category' => $categorySlug,
            'page' => $page,
            'per_page' => $perPage,
        ]));
        $cacheKey = "bookly:blog:list:{$locale}:{$cacheHash}";

        return BlogCache::remember(['blog', 'blog_list'], $cacheKey, 300, function () use ($categorySlug, $perPage, $page, $locale) {
            $query = BlogPost::query()
                ->published()
                ->with(['author', 'authorProfile.user', 'category'])
                ->orderByDesc('published_at');

            if ($categorySlug !== null && is_string($categorySlug) && $categorySlug !== '') {
                $category = BlogCategory::query()->where('slug', $categorySlug)->where('is_active', true)->first();
                if ($category !== null) {
                    $query->where('blog_category_id', $category->id);
                } else {
                    // Non-existent category filter produces empty result
                    $query->whereRaw('1 = 0');
                }
            }

            /** @var LengthAwarePaginator<int, BlogPost> $paginator */
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $transformedItems = [];
            foreach ($paginator->items() as $post) {
                $transformedItems[] = $this->transformer->transform($post, $locale);
            }

            return [
                'data' => $transformedItems,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        });
    }
}
