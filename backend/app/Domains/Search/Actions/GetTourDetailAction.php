<?php

namespace App\Domains\Search\Actions;

use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;
use App\Models\TourOption;
use App\Models\TourTranslation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

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
            'translations', 'translationStates', 'category', 'media', 'options.translations',
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
        // The requested locale is served only when it is the authored source or
        // a derivative whose translated hash still matches the current source;
        // otherwise the actual source content is served with its truthful
        // language, independent of spoken guide languages and the UI locale.
        $sourceLocale = $tour->sourceLocale();
        $source = $tour->sourceTranslation();
        $translation = $this->translationService->currentTranslation($tour, $locale);
        $t = $translation ?? $source;
        $sourceItinerary = optional($source)->itinerary;
        $hasNonemptySourceItinerary = is_array($sourceItinerary) && count($sourceItinerary) > 0;
        // An explicit [] derivative stays empty; a missing/empty source never warns.
        $itineraryFallback = $locale !== $sourceLocale
            && $hasNonemptySourceItinerary
            && ($translation === null || optional($t)->itinerary === null);
        $contentLocale = optional($t)->locale ?? $locale;

        /** @var mixed $selectedItinerary */
        $selectedItinerary = optional($t)->itinerary ?? $sourceItinerary ?? [];
        if (! is_array($selectedItinerary)) {
            $selectedItinerary = [];
        }
        // A nonempty itinerary claims its actual language; an empty
        // itinerary retains the selected content locale without claiming
        // translated text exists.
        $itineraryLocale = count($selectedItinerary) > 0
            ? ($itineraryFallback ? $sourceLocale : $contentLocale)
            : $contentLocale;

        // Spec 019 US4 T052: allImageUrls stays authoritative for order,
        // dedup, legacy fallback and no-image []. Per-URL specific alt comes
        // from the eager-loaded media collection (no extra queries):
        // nonblank authored alt projects with the authored source locale,
        // otherwise the selected current title with the actual content locale
        // (not the requested locale when falling back to the source). No
        // placeholder or fabricated photo is introduced.
        $images = $tour->allImageUrls();
        $mediaRows = $tour->relationLoaded('media') ? $tour->media : collect();
        $specificAltByUrl = [];
        foreach ($mediaRows as $row) {
            $rowUrl = $row->getAttribute('url');
            if (! is_string($rowUrl) || $rowUrl === '') {
                continue;
            }
            $rowAlt = $row->getAttribute('alt_text');
            $isSpecific = is_string($rowAlt) && trim($rowAlt) !== '';
            if (! array_key_exists($rowUrl, $specificAltByUrl)) {
                $specificAltByUrl[$rowUrl] = $isSpecific ? $rowAlt : null;
            } elseif ($isSpecific && $specificAltByUrl[$rowUrl] === null) {
                $specificAltByUrl[$rowUrl] = $rowAlt;
            }
        }
        $selectedTitle = optional($t)->title ?? '';
        $imageObjects = [];
        foreach ($images as $i => $url) {
            $specific = $specificAltByUrl[$url] ?? null;
            if (is_string($specific) && trim($specific) !== '') {
                $imageObjects[] = [
                    'url' => $url,
                    'is_cover' => $i === 0,
                    'alt' => $specific,
                    'alt_locale' => $sourceLocale,
                ];
            } else {
                $imageObjects[] = [
                    'url' => $url,
                    'is_cover' => $i === 0,
                    'alt' => $selectedTitle,
                    'alt_locale' => $contentLocale,
                ];
            }
        }

        $availableDates = $tour->upcomingAvailableDates();

        // Real purchasable options of this tour. A tour that ever configured
        // options but has none active right now is accurately unavailable: no
        // option can be selected for any date, so the sellable date surface
        // collapses to empty while the (retired) option history stays
        // server-side only. Tours that never configured options keep the
        // exact legacy availability behavior.
        $activeOptions = $tour->activeOptions();
        $noSaleOptions = $tour->hasOptionsConfigured() && $activeOptions->isEmpty();
        $isUnavailable = $isUnavailable || $noSaleOptions;
        if ($noSaleOptions) {
            $availableDates = [];
        }

        // Bounded primary-review fix: derive the public aggregate from the
        // same actual visible+flagged review set as the distribution (one
        // reviewDistribution() query, no cached claims, no cache rewrites).
        // Empty = 0/0; visible 5 + flagged 3 = avg 4 / count 2; hidden is
        // excluded by reviewDistribution() itself.
        $reviewDistribution = $tour->reviewDistribution();
        $reviewCount = (int) array_sum($reviewDistribution);
        $reviewSum = 5 * (int) ($reviewDistribution['5'] ?? 0)
            + 4 * (int) ($reviewDistribution['4'] ?? 0)
            + 3 * (int) ($reviewDistribution['3'] ?? 0)
            + 2 * (int) ($reviewDistribution['2'] ?? 0)
            + 1 * (int) ($reviewDistribution['1'] ?? 0);
        $reviewAverage = $reviewCount > 0 ? round($reviewSum / $reviewCount, 2) : 0;

        $optionObjects = $activeOptions
            ->map(fn (TourOption $option): array => $this->buildOptionObject($tour, $option, $locale, $availableDates))
            ->all();

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
            // Both share the genuine visible+flagged facts derived above.
            'rating' => [
                'average' => $reviewAverage,
                'count' => $reviewCount,
            ],
            'reviews' => [
                'average_rating' => $reviewAverage,
                'count' => $reviewCount,
                'distribution' => $reviewDistribution,
            ],
            'operator' => $this->operatorSummary->execute($tour),
            'related_tours' => $this->relatedTours->execute($tour, $locale),
            'seo' => $this->buildSeoMetadata($tour, $t, $locale),
        ];

        // Real purchasable departures. Empty when the tour never configured
        // options (legacy shape, no `options` consumers) and when every
        // configured option is retired (no-sale state above).
        $data['options'] = $optionObjects;

        // `partial_translation` summarizes actual nonempty source-language
        // fallbacks only.
        $generalFallback = $locale !== $sourceLocale && ! $translation && $source && $this->hasNonemptyGeneralContent($source);
        if ($generalFallback || $itineraryFallback) {
            $data['translation_warning'] = 'partial_translation';
        }

        return ['data' => $data];
    }

    /**
     * Whether the authored source carries any nonempty general traveler
     * content worth disclosing as a fallback. Lists count when any entry
     * is a nonempty string.
     */
    protected function hasNonemptyGeneralContent(TourTranslation $source): bool
    {
        foreach (['title', 'description', 'meeting_point', 'cancellation_policy'] as $field) {
            $value = $source->{$field};
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        foreach (['highlights', 'inclusions', 'exclusions', 'important_information'] as $field) {
            $value = $source->{$field};
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
     * One purchasable option in the public detail contract.
     *
     * `pricing` and `availability` reuse the exact top-level shapes
     * (base_price{amount,currency,formatted} + tiered_pricing:null;
     * next_available_date/available_dates/is_unavailable), so the booking
     * form consumes them identically. `available_dates` intersects the
     * tour's operating dates with the option's own weekday allow-list, and
     * `next_available_date` stays strictly future (CreateBookingAction
     * rejects today). The option texts carry the locale that is actually
     * served — derivative locale when the derivative is current, else the
     * authored source — matching the tour-level fallback contract.
     */
    protected function buildOptionObject(Tour $tour, TourOption $option, string $locale, array $tourDates): array
    {
        $text = $this->translationService->localizedOptionText($tour, $option, $locale);

        $dates = array_values(array_filter(
            $tourDates,
            fn (string $date): bool => $option->coversWeekday(Carbon::parse($date))
        ));

        $priceAmount = (int) $option->price_amount;

        return [
            'id' => $option->id,
            'title' => $text['title'],
            'pickup_instructions' => $text['pickup_instructions'],
            'content_locale' => $text['content_locale'],
            'guide_languages' => $option->guideLanguages(),
            'start_time' => $option->startTimeString(),
            'pricing' => [
                'base_price' => [
                    'amount' => $priceAmount,
                    'currency' => $tour->currency(),
                    'formatted' => Tour::formatPrice($priceAmount, $tour->currency()),
                ],
                'tiered_pricing' => null,
            ],
            'availability' => [
                'next_available_date' => $this->firstFutureDate($dates),
                'available_dates' => $dates,
                'is_unavailable' => $dates === [],
            ],
            'group_size' => [
                'min' => $tour->group_size_min,
                'max' => $option->effectiveCapacity($tour),
            ],
        ];
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
