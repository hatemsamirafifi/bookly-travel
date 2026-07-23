<?php

namespace App\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use App\Domains\Reviews\Models\Review;
use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

class Tour extends Model
{
    use Searchable;

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
        'location',
        'location_slug',
        'duration_minutes',
        'duration_label',
        'group_size_min',
        'group_size_max',
        'price_amount',
        'status',
        'cover_image_url',
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
        ];
    }

    public function toSearchableArray(): array
    {
        $translations = $this->translations->keyBy('locale');
        $en = $translations->get('en');
        $es = $translations->get('es');
        $it = $translations->get('it');

        return [
            'id' => $this->id,
            'title_en' => $en?->title ?? '',
            'title_es' => $es?->title ?? '',
            'title_it' => $it?->title ?? '',
            'description_en' => $en?->description ?? '',
            'description_es' => $es?->description ?? '',
            'description_it' => $it?->description ?? '',
            'highlights_en' => $en ? json_encode($en->highlights ?? []) : '[]',
            'highlights_es' => $es ? json_encode($es->highlights ?? []) : '[]',
            'highlights_it' => $it ? json_encode($it->highlights ?? []) : '[]',
            'slug' => $this->slug,
            'location' => $this->location,
            'location_slug' => $this->location_slug,
            'category_name' => $this->category?->name ?? '',
            'category_slug' => $this->category?->slug ?? '',
            'price_amount' => $this->lowestPriceAmount(),
            'price_currency' => $this->currency(),
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->duration_label,
            'average_rating' => $this->averageRating(),
            'review_count' => $this->reviewCount(),
            'cover_image_url' => $this->cover_image_url,
            'image_urls' => $this->allImageUrls(),
            'group_size_min' => $this->group_size_min,
            'group_size_max' => $this->group_size_max,
            'available_dates' => $this->upcomingAvailableDates(),
            'languages' => $this->availableLanguages(),
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
     */
    public function scopeBookable($query)
    {
        return $query
            ->published()
            ->where('price_amount', '>', 0)
            ->whereHas('availabilityRules');
    }

    public function hasValidPricing(): bool
    {
        return $this->lowestPriceAmount() > 0;
    }

    public function hasUpcomingAvailability(): bool
    {
        return count($this->upcomingAvailableDates()) > 0;
    }

    public function lowestPriceAmount(): int
    {
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
        // Delegate to Tour Images (spec 003)
        return $this->cover_image_url ? [$this->cover_image_url] : [];
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
            assert($rule instanceof AvailabilityRule);
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
            assert($rule instanceof AvailabilityRule);
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
            assert($rule instanceof AvailabilityRule);
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

        return in_array($date->dayOfWeek, $rule->days_of_week ?? [], true);
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

    public function availableLanguages(): array
    {
        // Reuse the loaded `translations` collection (no extra query).
        return $this->translations
            ->filter(fn ($t) => filled($t->title))
            ->pluck('locale')
            ->all();
    }

    public function translations()
    {
        return $this->hasMany(TourTranslation::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
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
    public function partnerRecord()
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
            return ($this->partnerRecord?->onboarding_status ?? null) === 'approved';
        }

        return true;
    }
}
