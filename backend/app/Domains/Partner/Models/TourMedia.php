<?php

namespace App\Domains\Partner\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property string $type
 * @property string $url
 * @property string|null $thumbnail_url
 * @property string|null $alt_text
 * @property int $sort_order
 */
class TourMedia extends Model
{
    public const TYPE_LEGACY_IMAGE = 'image';

    public const TYPE_COVER = 'cover';

    public const TYPE_GALLERY = 'gallery';

    protected $table = 'tour_media';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        // Media rows are projected into the public gallery/cover, so the
        // current versioned key plus the legacy key drop after commit.
        static::saved(function (self $media) {
            self::invalidateForTour((int) $media->tour_id);
        });

        static::deleted(function (self $media) {
            self::invalidateForTour((int) $media->tour_id);
        });
    }

    private static function invalidateForTour(int $tourId): void
    {
        app(TourPublicCacheService::class)->forgetDetailAfterCommit(
            Tour::query()->whereKey($tourId)->pluck('slug')->filter()->values()->all()
        );
    }

    protected $fillable = [
        'tour_id',
        'type',
        'url',
        'thumbnail_url',
        'alt_text',
        'sort_order',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
