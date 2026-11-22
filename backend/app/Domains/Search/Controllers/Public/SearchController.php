<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\SearchToursAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Domains\Search\Services\TourPublicEligibilityService;
use App\Http\Requests\Public\Search\SearchToursRequest;
use Illuminate\Http\JsonResponse;

class SearchController
{
    public function __construct(
        protected SearchToursAction $searchToursAction,
        protected TourPublicCacheService $cache,
        protected TourPublicEligibilityService $eligibility,
    ) {}

    public function search(SearchToursRequest $request): JsonResponse
    {
        $key = 'search.' . md5(json_encode($request->validated()));

        $results = $this->cache->rememberCatalog(
            $key,
            300,
            fn () => $this->searchToursAction->execute($request->validated()),
            fn (array $payload) => $this->eligibility->guardTourList($payload)
        );

        return response()->json($results);
    }
}
