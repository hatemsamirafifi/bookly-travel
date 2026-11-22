<?php

namespace App\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Search\Services\TourPublicCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A concrete bookable occurrence of a tour option (EGM-005, design §3.2.2).
 *
 * `starts_at` is the authoritative UTC instant; `timezone`/`local_date`/
 * `local_time` are cached display projections of the same instant in the
 * operating location's IANA timezone. Capacity is independent per departure
 * (D4) — there are no shared pools, so a booking on one departure never
 * consumes inventory of any other.
 *
 * @property int $tour_id
 * @property int $tour_option_id
 * @property Carbon $starts_at
 * @property string $timezone
 * @property string $local_date
 * @property string $local_time
 * @property int $capacity
 * @property int $booking_cutoff_hours
 * @property string $status
 * @property int|null $recurrence_rule_id
 * @property-read Tour $tour
 * @property-read TourOption $tourOption
 */
class TourDeparture extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'tour_id',
        'tour_option_id',
        'starts_at',
        'timezone',
        'local_date',
        'local_time',
        'capacity',
        'booking_cutoff_hours',
        'status',
        'recurrence_rule_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'capacity' => 'integer',
            'booking_cutoff_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $departure): void {
            self::invalidateForTour((int) $departure->tour_id);
        });

        static::deleted(function (self $departure): void {
            self::invalidateForTour((int) $departure->tour_id);
        });
    }

    private static function invalidateForTour(int $tourId): void
    {
        app(TourPublicCacheService::class)->forgetDetailAfterCommit(
            Tour::query()->whereKey($tourId)->pluck('slug')->filter()->values()->all()
        );
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function tourOption(): BelongsTo
    {
        return $this->belongsTo(TourOption::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'tour_departure_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Whether the departure can take new bookings for the given group size:
     * it must be active, the booking cutoff (cutoff hours before starts_at)
     * must not have passed, and enough independent capacity must remain
     * (D3, D4).
     */
    public function isBookable(int $participantCount = 1): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if (! now()->addHours($this->booking_cutoff_hours)->lt($this->starts_at)) {
            return false;
        }

        return $this->remainingCapacity() >= $participantCount;
    }

    /**
     * Seats already reserved on this departure: confirmed/completed bookings
     * count unconditionally; pending_payment bookings count only while their
     * `pending_expires_at` is in the future (expired pendings release their
     * hold, per design §4.4 rule 4).
     */
    public function reservedCapacity(): int
    {
        return (int) $this->bookings()
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
                    ->orWhere(function (Builder $sub): void {
                        $sub
                            ->where('status', Booking::STATUS_PENDING_PAYMENT)
                            ->where('pending_expires_at', '>', now());
                    });
            })
            ->sum('participant_count');
    }

    /**
     * Seats left on this departure, floored at zero (over-holds never
     * produce negative availability).
     */
    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->reservedCapacity());
    }
}
