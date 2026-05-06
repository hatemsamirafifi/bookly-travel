<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetCategoryToursAction;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    public function __construct(
        protected GetCategoryToursAction $getCategoryToursAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'tour_count' => $cat->publishedTourCount(),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function tours(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,es,it',
            'q' => 'nullable|string|max:255',
            'price_min' => 'nullable|integer|min:0',
            'price_max' => 'nullable|integer|min:0',
            'duration' => 'nullable|in:half-day,full-day,multi-day',
            'date' => 'nullable|date|date_format:Y-m-d',
            'sort' => 'nullable|in:relevance,price_asc,price_desc,rating,newest',
            'page' => 'nullable|integer|min:1',
        ]);

        $category = Category::where('slug', $slug)->where('is_active', true)->first();
        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $result = $this->getCategoryToursAction->execute($slug, $validated);

        return response()->json($result);
    }
}
