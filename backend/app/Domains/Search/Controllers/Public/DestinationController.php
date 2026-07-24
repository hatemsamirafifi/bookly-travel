<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetScopedToursAction;
use App\Domains\Search\Support\DestinationAggregator;
use App\Http\Requests\Public\Search\DestinationToursRequest;
use App\Http\Requests\Public\Search\LocaleRequest;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

class DestinationController
{
    public function __construct(
        protected GetScopedToursAction $getScopedToursAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $destinations = DestinationAggregator::popular(20);

        return response()->json(['data' => $destinations]);
    }

    public function tours(DestinationToursRequest $request, string $slug): JsonResponse
    {
        $exists = Tour::published()->where('location_slug', $slug)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Destination not found.'], 404);
        }

        $result = $this->getScopedToursAction->execute('location', $slug, $request->validated());

        return response()->json($result);
    }
}
