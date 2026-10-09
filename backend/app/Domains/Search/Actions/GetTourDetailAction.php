<?php

namespace App\Domains\Search\Actions;

use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetTourDetailAction
{
    public function __construct(
        private readonly TourTranslationService $translationService,
        private readonly GetPublicOperatorSummaryAction $operatorSummary,
        private readonly GetRelatedToursAction $relatedTours,
    ) {}

    public function execute(string $slug, string $locale): array
    {
        $tour = Tour::with([
            'translations', 'translationStates', 'category', 'media',
            'partnerRecord' => fn ($query) => $query->select(['id', 'onboarding_status', 'is_active']),
            'partnerRecord.profile' => fn ($query) => $query->select(['id', 'partner_id', 'company_name', 'business_description', 'logo_url']),
            'availabilityRules', 'availabilityExceptions',
        ])
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
        $english = $tour->translations->firstWhere('locale', 'en');
        $translation = $this->translationService->currentTranslation($tour, $locale);
        $t = $translation ?? $english;
        $englishItinerary = optional($english)->itinerary;
        $hasNonemptyEnglishItinerary = is_array($englishItinerary) && count($englishItinerary) > 0;
        // An explicit [] derivative stays empty; missing/empty English never warns.
        $itineraryFallback = $locale !== 'en'
            && $hasNonemptyEnglishItinerary
            && ($translation === null || optional($t)->itinerary === null);
        $contentLocale = optional($t)->locale ?? $locale;

        /** @var mixed $selectedItinerary */
        $selectedItinerary = optional($t)->itinerary ?? $englishItinerary ?? [];
        if (! is_array($selectedItinerary)) {
            $selectedItinerary = [];
        }
        // A nonempty itinerary claims its actual language; an empty
        // itinerary retains the selected content locale without claiming
        // translated text exists.
        $itineraryLocale = count($selectedItinerary) > 0
            ? ($itineraryFallback ? 'en' : $contentLocale)
            : $contentLocale;

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
            'content_locale' => $contentLocale,
            'description' => optional($t)->description ?? '',
            'translation_status' => $this->translationService->publicStatus($tour, $locale),
            'highlights' => optional($t)->highlights ?? [],
            'inclusions' => optional($t)->inclusions ?? [],
            'exclusions' => optional($t)->exclusions ?? [],
            'important_information' => optional($t)->important_information ?? [],
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
            'difficulty_level' => $tour->difficulty_level,
            // Keep the existing `languages` field for consumers of the public
            // contract, but make its meaning explicit in the new field.
            'languages' => $tour->guideLanguages(),
            'guide_languages' => $tour->guideLanguages(),
            'itinerary' => $selectedItinerary,
            'itinerary_locale' => $itineraryLocale,
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
            'operator' => $this->operatorSummary->execute($tour),
            'related_tours' => $this->relatedTours->execute($tour, $locale),
            'seo' => $this->buildSeoMetadata($tour, $t, $locale),
        ];

        // `partial_translation` summarizes actual nonempty English fallbacks only.
        $generalFallback = $locale !== 'en' && ! $translation && $english && $this->hasNonemptyGeneralContent($english);
        if ($generalFallback || $itineraryFallback) {
            $data['translation_warning'] = 'partial_translation';
        }

        return ['data' => $data];
    }

    /**
     * Whether the English source carries any nonempty general traveler
     * content worth disclosing as a fallback. Lists count when any entry
     * is a nonempty string.
     */
    protected function hasNonemptyGeneralContent(TourTranslation $english): bool
    {
        foreach (['title', 'description', 'meeting_point', 'cancellation_policy'] as $field) {
            $value = $english->{$field};
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        foreach (['highlights', 'inclusions', 'exclusions', 'important_information'] as $field) {
            $value = $english->{$field};
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry) && trim($entry) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
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
