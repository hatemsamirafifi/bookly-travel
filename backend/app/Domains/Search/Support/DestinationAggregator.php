<?php

namespace App\Domains\Search\Support;

use App\Models\Tour;
use Illuminate\Support\Str;

/**
 * Aggregates destination "cards" (location_slug + tour_count) from the
 * bookable tour catalog, shared by the homepage featured-destinations list
 * and the destinations index endpoint so the two cannot drift.
 *
 * The `country` is parsed from the `location` string's trailing comma
 * segment only when a comma is present; a bare city ("Rome") yields null
 * rather than echoing the city as its own country.
 */
class DestinationAggregator
{
    /**
     * Return up to `$limit` destinations ordered by descending tour count.
     */
    public static function popular(int $limit): array
    {
        return Tour::bookable()
            ->select('location_slug as slug', 'location as name')
            ->selectRaw('COUNT(*) as tour_count')
            ->groupBy('location_slug', 'location')
            ->orderByDesc('tour_count')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'country' => self::extractCountry($d->name),
                'image_url' => null,
                'tour_count' => (int) $d->tour_count,
                'is_featured' => true,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Extract the country from a "City, Country" location string. Returns
     * null when the location has no comma (a bare city), so the city is never
     * shown as its own country.
     */
    public static function extractCountry(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $str = Str::of($location);

        return $str->contains(',') ? trim((string) $str->afterLast(',')) : null;
    }
}