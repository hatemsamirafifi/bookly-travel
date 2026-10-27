<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Http\Requests\Public\Search\CategoryToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $key = 'categories.index';

        /** @var \Illuminate\Support\Collection<int, array{slug: string, name: string, description: string|null, image_url: string|null, tour_count: int}> */
        $categories = Cache::remember($key, 1800, function (): \Illuminate\Support\Collection {
            return Category::popularWithCounts()
                ->get()
                ->map(fn (Category $cat) => [
                    'slug' => (string) $cat->slug,
                    'name' => (string) $cat->name,
                    'description' => $cat->description ? (string) $cat->description : null,
                    'image_url' => $cat->image_url ? (string) $cat->image_url : null,
                    'tour_count' => (int) $cat->getAttribute('tours_count'),
                ])
                ->values();
        });

        return response()->json(['data' => $categories]);
    }

    public function tours(CategoryToursRequest $request, string $slug): JsonResponse
    {
        $key = 'categories.tours.' . $slug . '.' . md5(json_encode($request->validated()));

        $result = Cache::remember($key, 600, function () use ($slug, $request) {
            return $this->getScopedToursAction->execute('category', $slug, $request->validated());
        });

        return response()->json($result);
    }
}
