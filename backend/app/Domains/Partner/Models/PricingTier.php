<?php

namespace App\Domains\Partner\Models;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingTier extends Model
{
    protected $fillable = [
        'tour_id',
        'name',
        'price',
        'min_participants',
        'max_participants',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_participants' => 'integer',
            'max_participants' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
