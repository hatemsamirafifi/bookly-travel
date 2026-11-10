<?php

namespace App\Models;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Reviews\Models\Review;
use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Throwable;

class Tour extends Model
{
    /**
     * Content locales the platform can store and serve. Exactly one of them
     * is the authored source (`tours.source_locale`); the others are generated
     * derivatives of that source.
     */
    public const CONTENT_LOCALES = ['en', 'es', 'it'];

    public const DEFAULT_SOURCE_LOCALE = 'en';

    use Searchable {
        queueMakeSearchable as private traitQueueMakeSearchable;
        queueRemoveFromSearch as private traitQueueRemoveFromSearch;
    }

    /**
     * Contain async Scout queue dispatch loss so it can never fail the
     * accepted source commit (lost dispatch is recoverable via queued
     * refresh). The actual dispatch is still attempted; only its push
     * failure is contained. Sync-engine failures still propagate for
     * worker retry.
     */
    public function queueMakeSearchable($models)
    {
        if (! config('scout.queue')) {
            $this->traitQueueMakeSearchable($models);

            return;
        }

        try {
            $this->traitQueueMakeSearchable($models);
        } catch (Throwable) {
            Log::warning('tour scout queue dispatch contained');
        }
    }

    /**
     * Contain async Scout queue dispatch loss as above; sync-engine
     * failures still propagate for worker retry.
     */
    public function queueRemoveFromSearch($models)
    {
        if (! config('scout.queue')) {
            $this->traitQueueRemoveFromSearch($models);

            return;
        }

        try {
            $this->traitQueueRemoveFromSearch($models);
        } catch (Throwable) {
            Log::warning('tour scout queue dispatch contained');
        }
    }

    public static function clearDetailCache(?string $slug): void
    {
        if (! $slug) {
            return;
        }

        foreach (['en', 'es', 'it'] as $locale) {
            Cache::forget("tour.{$slug}.{$locale}");
        }
    }

    protected static function booted(): void
    {
        static::saved(function (Tour $tour) {
            static::clearDetailCache($tour->slug);
            if ($tour->wasChanged('slug') && $tour->getOriginal('slug')) {
                static::clearDetailCache((string) $tour->getOriginal('slug'));
            }
        });

        static::deleted(function (Tour $tour) {
            static::clearDetailCache($tour->slug);
        });
    }

    // Meilisearch index settings for Scout sync-index-settings
    protected array $meilisearchSettings = [
        'filterableAttributes' => [
            'status',
            'category_slug',
            'location_slug',
            'price_amount',
            'duration_minutes',
            'available_dates',
        ],
        'sortableAttributes' => [
            'price_amount',
            'average_rating',
            'created_at',
        ],
    ];

