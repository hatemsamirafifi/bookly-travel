<?php

namespace App\Domains\Partner\Models;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property string $type
 * @property string $url
 * @property string|null $thumbnail_url
 * @property int $sort_order
 */
class TourMedia extends Model
{
    protected $table = 'tour_media';

    const UPDATED_AT = null;

    protected $fillable = [
        'tour_id',
        'type',
        'url',
        'thumbnail_url',
        'sort_order',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
