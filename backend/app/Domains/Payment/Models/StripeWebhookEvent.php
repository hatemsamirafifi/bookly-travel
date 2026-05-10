<?php

namespace App\Domains\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'processing_status',
        'payload_hash',
        'raw_payload',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
