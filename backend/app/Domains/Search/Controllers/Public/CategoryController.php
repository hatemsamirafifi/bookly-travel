<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Http\Requests\Public\Search\CategoryToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $categories = Category::popularWithCounts()
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'tour_count' => (int) $cat->tours_count,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function tours(CategoryToursRequest $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->first();
        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $result = $this->getScopedToursAction->execute('category', $slug, $request->validated());

        return response()->json($result);
    }
}
