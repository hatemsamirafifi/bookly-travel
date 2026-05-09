<?php

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAuditLog extends Model
{
    // IMMUTABLE — application code MUST NOT UPDATE or DELETE rows
    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'actor_type',
        'actor_id',
        'action',
        'before_state',
        'after_state',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