    protected $fillable = [
        'partner_id',
        'category_id',
        'slug',
        'source_locale',
        'location',
        'location_slug',
        'duration_minutes',
        'duration_label',
        'group_size_min',
        'group_size_max',
        'price_amount',
        'status',
        'cover_image_url',
        'difficulty_level',
        'guide_languages',
        'is_featured',
        'published_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'group_size_min' => 'integer',
            'group_size_max' => 'integer',
            'duration_minutes' => 'integer',
            'price_amount' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'guide_languages' => 'array',
        ];
    }

    public function toSearchableArray(): array
    {
        $translations = $this->translations->keyBy('locale');
        $source = $translations->get($this->sourceLocale());
        $resolver = app(TourTranslationService::class);

        // One projection slot per platform content locale. The authored source
        // always fills its own slot; a genuinely current derivative fills its
        // slot, otherwise the source is served as the truthful fallback so no
        // locale slot is ever silently empty while real content exists.
        $projected = [];
        foreach (self::CONTENT_LOCALES as $contentLocale) {
            $projected[$contentLocale] = $resolver->currentTranslation($this, $contentLocale) ?? $source;
        }
        $en = $projected['en'] ?? null;
        $es = $projected['es'] ?? null;
        $it = $projected['it'] ?? null;

        return [
            'id' => $this->id,
            'title_en' => optional($en)->title ?? '',
            'title_es' => optional($es)->title ?? '',
            'title_it' => optional($it)->title ?? '',
            'description_en' => strip_tags(optional($en)->description ?? ''),
            'description_es' => strip_tags(optional($es)->description ?? ''),
            'description_it' => strip_tags(optional($it)->description ?? ''),
            'highlights_en' => $en ? json_encode($en->highlights ?? []) : '[]',
            'highlights_es' => $es ? json_encode($es->highlights ?? []) : '[]',
            'highlights_it' => $it ? json_encode($it->highlights ?? []) : '[]',
            'slug' => $this->slug,
            'location' => $this->location,
            'location_slug' => $this->location_slug,
            'category_name' => optional($this->category)->name ?? '',
            'category_slug' => optional($this->category)->slug ?? '',
            'price_amount' => $this->lowestPriceAmount(),
            'price_currency' => $this->currency(),
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->duration_label,
            'average_rating' => $this->averageRating(),
            'review_count' => $this->reviewCount(),
            'cover_image_url' => $this->allImageUrls()[0] ?? null,
            'image_urls' => $this->allImageUrls(),
            'group_size_min' => $this->group_size_min,
            'group_size_max' => $this->group_size_max,
            'available_dates' => $this->upcomingAvailableDates(),
            'languages' => $this->guideLanguages(),
            'guide_languages' => $this->guideLanguages(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->isPubliclyBookable();
    }

    /**
     * A tour is publicly bookable when it is published, has valid pricing
     * (lowestPriceAmount > 0), and at least one upcoming available date.
     *
     * This is the single invariant shared by the search index
     * (`shouldBeSearchable`), the facet aggregates, and the tour-detail
     * availability gate — keeping them aligned so a tour that is excluded
     * from search can never be served as bookable via a direct URL (FR-036).
     */
    public function isPubliclyBookable(): bool
    {
        return $this->status === 'published'
            && $this->hasValidPricing()
            && $this->hasUpcomingAvailability();
    }

    /**
     * Query scope for published tours. Use this instead of repeated
     * `where('status', 'published')` so the read-side filter has one home.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Query scope narrowing to tours that satisfy the same bookable invariant
     * the search index uses, expressed at the query level. The availability
     * component is approximated with `whereHas('availabilityRules')` (a tour
     * with no rules has no upcoming dates), which matches the search-index
     * behavior closely enough for facet counts and homepage listings.
     *
     * A tour that ever configured options is bookable only while at least one
     * option is active: with no option to select (all retired / no sale) the
     * tour is accurately unavailable even though its availability rules still
     * define dates. Tours that never configured options keep legacy behavior.
     */
    public function scopeBookable($query)
    {
        return $query
            ->published()
            ->whereHas('availabilityRules')
            ->where(function ($query) {
                $query->where(fn ($legacy) => $legacy->whereDoesntHave('options')->where('tours.price_amount', '>', 0))
                    ->orWhereHas('options', fn ($options) => $options->active()->where('price_amount', '>', 0));
            });
    }

    public function hasValidPricing(): bool
    {
        return $this->lowestPriceAmount() > 0;
    }

    /**
     * Whether any option was ever configured for this tour (active or
     * retired). Tours that were never configured keep legacy behavior on
     * every read surface; once options exist, the booking path requires an
     * explicit option selection and the public availability must reflect the
     * sellable option set instead of the bare tour schedule.
     */
    public function hasOptionsConfigured(): bool
    {
        if ($this->relationLoaded('options')) {
            return $this->options->isNotEmpty();
        }

        return $this->options()->exists();
    }

    public function supportsFieldTranslation(): bool
    {
        return $this->sourceLocale() === self::DEFAULT_SOURCE_LOCALE && ! $this->hasOptionsConfigured();
    }

    /**
     * The active (selectable) options in authored display order. Reads the
     * eager-loaded relation when present; otherwise one indexed query.
     */
    public function activeOptions(): Collection
    {
        $options = $this->relationLoaded('options')
            ? $this->options
            : $this->options()->get();

        return $options
            ->filter(fn (TourOption $option) => $option->isActive())
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
    }

    public function hasActiveOptions(): bool
    {
        if ($this->relationLoaded('options')) {
            return $this->options->contains(fn (TourOption $option) => $option->isActive());
        }

        return $this->options()->active()->exists();
    }

    public function hasUpcomingAvailability(): bool
    {
        // A tour whose options were all retired has nothing to sell on any
        // of its operating dates: availability is accurately unavailable even
        // when the schedule still defines dates (no-sale state).
        if ($this->hasOptionsConfigured() && ! $this->hasActiveOptions()) {
            return false;
        }

        return count($this->upcomingAvailableDates()) > 0;
    }

    /**
     * The per-person bookable price for this tour: the minimum among active
     * options when options are configured (the price a traveler can really
     * pay), otherwise the legacy tour-level amount for option-less tours.
     */
    public function lowestPriceAmount(): int
    {
        $activeOptions = $this->relationLoaded('options')
            ? $this->options->filter(fn (TourOption $option) => $option->isActive())
            : null;
        $hasActiveOptions = $activeOptions !== null
            ? $activeOptions->isNotEmpty()
            : $this->options()->active()->exists();

        if ($hasActiveOptions) {
            $options = $activeOptions ?? $this->options()->active()->get();

            return (int) $options->min('price_amount');
        }

        return (int) ($this->price_amount ?? 0);
    }

    public function currency(): string
    {
        return 'EUR';
    }

    public function averageRating(): float
    {
        return (float) ($this->average_rating ?? 0.0);
    }

    public function reviewCount(): int
    {
        return (int) ($this->review_count ?? 0);
    }

    public function allImageUrls(): array
    {
        $isUsable = static function ($url): bool {
            return is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
        };

        // Compatible reader: accept legacy image plus new cover/gallery rows.
        // The first usable cover-typed row leads even when its sort_order is
        // later; remaining usable rows follow in deterministic
        // sort_order/id order. The legacy cover column is only a fallback
        // for media-empty tours, never prepended to a valid gallery.
        /** @var Collection<int, TourMedia> $media */
        $media = $this->relationLoaded('media') ? $this->media : $this->media()->get();
        $rows = $media
            ->whereIn('type', ['image', 'cover', 'gallery'])
            ->sort(function (TourMedia $a, TourMedia $b): int {
                if ($a->sort_order !== $b->sort_order) {
                    return $a->sort_order <=> $b->sort_order;
                }

                return $a->id <=> $b->id;
            })
            ->values();

        $coverUrl = null;
        foreach ($rows as $row) {
            if ($row->type === 'cover' && $isUsable($row->url)) {
                $coverUrl = $row->url;

                break;
            }
        }

        $urls = [];
        if ($coverUrl !== null) {
            $urls[] = $coverUrl;
        }
        foreach ($rows as $row) {
            if ($isUsable($row->url) && ! in_array($row->url, $urls, true)) {
                $urls[] = $row->url;
            }
        }

        if ($urls !== []) {
            if ($coverUrl === null) {
                // No cover-typed row: a usable legacy cover match still leads;
                // a stale legacy cover is never prepended as an extra photo.
                $legacy = $this->cover_image_url;
                if (is_string($legacy) && in_array($legacy, $urls, true)) {
                    $urls = array_values(array_diff($urls, [$legacy]));
                    array_unshift($urls, $legacy);
                }
            }

            return $urls;
        }

        return $isUsable($this->cover_image_url) ? [$this->cover_image_url] : [];
    }

    /**
     * Upcoming bookable dates derived from availability_rules +
     * availability_exceptions (spec 004 / spec 006 FR-003, FR-036).
     *
     * This is the search-index-level approximation: it reflects rule-defined
     * dates minus blocking exceptions, NOT real-time booking occupancy. The
     * tour detail page performs the real-time availability check on load to
     * cover any staleness within the async index update window (FR-036).
     *
     * Reads the eager-loadable `availabilityRules` / `availabilityExceptions`
     * relation collections so it does not issue extra queries when preloaded.
     * Horizon and limit keep the search-index payload small.
     */
    public function upcomingAvailableDates(int $horizonDays = 90, int $limit = 60): array
    {
        $rules = $this->availabilityRules;
        if ($rules->isEmpty()) {
            return [];
        }

        $blocked = $this->availabilityExceptions
            ->filter(fn (AvailabilityException $e) => $e->exception_type === 'block')
            ->mapWithKeys(fn (AvailabilityException $e) => [$this->dateString($e->date) => true]);

        $from = Carbon::today();
        $to = (clone $from)->addDays($horizonDays - 1);

        $dates = [];

        foreach ($rules as $rule) {
            if ($rule->rule_type === 'specific_date') {
                $carbon = Carbon::parse($this->dateString($rule->start_date));
                if ($carbon >= $from && $carbon <= $to && ! isset($blocked[$carbon->toDateString()])) {
                    $dates[$carbon->toDateString()] = true;
                }

                continue;
            }

            // Recurring rule: walk the horizon window and keep each day the
            // rule covers (day-of-week within [start_date, end_date]).
            $cursor = clone $from;
            while ($cursor <= $to) {
                if ($this->ruleCoversDate($rule, $cursor) && ! isset($blocked[$cursor->toDateString()])) {
                    $dates[$cursor->toDateString()] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        return array_slice($dates, 0, $limit);
    }

    /**
     * Does this tour operate on the given date, per its availability rules
     * minus blocking exceptions? (F9 — gates booking creation against the
     * operating schedule, not just "future date".)
     *
     * Unlike `upcomingAvailableDates()`, this is NOT bounded by the 90-day /
     * 60-date search-index horizon — it answers for any concrete date. Shares
     * `ruleCoversDate()` with `upcomingAvailableDates()` so the two never drift.
     */
    public function operatesOnDate(Carbon $date): bool
    {
        if ($this->availabilityRules->isEmpty()) {
            return false;
        }

        $dateStr = $date->toDateString();
        $blocked = $this->availabilityExceptions
            ->contains(fn (AvailabilityException $e) => $e->exception_type === 'block' && $this->dateString($e->date) === $dateStr);

        if ($blocked) {
            return false;
        }

        foreach ($this->availabilityRules as $rule) {
            if ($this->ruleCoversDate($rule, $date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Earliest start time of the availability rules covering the given date,
     * or null when no covering rule declares one. Used to snapshot the tour
     * start time onto the booking at creation (F5) so cancellation windows
     * and no_show gates anchor to the actual start, not `tour_date` midnight.
     */
    public function startTimeForDate(Carbon $date): ?string
    {
        $times = [];
        foreach ($this->availabilityRules as $rule) {
            if ($this->ruleCoversDate($rule, $date) && $rule->start_time) {
                // The `datetime:H:i:s` cast returns a Carbon at runtime, but
                // larastan types `start_time` as string because of the format
                // suffix — widen the inferred type so the instanceof guard is
                // not flagged as dead and the format/cast both stay valid.
                /** @var Carbon|string $start */
                $start = $rule->start_time;
                $times[] = $start instanceof Carbon ? $start->format('H:i:s') : (string) $start;
            }
        }

        if (empty($times)) {
            return null;
        }

        sort($times);

        return $times[0];
    }

    /**
     * Single source of truth for "does this availability rule cover this date"
     * (ignoring the search-horizon window and blocking exceptions, which are
     * applied by the callers). specific_date matches the exact date; recurring
     * matches when the date's day-of-week is selected and falls in
     * [start_date, end_date].
     */
    protected function ruleCoversDate(AvailabilityRule $rule, Carbon $date): bool
    {
        if ($rule->rule_type === 'specific_date') {
            return $this->dateString($rule->start_date) === $date->toDateString();
        }

        $start = $rule->start_date ? Carbon::parse($this->dateString($rule->start_date))->startOfDay() : null;
        $end = $rule->end_date ? Carbon::parse($this->dateString($rule->end_date))->endOfDay() : null;

        if ($start && $date < $start) {
            return false;
        }
        if ($end && $date > $end) {
            return false;
        }

        $days = $rule->days_of_week ?? [];
        $dayOfWeek = $date->dayOfWeek;
        $dayName = strtolower($date->format('D')); // 'mon', 'tue', etc.

        return in_array($dayOfWeek, $days, false) || in_array($dayName, $days, true);
    }

    /**
     * Normalize a date attribute (Carbon|string|null) to a Y-m-d string.
     */
    protected function dateString($date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date instanceof Carbon ? $date->toDateString() : (string) $date;
    }

    public function guideLanguages(): array
    {
        return $this->guide_languages ?? [];
    }

    /** @return HasMany<TourTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(TourTranslation::class);
    }

    /** @return HasMany<TourTranslationState, $this> */
    public function translationStates(): HasMany
    {
        return $this->hasMany(TourTranslationState::class);
    }

    /**
     * Resolve the tour's display title from its translations.
     *
     * Tours have no `title` column — titles live in `tour_translations`. This
     * picks the requested locale, falling back to the tour's authored source
     * locale, then the first available translation, then an empty string. Used
     * by review-facing resources so a title is always shown regardless of which
     * translations exist. Reuses the eager-loaded `translations` collection
     * when available to avoid an extra query.
     */
    public function displayTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return optional(app(TourTranslationService::class)->currentTranslation($this, $locale))->title
            ?? optional($translations->firstWhere('locale', $this->sourceLocale()))->title
            ?? optional($translations->first())->title
            ?? '';
    }

    /**
     * The immutable authored-content locale of this tour.
     *
     * Existing rows and every pre-source-locale caller default to English;
     * unknown stored values also read as English rather than leaking an
     * unsupported locale into generation or projection.
     */
    public function sourceLocale(): string
    {
        $locale = $this->getAttribute('source_locale');

        return is_string($locale) && in_array($locale, self::CONTENT_LOCALES, true)
            ? $locale
            : self::DEFAULT_SOURCE_LOCALE;
    }

    /**
     * The authored source translation row, if it exists. Reuses the
     * eager-loaded collection when available.
     */
    public function sourceTranslation(): ?TourTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $this->sourceLocale());
    }

    /**
     * Locales generated from a given source locale, in stable platform order.
     *
     * @return array<int, string>
     */
    public static function derivativeLocalesFor(string $sourceLocale): array
    {
        return array_values(array_diff(self::CONTENT_LOCALES, [$sourceLocale]));
    }

    /**
     * Generated derivative locales of this tour, in stable platform order.
     *
     * @return array<int, string>
     */
    public function derivativeLocales(): array
    {
        return self::derivativeLocalesFor($this->sourceLocale());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public static function formatPrice(int $amount, string $currency): string
    {
        return match ($currency) {
            'EUR' => '€' . number_format($amount / 100, 2),
            'USD' => '$' . number_format($amount / 100, 2),
            default => number_format($amount / 100, 2) . ' ' . $currency,
        };
    }

    public function media(): HasMany
    {
        return $this->hasMany(TourMedia::class)->orderBy('sort_order');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }

    /**
     * Every option ever configured for this tour (active + retired), in
     * authored display order. Retirement never deletes rows, so this relation
     * is also the stable identity source for generated option translations.
     *
     * @return HasMany<TourOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(TourOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(TourDraft::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Governance audit entries targeting this tour (morph map `tour`).
     *
     * @return HasMany<GovernanceAuditLog, $this>
     */
    public function governanceAuditLogs(): HasMany
    {
        return $this->hasMany(GovernanceAuditLog::class, 'target_id')
            ->where('target_type', 'tour');
    }

    /**
     * Per-star review distribution for the tour-detail response
     * (tour-detail-api.md:65), keyed "5".."1". Counts the same review set
     * the aggregate rating uses (visible + flagged), so the distribution
     * sums to `reviewCount()` and reflects the publicly-shown reviews.
     */
    public function reviewDistribution(): array
    {
        $counts = Review::where('tour_id', $this->id)
            ->whereIn('status', ['visible', 'flagged'])
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return [
            '5' => (int) ($counts[5] ?? 0),
            '4' => (int) ($counts[4] ?? 0),
            '3' => (int) ($counts[3] ?? 0),
            '2' => (int) ($counts[2] ?? 0),
            '1' => (int) ($counts[1] ?? 0),
        ];
    }

    /**
     * The Partner record owning this tour (Spec 013, data-model.md §5).
     *
     * NOTE: `tours.partner_id` references `partners.id` (repinned by the
     * `fix_tours_partner_id_to_partners_table` migration), not users.id. The
     * legacy `partner()` relation still resolves to a User and is left as-is
     * for backward compatibility with existing columns; this relation gives
     * the authoritative Partner for governance guards.
     */
    public function partnerRecord(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Guard tour lifecycle transitions (FR-005).
     *
     * Allowed transitions (data-model.md §5):
     *  draft → pending_review; pending_review → published|rejected;
     *  rejected → pending_review; published → archived|draft.
     *
     * Publishing (→ published) is blocked unless the owning partner is
     * approved — `onboarding_status === 'approved'`.
     */
    public function canTransitionTo(TourStatus|string $to): bool
    {
        $to = $to instanceof TourStatus ? $to->value : $to;
        $from = $this->status;

        $allowed = [
            'draft' => ['pending_review'],
            'pending_review' => ['published', 'rejected'],
            'rejected' => ['pending_review'],
            'published' => ['archived', 'draft'],
            'archived' => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            return false;
        }

        if ($to === TourStatus::Published->value) {
            return optional($this->partnerRecord)->onboarding_status === 'approved';
        }

        return true;
    }
}
