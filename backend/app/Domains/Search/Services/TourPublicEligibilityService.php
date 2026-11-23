<?php

namespace App\Domains\Search\Services;

use App\Domains\Partner\Models\Partner;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Fresh authorization probes; no dependency on cache contents or invalidation. */
class TourPublicEligibilityService
{
    public function assertEligibleOwner(Tour $tour): void
    {
        if (! Partner::query()->whereKey($tour->partner_id)->publiclyEligible()->exists()) {
            throw new HttpResponseException(response()->json(['message' => 'Tour not found.'], 404));
        }
    }

    public function hasIneligiblePublishedOwners(): bool
    {
        return Tour::query()->published()
            ->whereDoesntHave('partnerRecord', function (Builder $owner): void {
                /** @var Builder<Partner> $owner */
                $owner->publiclyEligible();
            })
            ->exists();
    }

    /**
     * Batch-check actual cached IDs, preserving selected order and DTO values.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function filterCards(array $cards): array
    {
        $ids = [];
        foreach ($cards as $card) {
            if (isset($card['id'])) {
                $ids[] = (int) $card['id'];
            }
        }
        if ($ids === []) {
            return [];
        }

        $eligible = Tour::query()->whereIn('id', array_unique($ids))->published()
            ->withEligiblePublicOwner()->pluck('id')->flip()->all();
        $filtered = [];
        foreach ($cards as $card) {
            if (isset($card['id'], $eligible[(int) $card['id']])) {
                $filtered[] = $card;
            }
        }

        return $filtered;
    }

    /**
     * The real detail builder returns the public data envelope.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function guardDetail(array $payload): array
    {
        if (isset($payload['data']['related_tours']) && is_array($payload['data']['related_tours'])) {
            $payload['data']['related_tours'] = $this->filterCards($payload['data']['related_tours']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function guardTourList(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = $this->filterCards($payload['data']);
        }

        return $payload;
    }

    /**
     * Recheck all homepage card collections with one query, retaining each
     * original DTO and rail order and omitting rails that become empty.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function guardHomepage(array $payload): array
    {
        $featured = $payload['data']['featured_tours'] ?? [];
        $rails = $payload['data']['curated_rails'] ?? [];
        $all = $featured;
        foreach ($rails as $rail) {
            $all = array_merge($all, $rail['tours']);
        }
        $eligible = array_fill_keys(array_column($this->filterCards($all), 'id'), true);
        $filter = static fn (array $cards): array => array_values(array_filter(
            $cards, static fn (array $card): bool => isset($eligible[$card['id']]),
        ));
        $payload['data']['featured_tours'] = $filter($featured);
        $keptRails = [];
        foreach ($rails as $rail) {
            $rail['tours'] = $filter($rail['tours']);
            if ($rail['tours'] !== []) {
                $keptRails[] = $rail;
            }
        }
        $payload['data']['curated_rails'] = $keptRails;

        return $payload;
    }

    /**
     * Fresh guard on cached structured sitemap parts.
     * Rechecks actual Tour IDs and destination slugs against current
     * published status and eligible public owner before returning the parts.
     *
     * @param  array{
     *     prefix?: string,
     *     tours?: array<int, string>,
     *     static_after?: string,
     *     destinations?: array<string, string>,
     *     suffix?: string,
     * }  $parts
     * @return array{
     *     prefix?: string,
     *     tours?: array<int, string>,
     *     static_after?: string,
     *     destinations?: array<string, string>,
     *     suffix?: string,
     * }
     */
    public function guardSitemapParts(array $parts): array
    {
        $cachedTours = $parts['tours'] ?? [];
        if ($cachedTours !== []) {
            $cachedTourIds = array_keys($cachedTours);
            $eligibleTourIds = [];
            foreach (array_chunk($cachedTourIds, 1000) as $chunk) {
                $eligibleChunk = Tour::query()
                    ->whereIn('id', $chunk)
                    ->published()
                    ->withEligiblePublicOwner()
                    ->pluck('id')
                    ->all();
                foreach ($eligibleChunk as $id) {
                    $eligibleTourIds[(int) $id] = true;
                }
            }

            $guardedTours = [];
            foreach ($cachedTours as $tourId => $fragment) {
                if (isset($eligibleTourIds[(int) $tourId])) {
                    $guardedTours[$tourId] = $fragment;
                }
            }
            $parts['tours'] = $guardedTours;
        }

        $cachedDestinations = $parts['destinations'] ?? [];
        if ($cachedDestinations !== []) {
            $cachedSlugs = array_keys($cachedDestinations);
            $eligibleSlugs = [];
            foreach (array_chunk($cachedSlugs, 1000) as $chunk) {
                $slugsWithEligibleTours = Tour::query()
                    ->whereIn('location_slug', $chunk)
                    ->published()
                    ->withEligiblePublicOwner()
                    ->distinct()
                    ->pluck('location_slug')
                    ->all();
                foreach ($slugsWithEligibleTours as $slug) {
                    $eligibleSlugs[(string) $slug] = true;
                }
            }

            $guardedDestinations = [];
            foreach ($cachedDestinations as $slug => $fragment) {
                if (isset($eligibleSlugs[(string) $slug])) {
                    $guardedDestinations[$slug] = $fragment;
                }
            }
            $parts['destinations'] = $guardedDestinations;
        }

        return $parts;
    }
}
