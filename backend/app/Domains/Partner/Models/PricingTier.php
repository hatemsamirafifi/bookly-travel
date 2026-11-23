<?php

namespace App\Domains\Partner\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingTier extends Model
{
    protected $fillable = [
        'tour_id',
        'name',
        'price',
        'min_participants',
        'max_participants',
        'tour_option_id',
        'price_amount',
        'currency',
        'sort_order',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_participants' => 'integer',
            'max_participants' => 'integer',
            'tour_option_id' => 'integer',
            'price_amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Commerce stays a separate operational contract, but the served tour
        // detail payload must never outlive a pricing change: drop the current
        // versioned key plus the legacy key after commit (design v3 §8).
        static::saved(function (self $tier) {
            self::invalidateForTour((int) $tier->tour_id);
        });

        static::deleted(function (self $tier) {
            self::invalidateForTour((int) $tier->tour_id);
        });
    }

    private static function invalidateForTour(int $tourId): void
    {
        app(TourPublicCacheService::class)->forgetDetailAfterCommit(
            Tour::query()->whereKey($tourId)->pluck('slug')->filter()->values()->all()
        );
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * The option this tier prices (EGM-005 D5). Null for legacy tour-level
     * tiers, which keep resolving via `tour()`.
     */
    public function tourOption(): BelongsTo
    {
        return $this->belongsTo(TourOption::class);
    }

    /**
     * The authoritative per-person unit price in integer minor units (cents).
     * Falls back to the legacy decimal `price` (× 100, rounded) when
     * `price_amount` has not been set (design §3.2.3).
     */
    public function unitPriceCents(): int
    {
        return $this->price_amount ?? (int) round(((float) $this->price) * 100);
    }
}
