<?php

namespace App\Domains\Payment\Models;

use App\Domains\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->stripe_payment_intent_id)) {
                $payment->stripe_payment_intent_id = 'pi_test_' . \Illuminate\Support\Str::random(24);
            }
        });
    }

    protected $fillable = [
        'booking_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'type',
        'amount',
        'currency',
        'status',
        'card_last_four',
        'card_brand',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
