<?php

declare(strict_types=1);

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetBlogCategoryAction
{
    public function __construct(
        private readonly BlogPostTransformer $transformer,
    ) {}

    /**
     * @return array{
     *     data: array{
     *         id: int,
     *         name: string,
     *         slug: string,
     *         description: ?string,
     *         posts: list<array<string, mixed>>
     *     },
     *     meta: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int
     *     }
     * }
     */
    public function execute(string $slug, Request $request, string $locale = 'en'): array
    {
        $category = BlogCategory::query()->where('slug', $slug)->first();

        if ($category === null) {
            throw new NotFoundHttpException('Blog category not found.');
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 12);
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        $cacheHash = md5(serialize([
            'page' => $page,
            'per_page' => $perPage,
        ]));
        $cacheKey = "bookly:blog:category:{$slug}:{$locale}:{$cacheHash}";

        return Cache::remember($cacheKey, 300, function () use ($category, $perPage, $page, $locale) {
            $query = BlogPost::query()
                ->published()
                ->with(['authorProfile.user', 'primaryCategory'])
                ->where(function ($q) use ($category) {
                    $q->where('blog_category_id', $category->id)
                        ->orWhereHas('categories', function ($catQuery) use ($category) {
                            $catQuery->where('blog_categories.id', $category->id);
                        });
                })
                ->orderByDesc('published_at');

            /** @var LengthAwarePaginator<BlogPost> $paginator */
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $transformedItems = [];
            foreach ($paginator->items() as $post) {
                $transformedItems[] = $this->transformer->transform($post, $locale);
            }

            return [
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'posts' => $transformedItems,
                ],
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
