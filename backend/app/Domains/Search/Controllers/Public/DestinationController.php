<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Domains\Search\Support\DestinationAggregator;
use App\Http\Requests\Public\Search\DestinationToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DestinationController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $key = 'destinations.index';

        $destinations = Cache::remember($key, 1800, function () {
            return DestinationAggregator::popular(20);
        });

        return response()->json(['data' => $destinations]);
    }

    public function tours(DestinationToursRequest $request, string $slug): JsonResponse
    {
        // A destination only exists publicly while at least one published
        // tour carries its location slug (the same entity-style gate
        // CategoryController applies to inactive categories). Without this
        // check an unknown slug renders a soft-404 empty listing (HTTP 200)
        // and the localized 404 contract for public pages is unreachable.
        $destinationExists = Tour::query()
            ->where('location_slug', $slug)
            ->where('status', 'published')
            ->exists();

        if (! $destinationExists) {
            return response()->json(['message' => 'Destination not found.'], 404);
        }

        $key = 'destinations.tours.' . $slug . '.' . md5(json_encode($request->validated()));

        $result = Cache::remember($key, 600, function () use ($slug, $request) {
            return $this->getScopedToursAction->execute('location', $slug, $request->validated());
        });

        return response()->json($result);
    }
}
