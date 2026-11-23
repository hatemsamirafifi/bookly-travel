<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Domains\Search\Services\TourPublicEligibilityService;
use App\Http\Requests\Public\Search\CategoryToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction,
        protected TourPublicCacheService $cache,
        protected TourPublicEligibilityService $eligibility,
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $key = 'categories.index';

        /** @var array<int, array{slug: string, name: string, description: string|null, image_url: string|null, tour_count: int}> */
        $categories = $this->cache->rememberCatalog(
            $key,
            1800,
            function (): array {
                return Category::popularWithCounts()
                    ->get()
                    ->map(fn (Category $cat) => [
                        'slug' => (string) $cat->slug,
                        'name' => (string) $cat->name,
                        'description' => $cat->description === '' ? null : (string) $cat->description,
                        'image_url' => $this->formatImageUrl($cat->image_url),
                        'tour_count' => (int) $cat->getAttribute('tours_count'),
                    ])
                    ->values()
                    ->all();
            },
            fn ($payload) => $payload
        );

        return response()->json(['data' => $categories]);
    }

    public function tours(CategoryToursRequest $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->first();
        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $key = 'categories.tours.' . $slug . '.' . md5(json_encode($request->validated()));

        $result = $this->cache->rememberCatalog(
            $key,
            600,
            fn () => $this->getScopedToursAction->execute('category', $slug, $request->validated()),
            fn (array $payload) => $this->eligibility->guardTourList($payload)
        );

        return response()->json($result);
    }

    private function formatImageUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
            return $url;
        }

        return '/storage/' . ltrim($url, '/');
    }
}
