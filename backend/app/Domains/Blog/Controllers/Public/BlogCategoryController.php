<?php

declare(strict_types=1);

namespace App\Domains\Blog\Controllers\Public;

use App\Domains\Blog\Actions\GetBlogCategoryAction;
use App\Domains\Blog\Requests\Public\ListBlogPostsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class BlogCategoryController extends Controller
{
    public function show(ListBlogPostsRequest $request, string $slug, GetBlogCategoryAction $action): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $result = $action->execute($slug, $request, is_string($locale) ? $locale : 'en');

        return response()->json($result);
    }
}
