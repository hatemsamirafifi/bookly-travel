<?php

namespace App\Models;

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
