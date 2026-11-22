<?php

namespace App\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $locale
 * @property string $title
 * @property string|null $description
 * @property array<int, string>|null $highlights
 * @property array<int, string>|null $inclusions
 * @property array<int, string>|null $exclusions
 * @property string|null $meeting_point
 * @property string|null $cancellation_policy
 * @property array<int, array<string, mixed>>|null $itinerary
 * @property array<int, string>|null $important_information
 * @property-read Tour $tour
 */
class TourTranslation extends Model
{
    /** Resolve the owning tour's detail cache invalidation slugs. */
    private static function resolveSlugs(int $tourId): array
    {
        return Tour::query()->whereKey($tourId)->pluck('slug')->filter()->values()->all();
    }

    protected static function booted(): void
    {
        static::saved(function (TourTranslation $translation) {
            // Every existing translation invalidation is preserved, now also
            // against the current versioned detail keys.
            app(TourPublicCacheService::class)
                ->forgetDetailAfterCommit(self::resolveSlugs((int) $translation->tour_id));
        });

        static::deleted(function (TourTranslation $translation) {
            app(TourPublicCacheService::class)
                ->forgetDetailAfterCommit(self::resolveSlugs((int) $translation->tour_id));
        });
    }

    protected $touches = ['tour'];

    protected $fillable = [
        'tour_id',
        'locale',
        'title',
        'description',
        'highlights',
        'inclusions',
        'exclusions',
        'meeting_point',
        'cancellation_policy',
        'itinerary',
        'important_information',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'itinerary' => 'array',
            'important_information' => 'array',
        ];
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
