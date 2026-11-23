<?php

namespace App\Domains\Partner\Models;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDraft extends Model
{
    protected $fillable = [
        'tour_id',
        'partner_id',
        'payload',
        'status',
        'rejection_reason',
        'auto_saved_at',
        'creation_id',
        'creation_payload_hash',
        'concurrency_token',
        'current_step',
        'source_locale',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'auto_saved_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
