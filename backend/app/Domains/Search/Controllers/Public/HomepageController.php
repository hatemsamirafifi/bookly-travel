<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetHomepageDataAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Domains\Search\Services\TourPublicEligibilityService;
use App\Http\Requests\Public\Search\LocaleRequest;
use Illuminate\Http\JsonResponse;

class HomepageController
{
    public function __construct(
        protected GetHomepageDataAction $getHomepageDataAction,
        protected TourPublicCacheService $cache,
        protected TourPublicEligibilityService $eligibility,
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $locale = $request->validated()['locale'];
        $key = 'homepage.' . $locale;

        $data = $this->cache->rememberCatalog(
            $key,
            600,
            fn () => $this->getHomepageDataAction->execute($locale),
            fn (array $payload) => $this->eligibility->guardHomepage($payload),
            tags: ['homepage']
        );

        return response()->json($data);
    }
}
