<?php

namespace App\Domains\Booking\Models;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Payment\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->idempotency_key)) {
                $booking->idempotency_key = Str::uuid()->toString();
            }
        });
    }

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

    public const REFERENCE_PREFIX = 'BKO-';

    public const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const REFERENCE_LENGTH = 6;

    protected $fillable = [
        'reference',
        'traveler_id',
        'guest_identity_id',
        'tour_id',
        'tour_date',
        'start_time',
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
        'voucher_generated_at',
        'voucher_content_hash',
    ];

    protected function casts(): array
    {
        return [
            'tour_date' => 'date',
            'start_time' => 'datetime:H:i:s',
            'participant_count' => 'integer',
            'price_per_person' => 'integer',
            'total_price' => 'integer',
            'cancellation_window_hours' => 'integer',
            'cancelled_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'pending_expires_at' => 'datetime',
            'voucher_generated_at' => 'datetime',
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

    /**
     * Unified governance audit entries written by admin actions (Spec 013).
     * Inverse of GovernanceAuditLog::target() morphTo — target_type='booking'.
     */
    public function governanceAuditLogs(): HasMany
    {
        return $this->hasMany(GovernanceAuditLog::class, 'target_id')
            ->where('target_type', 'booking');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * All financial events for this booking — a charge plus any refund(s).
     * The audit endpoint exposes these as `linked_financial_events` (a list,
     * per audit-api.md), so this is a HasMany, not the single-row `payment()`.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('created_at');
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

    /**
     * The tour start as a concrete datetime for this booking — `tour_date`
     * set to the snapshotted `start_time`, or the configured default start
     * (bookings.default_start_time, '09:00') when no start time was captured.
     * Anchors cancellation and no_show cutoffs to the actual start, not
     * `tour_date` midnight (F5).
     */
    public function startDateTime(): Carbon
    {
        // `start_time` is cast `datetime:H:i:s` and `tour_date` is cast `date`;
        // both return Carbon at runtime, but larastan types them as string
        // because of the format suffixes — so parse them explicitly here.
        $time = $this->start_time
            ? Carbon::parse($this->start_time)->format('H:i:s')
            : config('bookings.default_start_time', '09:00');

        [$h, $m, $s] = array_pad(explode(':', $time), 3, '0');

        return Carbon::parse($this->tour_date)->startOfDay()->setTime((int) $h, (int) $m, (int) $s);
    }

    public function canCancel(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        if ($this->cancellation_window_hours === null) {
            return true;
        }

        $deadline = $this->startDateTime()->subHours($this->cancellation_window_hours);

        return now()->lt($deadline);
    }

    /**
     * Guard admin booking-status transitions (FR-009, data-model.md §5).
     *
     * Returns true only for admin-initiated transitions the admin surface is
     * allowed to record. Transitions with a financial side-effect
     * (confirmed → cancellation_requested|cancelled) are allowed here — the
     * TransitionBookingStatusAction delegates the refund to Spec 008 and only
     * logs the booking.transition audit entry; this guard does NOT execute
     * the refund.
     */
    public function canTransitionTo(string $to): bool
    {
        $allowed = [
            self::STATUS_CONFIRMED => [
                self::STATUS_COMPLETED,
                self::STATUS_NO_SHOW,
                self::STATUS_CANCELLATION_REQUESTED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PENDING_PAYMENT => [
                self::STATUS_EXPIRED,
            ],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }

    public static function formatPrice(int $amount, string $currency): string
    {
        return Tour::formatPrice($amount, $currency);
    }
}
