<?php

namespace App\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $locale
 * @property string $source_hash
 * @property string|null $translated_hash
 * @property string $status
 * @property string|null $last_error_code
 * @property int $generation_epoch
 */
class TourTranslationState extends Model
{
    /** Resolve the owning tour's detail cache invalidation slugs. */
    private static function invalidateForTour(int $tourId): void
    {
        // A missing slug (row deleted with its tour) leaves nothing to evict;
        // unrelated keys are never flushed.
        app(TourPublicCacheService::class)->forgetDetailAfterCommit(
            Tour::query()->whereKey($tourId)->pluck('slug')->all()
        );
    }

    protected static function booted(): void
    {
        static::saved(function (TourTranslationState $state) {
            // Derivative state/content transitions (failed→pending→ready) are
            // public payload transitions: the current versioned key plus the
            // legacy key drop so a stale derivative is never served (design
            // v3 §8).
            self::invalidateForTour((int) $state->tour_id);
        });

        static::deleted(function (TourTranslationState $state) {
            self::invalidateForTour((int) $state->tour_id);
        });
    }

    /**
     * Operational internals never leave the server: revision hashes, epochs
     * and error categories stay queryable but are hidden from serialization.
     */
    protected $hidden = [
        'source_hash',
        'translated_hash',
        'last_error_code',
        'generation_epoch',
    ];

    protected $fillable = [
        'tour_id',
        'locale',
        'source_hash',
        'translated_hash',
        'status',
        'last_error_code',
        'generation_epoch',
    ];

    protected $casts = [
        'generation_epoch' => 'integer',
        'tour_id' => 'integer',
    ];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function isCurrent(Tour $tour, string $sourceHash): bool
    {
        return $this->status === 'ready'
            && $this->translated_hash === $sourceHash
            && $this->generation_epoch === $tour->generationEpoch();
    }
}
