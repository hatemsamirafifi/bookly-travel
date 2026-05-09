<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetDestinationToursAction;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController
{
    public function __construct(
        protected GetDestinationToursAction $getDestinationToursAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['locale' => 'required|in:en,es,it']);

        $destinations = Tour::where('status', 'published')
            ->select('location_slug as slug', 'location as name')
            ->selectRaw('COUNT(*) as tour_count')
            ->groupBy('location_slug', 'location')
            ->orderByDesc('tour_count')
            ->limit(20)
            ->get()
            ->map(fn ($d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'country' => trim((string) str($d->name)->afterLast(',')),
                'image_url' => null,
                'tour_count' => (int) $d->tour_count,
                'is_featured' => true,
            ]);

        return response()->json(['data' => $destinations]);
    }

    public function tours(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,es,it',
            'q' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'price_min' => 'nullable|integer|min:0',
            'price_max' => 'nullable|integer|min:0',
            'duration' => 'nullable|in:half-day,full-day,multi-day',
            'date' => 'nullable|date_format:Y-m-d',
            'sort' => 'nullable|in:relevance,price_asc,price_desc,rating,newest',
            'page' => 'nullable|integer|min:1',
        ]);

        $exists = Tour::where('status', 'published')->where('location_slug', $slug)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Destination not found.'], 404);
        }

        $result = $this->getDestinationToursAction->execute($slug, $validated);

        return response()->json($result);
    }
}
