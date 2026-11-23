<?php

namespace App\Models;

use App\Domains\Search\Services\TourPublicCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generated derivative texts for one purchasable option, one locale per row
 * (mirroring the `tour_translations` pattern). The authored source text lives
 * on `tour_options` itself; these rows exist only for generated derivative
 * locales whose tour-level derivative is genuinely current. Bookings never
 * read these rows for historical truth — they snapshot title/pickup (plus
 * the text's content locale) at creation time.
 */
class TourOptionTranslation extends Model
{
    protected $fillable = [
        'tour_option_id',
        'locale',
        'title',
        'pickup_instructions',
    ];

    protected function casts(): array
    {
        return [
            'tour_option_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (TourOptionTranslation $translation) {
            $translation->invalidateForTour();
        });

        static::deleted(function (TourOptionTranslation $translation) {
            $translation->invalidateForTour();
        });
    }

    /** Drop the owning tour's current detail payload after commit. */
    private function invalidateForTour(): void
    {
        $tourId = (int) $this->option()->value('tour_id');

        if ($tourId > 0) {
            app(TourPublicCacheService::class)->forgetDetailAfterCommit(
                Tour::query()->whereKey($tourId)->pluck('slug')->filter()->values()->all()
            );
        }
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(TourOption::class, 'tour_option_id');
    }
}
