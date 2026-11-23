<?php

namespace App\Domains\Partner\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    protected $table = 'availability_exceptions';

    const UPDATED_AT = null;

    protected $fillable = [
        'tour_id',
        'exception_type',
        'date',
        'start_time',
        'capacity',
        'price_multiplier',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i:s',
            'capacity' => 'integer',
            'price_multiplier' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // A blocking or re-opened exception date changes the projected available
        // dates immediately, so the current versioned key plus the legacy key
        // drop after commit (design v3 §8).
        static::saved(function (self $exception) {
            self::invalidateForTour((int) $exception->tour_id);
        });

        static::deleted(function (self $exception) {
            self::invalidateForTour((int) $exception->tour_id);
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
}
