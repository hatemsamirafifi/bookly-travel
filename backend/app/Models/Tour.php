<?php

namespace App\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $this->status === 'published'
            && $this->hasValidPricing()
            && $this->hasUpcomingAvailability();
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

    public function upcomingAvailableDates(): array
    {
        // Delegate to Availability domain (spec 004) — placeholder returns empty array
        return [];
    }

    public function availableLanguages(): array
    {
        return $this->translations()
            ->whereNotNull('title')
            ->pluck('locale')
            ->toArray();
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
