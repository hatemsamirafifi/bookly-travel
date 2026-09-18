<?php

namespace App\Domains\Search\Actions;

use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetTourDetailAction
{
    public function execute(string $slug, string $locale): array
    {
        $tour = Tour::with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])
            ->where('slug', $slug)->first();

        if (! $tour) {
            throw new HttpResponseException(
                response()->json(['message' => 'Tour not found.'], 404)
            );
        }

        if ($tour->status !== 'published') {
            // Allowlist on `published` rather than a blocklist of statuses so a
            // future status (e.g. `paused`) can never be served publicly with
            // full content. Archived tours that were once published return 410
            // (see F10 / published_at refinement); everything else 404.
            $code = $tour->status === 'archived' && $tour->published_at !== null
                ? 410
                : 404;
            $message = $code === 410
                ? 'This tour is no longer available.'
                : 'Tour not found.';

            throw new HttpResponseException(
                response()->json(['message' => $message], $code)
            );
        }

        // The search index only holds tours that are published + valid pricing
        // + upcoming availability (`Tour::isPubliclyBookable`). A published tour
        // reached via direct URL may fail those (partner saved before setting
        // pricing, or let availability expire). Serve it per the contract
        // (tour-detail-api.md:115-116: 200 with "Currently Unavailable") but
        // flag it so the frontend hides the Book Now CTA instead of offering a
        // mispriced/unavailable booking.
        $isUnavailable = ! $tour->isPubliclyBookable();

        // Reuse the eager-loaded `translations` collection (no extra query).
        $translation = $tour->translations->firstWhere('locale', $locale);
        $fallback = $translation ? null : $tour->translations->firstWhere('locale', 'en');
        $t = $translation ?? $fallback;

        $images = $tour->allImageUrls();
        $imageObjects = [];
        foreach ($images as $i => $url) {
            $imageObjects[] = [
                'url' => $url,
                'is_cover' => $i === 0,
                'alt' => optional($t)->title ?? '',
            ];
        }

        $availableDates = $tour->upcomingAvailableDates();

        $data = [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => optional($t)->title ?? '',
            'description' => optional($t)->description ?? '',
            'highlights' => optional($t)->highlights ?? [],
            'inclusions' => optional($t)->inclusions ?? [],
            'exclusions' => optional($t)->exclusions ?? [],
            'location' => $tour->location,
            'meeting_point' => optional($t)->meeting_point ?? '',
            'category' => [
                'slug' => optional($tour->category)->slug ?? '',
                'name' => optional($tour->category)->name ?? '',
            ],
            'duration' => [
                'minutes' => $tour->duration_minutes,
                'label' => $tour->duration_label,
            ],
            'languages' => $tour->availableLanguages(),
            'group_size' => [
                'min' => $tour->group_size_min,
                'max' => $tour->group_size_max,
            ],
            'cancellation_policy' => optional($t)->cancellation_policy ?? '',
            'images' => $imageObjects,
            'pricing' => [
                'base_price' => [
                    'amount' => $tour->lowestPriceAmount(),
                    'currency' => $tour->currency(),
                    'formatted' => Tour::formatPrice($tour->lowestPriceAmount(), $tour->currency()),
                ],
                'tiered_pricing' => null,
            ],
            'availability' => [
                // Bookings require a strictly-future tour_date (CreateBookingAction
                // rejects today and past dates), so the date the UI uses to deep-link
                // into the booking form must also be strictly future. The index-level
                // approximation in upcomingAvailableDates() can still include today
                // for search visibility; filter it out of the bookable hint only.
                'next_available_date' => $this->firstFutureDate($availableDates),
                'available_dates' => $availableDates,
                'is_unavailable' => $isUnavailable,
            ],
            // `rating` mirrors the TourCard contract (TourDetail extends TourCard)
            // so the frontend can render the listing-style StarRating uniformly.
            'rating' => [
                'average' => $tour->averageRating(),
                'count' => $tour->reviewCount(),
            ],
            'reviews' => [
                'average_rating' => $tour->averageRating(),
                'count' => $tour->reviewCount(),
                'distribution' => $tour->reviewDistribution(),
            ],
            'seo' => $this->buildSeoMetadata($tour, $t, $locale),
        ];

        if (! $translation && $fallback) {
            $data['translation_warning'] = 'partial_translation';
        }

        return ['data' => $data];
    }

    /**
     * First available date strictly after today, or null. The booking form
     * deep-link date must satisfy CreateBookingAction's "future date" rule,
     * which rejects today's date.
     *
     * @param  array<int, string>  $dates
     */
    protected function firstFutureDate(array $dates): ?string
    {
        $tomorrow = now()->addDay()->toDateString();

        foreach ($dates as $date) {
            if ($date >= $tomorrow) {
                return $date;
            }
        }

        return null;
    }

    protected function buildSeoMetadata(Tour $tour, ?TourTranslation $translation, string $locale): array
    {
        $title = optional($translation)->title ?? '';
        $desc = optional($translation)->description ?? '';
        $baseUrl = config('app.url', 'https://bookly.com');
        $canonical = "{$baseUrl}/{$locale}/tours/{$tour->slug}";

        $hreflang = [];
        foreach (config('app.supported_locales', ['en', 'es', 'it']) as $lang) {
            $hreflang[$lang] = "{$baseUrl}/{$lang}/tours/{$tour->slug}";
        }

        $metaTitle = $title ? "{$title} | Bookly" : 'Tour Details | Bookly';
        $metaDesc = $desc ? substr($desc, 0, 160) : 'View tour details and book your next adventure with Bookly.';

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
            'canonical_url' => $canonical,
            'hreflang' => $hreflang,
        ];
    }
}
