<?php

declare(strict_types=1);

namespace App\Domains\Blog\Controllers\Public;

use App\Domains\Blog\Actions\GetBlogPostAction;
use App\Domains\Blog\Actions\ListBlogPostsAction;
use App\Domains\Blog\Requests\Public\ListBlogPostsRequest;
use App\Domains\Blog\Requests\Public\ShowBlogPostRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class BlogPostController extends Controller
{
    public function index(ListBlogPostsRequest $request, ListBlogPostsAction $action): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $result = $action->execute($request, is_string($locale) ? $locale : 'en');

        return response()->json($result);
    }

    public function show(ShowBlogPostRequest $request, string $slug, GetBlogPostAction $action): JsonResponse
    {
        $locale = $request->query('locale');
        $result = $action->execute($slug, is_string($locale) ? $locale : 'en');

        return response()->json($result);
    }
}

