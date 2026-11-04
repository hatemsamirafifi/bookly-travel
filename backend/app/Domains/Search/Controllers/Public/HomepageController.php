<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetHomepageDataAction;
use App\Http\Requests\Public\Search\LocaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomepageController
{
    public function __construct(
        protected GetHomepageDataAction $getHomepageDataAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $locale = $request->validated()['locale'];
        $key = 'homepage.' . $locale;

        // Attempt a tag-based cache first (Redis/memcached/array) so Tour or
        // Category edits can flush the whole 'homepage' tag. Non-taggable
        // stores throw BadMethodCallException — mirror the defensive style of
        // InvalidateBlogCacheJob and fall back to the plain remember call.
        try {
            $data = Cache::tags(['homepage'])->remember($key, 600, function () use ($locale) {
                return $this->getHomepageDataAction->execute($locale);
            });

            return response()->json($data);
        } catch (\Throwable) {
            // Store doesn't support tags — fall through to plain remembering.
        }

        $data = Cache::remember($key, 600, function () use ($locale) {
            return $this->getHomepageDataAction->execute($locale);
        });

        return response()->json($data);
    }
}
