<?php

namespace App\Domains\Booking\Models;

use App\Domains\Payment\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_EXPIRED = 'expired';

    public const REFERENCE_PREFIX = 'BKO-';
    public const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const REFERENCE_LENGTH = 6;

    protected $fillable = [
        'reference',
        'traveler_id',
        'tour_id',
        'tour_date',
        'participant_count',
        'price_per_person',
        'total_price',
        'currency',
        'status',
        'idempotency_key',
        'cancellation_policy',
        'cancellation_window_hours',
        'cancelled_at',
        'cancellation_reason',
        'confirmation_email_sent_at',
        'locale',
        'anonymized_at',
        'stripe_payment_intent_id',
        'payment_confirmed_at',
        'pending_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tour_date' => 'date',
            'participant_count' => 'integer',
            'price_per_person' => 'integer',
            'total_price' => 'integer',
            'cancellation_window_hours' => 'integer',
            'cancelled_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'pending_expires_at' => 'datetime',
        ];
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BookingAuditLog::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public static function generateReference(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $length = self::REFERENCE_LENGTH;
        $alphabetLength = strlen($alphabet);

        do {
            $random = '';
            for ($i = 0; $i < $length; $i++) {
                $random .= $alphabet[random_int(0, $alphabetLength - 1)];
            }
            $reference = self::REFERENCE_PREFIX . $random;
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function canCancel(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        if ($this->cancellation_window_hours === null) {
            return true;
        }

        $deadline = (clone $this->tour_date)->subHours($this->cancellation_window_hours);

        return now()->lt($deadline);
    }

    public static function formatPrice(int $amount, string $currency): string
    {
        return Tour::formatPrice($amount, $currency);
    }
}
