<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $locale
 * @property string $source_hash
 * @property string|null $translated_hash
 * @property string $status
 * @property string|null $last_error_code
 */
class TourTranslationState extends Model
{
    /**
     * Operational internals never leave the server: revision hashes and
     * error categories stay queryable but are hidden from serialization.
     */
    protected $hidden = [
        'source_hash',
        'translated_hash',
        'last_error_code',
    ];

    protected $fillable = [
        'tour_id',
        'locale',
        'source_hash',
        'translated_hash',
        'status',
        'last_error_code',
    ];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
