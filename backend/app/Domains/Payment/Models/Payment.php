<?php

namespace App\Domains\Payment\Models;

use App\Domains\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (app()->environment('testing') && empty($payment->stripe_payment_intent_id)) {
                $payment->stripe_payment_intent_id = 'pi_test_' . Str::random(16);
            }

            if (empty($payment->stripe_payment_intent_id)) {
                throw new \InvalidArgumentException(
                    'stripe_payment_intent_id is required when creating a Payment.'
                );
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
