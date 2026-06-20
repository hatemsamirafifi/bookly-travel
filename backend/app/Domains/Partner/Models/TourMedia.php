<?php

namespace App\Domains\Partner\Models;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourMedia extends Model
{
    protected $table = 'tour_media';

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