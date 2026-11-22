<?php

namespace App\Domains\Partner\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    protected $fillable = [
        'tour_id',
        'rule_type',
        'days_of_week',
        'start_time',
        'start_date',
        'end_date',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'start_time' => 'datetime:H:i:s',
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Schedule changes are visible immediately in the projected available
        // dates, so the current versioned key plus the legacy key drop after
        // commit instead of surviving a 900-second TTL (design v3 §8).
        static::saved(function (self $rule) {
            self::invalidateForTour((int) $rule->tour_id);
        });

        static::deleted(function (self $rule) {
            self::invalidateForTour((int) $rule->tour_id);
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
