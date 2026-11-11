<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A purchasable option (departure/variant) of a tour.
 *
 * Options are scoped to exactly one tour and are never shared: an option id
 * from another tour is rejected at booking time. Options are retired, not
 * deleted (`status` = active|retired + `retired_at`), so the id stays stable
 * for historical bookings; re-adding the same id re-activates it.
 *
 * `title` and `pickup_instructions` are authored in the tour's source locale;
 * generated derivative texts live in `tour_option_translations`, keyed by this
 * stable id. `sort_order` is the partner-authored display order and
 * participates in the source hash (reorder = new translation revision), while
 * price/capacity/start time/weekdays are structured-only and never invalidate
 * a translation. Bookings snapshot both texts plus `content_locale` so a past
 * booking keeps showing what the traveler actually bought.
 *
 * @property-read array<int, string>|null $guide_languages
 * @property-read array<int, int|string>|null $weekdays
 * @property-read Carbon|string|null $start_time
 */
class TourOption extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'tour_id',
        'title',
        'pickup_instructions',
        'guide_languages',
        'price_amount',
        'group_size_max',
        'start_time',
        'weekdays',
        'status',
        'retired_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'guide_languages' => 'array',
            'price_amount' => 'integer',
            'group_size_max' => 'integer',
            'start_time' => 'datetime:H:i:s',
            'weekdays' => 'array',
            'retired_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Generated derivative texts for this option, one row per locale.
     *
     * @return HasMany<TourOptionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(TourOptionTranslation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The option's own weekday allow-list (0 = Sunday … 6 = Saturday, matching
     * Carbon's dayOfWeek). Null or empty means "every weekday the tour runs" —
     * the option never widens the tour schedule, it only narrows it.
     */
    public function coversWeekday(Carbon $date): bool
    {
        $weekdays = $this->weekdays;

        if (! is_array($weekdays) || $weekdays === []) {
            return true;
        }

        $dayName = strtolower($date->format('D'));

        foreach ($weekdays as $weekday) {
            if (is_numeric($weekday) && (int) $weekday === $date->dayOfWeek) {
                return true;
            }

            if (is_string($weekday) && strtolower($weekday) === $dayName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seats available per departure for this option. Falls back to the tour's
     * group_size_max when the option does not narrow it.
     */
    public function effectiveCapacity(?Tour $tour = null): int
    {
        $capacity = $this->group_size_max ?? $tour?->group_size_max;

        return max(1, (int) ($capacity ?? 1));
    }

    /**
     * The option start time as H:i:s, or null when the option inherits the
     * tour's start time. The `datetime:H:i:s` cast yields a Carbon at runtime
     * but larastan types it as string because of the format suffix, so both
     * shapes are handled.
     */
    public function startTimeString(): ?string
    {
        if ($this->start_time === null) {
            return null;
        }

        return $this->start_time instanceof Carbon
            ? $this->start_time->format('H:i:s')
            : (string) $this->start_time;
    }

    /**
     * Spoken guide language codes for this departure.
     *
     * @return array<int, string>
     */
    public function guideLanguages(): array
    {
        return is_array($this->guide_languages) ? $this->guide_languages : [];
    }
}
