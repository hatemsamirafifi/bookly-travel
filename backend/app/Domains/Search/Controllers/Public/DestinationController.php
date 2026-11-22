<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Domains\Search\Services\TourPublicEligibilityService;
use App\Domains\Search\Support\DestinationAggregator;
use App\Http\Requests\Public\Search\DestinationToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

class DestinationController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction,
        protected TourPublicCacheService $cache,
        protected TourPublicEligibilityService $eligibility,
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $key = 'destinations.index';

        $destinations = $this->cache->rememberCatalog(
            $key,
            1800,
            fn () => DestinationAggregator::popular(20),
            fn ($payload) => $payload
        );

        return response()->json(['data' => $destinations]);
    }

    public function tours(DestinationToursRequest $request, string $slug): JsonResponse
    {
        // A destination only exists publicly while at least one published tour
        // carries its location slug and has an approved and active public owner.
        // Without this check an unknown or all-ineligible destination slug renders
        // a soft-404 empty listing (HTTP 200) and the localized 404 contract is
        // unreachable. Eligible-owner temporarily unbookable tours keep destination alive.
        $destinationExists = Tour::query()
            ->where('location_slug', $slug)
            ->where('status', 'published')
            ->withEligiblePublicOwner()
            ->exists();

        if (! $destinationExists) {
            return response()->json(['message' => 'Destination not found.'], 404);
        }

        $key = 'destinations.tours.' . $slug . '.' . md5(json_encode($request->validated()));

        $result = $this->cache->rememberCatalog(
            $key,
            600,
            fn () => $this->getScopedToursAction->execute('location', $slug, $request->validated()),
            fn (array $payload) => $this->eligibility->guardTourList($payload)
        );

        return response()->json($result);
    }
}
