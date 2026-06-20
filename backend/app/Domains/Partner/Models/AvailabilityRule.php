<?php

namespace App\Domains\Partner\Models;

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

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}