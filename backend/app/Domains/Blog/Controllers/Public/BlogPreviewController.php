<?php

namespace App\Domains\Blog\Controllers\Public;

use App\Domains\Blog\Actions\GetBlogPostPreviewAction;
use App\Domains\Blog\Requests\Public\ShowBlogPreviewRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BlogPreviewController extends Controller
{
    public function show(
        string $slug,
        ShowBlogPreviewRequest $request,
        GetBlogPostPreviewAction $action
    ): JsonResponse {
        $token = (string) $request->query('token', '');
        $locale = (string) $request->query('locale', 'en');

        if (empty($token)) {
            return response()->json([
                'message' => 'Preview token is required.',
            ], 403);
        }

        $previewData = $action->execute($slug, $token, $locale);

        return response()->json([
            'data' => $previewData,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
