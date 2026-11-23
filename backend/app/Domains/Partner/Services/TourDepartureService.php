<?php

namespace App\Domains\Partner\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service governing explicit tour departures and independent capacity (EGM-005 D3, D4).
 *
 * Enforces:
 * - Concrete departure records with independent date and time.
 * - Authoritative UTC starts_at instant + IANA timezone reference.
 * - DST transition safety for local schedule conversions.
 * - Blackout detection and capacity overrides from availability exceptions.
 * - Bounded recurrence generation horizon up to 180 days.
 * - Idempotent generation preserving existing bookings, active counts, and cancelled state.
 * - Capacity update guard (D4 Rule 10): supplier capacity reductions below
 *   already reserved capacity must be rejected.
 */
class TourDepartureService
{
    public const MAX_HORIZON_DAYS = 180;

    public const DEFAULT_CUTOFF_HOURS = 2;

    /**
     * Resolve local date + time in a validated IANA timezone into authoritative UTC + synchronized local fields.
     *
     * @return array{starts_at: Carbon, local_date: string, local_time: string, timezone: string}
     */
    public function resolveStartsAtInTimezone(string $localDate, string $timeStr, string $timezone): array
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => ['The timezone must be a valid IANA timezone identifier.'],
            ]);
        }

        $normalizedTime = strlen($timeStr) === 5 ? $timeStr . ':00' : $timeStr;
        $localDateTimeStr = "{$localDate} {$normalizedTime}";

        try {
            $carbonLocal = Carbon::createFromFormat('Y-m-d H:i:s', $localDateTimeStr, $timezone);
            if (! $carbonLocal instanceof Carbon) {
                $carbonLocal = Carbon::parse($localDateTimeStr, $timezone);
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'local_time' => ['Invalid local departure date or time for the given timezone.'],
            ]);
        }

        return [
            'starts_at' => $carbonLocal->copy()->utc(),
            'local_date' => $carbonLocal->format('Y-m-d'),
            'local_time' => $carbonLocal->format('H:i:s'),
            'timezone' => $timezone,
        ];
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState === '23505' || (string) $exception->getCode() === '23505') {
            return true;
        }

        return $sqlState === '23000' && str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Batch reserved active seat counts per departure (CONFIRMED, COMPLETED, or unexpired PENDING_PAYMENT).
     *
     * @param  array<int, int>  $departureIds
     * @return array<int, int>
     */
    public function batchedReservedSeatsMap(array $departureIds): array
    {
        if ($departureIds === []) {
            return [];
        }

        $now = now();

        return Booking::query()
            ->whereIn('tour_departure_id', $departureIds)
            ->where(function ($query) use ($now): void {
                $query
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
                    ->orWhere(function ($sub) use ($now): void {
                        $sub
                            ->where('status', Booking::STATUS_PENDING_PAYMENT)
                            ->where('pending_expires_at', '>', $now);
                    });
            })
            ->groupBy('tour_departure_id')
            ->selectRaw('tour_departure_id, COALESCE(SUM(participant_count), 0) AS reserved_sum')
            ->pluck('reserved_sum', 'tour_departure_id')
            ->map(fn ($sum): int => (int) $sum)
            ->all();
    }

    /**
     * Serialize a departure with reservation metrics without extra queries when precomputed.
     *
     * @return array<string, mixed>
     */
    public function serializeDeparture(TourDeparture $departure, ?int $reserved = null): array
    {
        $departure->loadMissing('tourOption');
        $option = $departure->getRelationValue('tourOption');
        $reservedSeats = $reserved ?? $departure->reservedCapacity();
        $availableSpots = max(0, (int) $departure->capacity - $reservedSeats);
        $isBookable = $departure->status === TourDeparture::STATUS_ACTIVE
            && now()->addHours((int) $departure->booking_cutoff_hours)->lt($departure->starts_at)
            && $availableSpots >= 1;

        return [
            'id' => (int) $departure->id,
            'tour_id' => (int) $departure->tour_id,
            'tour_option_id' => (int) $departure->tour_option_id,
            'option_title' => $option instanceof TourOption ? $option->title : null,
            'starts_at' => $departure->starts_at->copy()->utc()->toIso8601String(),
            'timezone' => $departure->timezone,
            'local_date' => $departure->local_date,
            'local_time' => $departure->local_time,
            'capacity' => (int) $departure->capacity,
            'reserved_seats' => $reservedSeats,
            'available_spots' => $availableSpots,
            'booking_cutoff_hours' => (int) $departure->booking_cutoff_hours,
            'status' => $departure->status,
            'is_bookable' => $isBookable,
        ];
    }

    /**
     * List departures for a partner-owned tour with optional filters and zero N+1 queries.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listForTour(Tour $tour, array $filters = []): array
    {
        $query = TourDeparture::query()
            ->where('tour_id', $tour->id)
            ->with('tourOption')
            ->orderBy('starts_at', 'asc');

        if (! empty($filters['tour_option_id'])) {
            $query->where('tour_option_id', (int) $filters['tour_option_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('local_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('local_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $departures = $query->get();
        $ids = $departures->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $reservedMap = $this->batchedReservedSeatsMap($ids);

        return $departures
            ->map(fn (TourDeparture $departure): array => $this->serializeDeparture(
                $departure,
                $reservedMap[(int) $departure->id] ?? 0
            ))
            ->values()
            ->all();
    }

    /**
     * Create a single explicit departure for an active owned option (D3).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDeparture(Tour $tour, TourOption $option, array $attributes): TourDeparture
    {
        return DB::transaction(function () use ($tour, $option, $attributes): TourDeparture {
            $lockedTour = Tour::whereKey($tour->id)->lockForUpdate()->firstOrFail();

            $lockedOption = TourOption::where('tour_id', $lockedTour->id)
                ->whereKey($option->id)
                ->first();

            if (! $lockedOption) {
                throw new NotFoundHttpException('Tour option does not belong to this tour.');
            }

            if ($lockedOption->status !== TourOption::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'tour_option_id' => ['Cannot create departures for a retired tour option.'],
                ]);
            }

            $capacity = (int) ($attributes['capacity'] ?? 0);
            if ($capacity < 1 || $capacity > 1000) {
                throw ValidationException::withMessages([
                    'capacity' => ['Departure capacity must be between 1 and 1000.'],
                ]);
            }

            $cutoffHours = array_key_exists('booking_cutoff_hours', $attributes) && $attributes['booking_cutoff_hours'] !== null
                ? (int) $attributes['booking_cutoff_hours']
                : self::DEFAULT_CUTOFF_HOURS;
            if ($cutoffHours < 0 || $cutoffHours > 8760) {
                throw ValidationException::withMessages([
                    'booking_cutoff_hours' => ['Booking cutoff hours must be between 0 and 8760.'],
                ]);
            }

            $timezone = (string) ($attributes['timezone'] ?? config('app.timezone', 'UTC'));
            $resolved = $this->resolveStartsAtInTimezone(
                (string) $attributes['local_date'],
                (string) $attributes['local_time'],
                $timezone
            );

            try {
                /** @var TourDeparture $departure */
                $departure = DB::transaction(fn (): TourDeparture => TourDeparture::create([
                    'tour_id' => $lockedTour->id,
                    'tour_option_id' => $lockedOption->id,
                    'starts_at' => $resolved['starts_at'],
                    'timezone' => $resolved['timezone'],
                    'local_date' => $resolved['local_date'],
                    'local_time' => $resolved['local_time'],
                    'capacity' => $capacity,
                    'booking_cutoff_hours' => $cutoffHours,
                    'status' => TourDeparture::STATUS_ACTIVE,
                ]));
            } catch (QueryException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    throw ValidationException::withMessages([
                        'starts_at' => ['A departure already exists for this option at the selected date and time.'],
                    ]);
                }

                throw $exception;
            }

            return $departure->load('tourOption');
        });
    }

    /**
     * Bounded recurrence generation across active owned options (or one specified active option).
     *
     * @return Collection<int, TourDeparture>
     */
    public function generateForTour(
        Tour $tour,
        CarbonInterface $startDate,
        ?CarbonInterface $endDate = null,
        ?string $timezone = null,
        ?int $optionId = null
    ): Collection {
        $resolvedTimezone = $timezone ?: config('app.timezone', 'UTC');
        if (! in_array($resolvedTimezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => ['The timezone must be a valid IANA timezone identifier.'],
            ]);
        }

        return DB::transaction(function () use ($tour, $startDate, $endDate, $resolvedTimezone, $optionId): Collection {
            /** @var Tour $lockedTour */
            $lockedTour = Tour::whereKey($tour->id)->lockForUpdate()->firstOrFail();
            $lockedTour->load(['availabilityRules', 'availabilityExceptions']);

            if ($optionId !== null) {
                $option = TourOption::where('tour_id', $lockedTour->id)
                    ->where('id', $optionId)
                    ->first();

                if (! $option) {
                    throw new NotFoundHttpException('Tour option not found for this tour.');
                }

                if ($option->status !== TourOption::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        'tour_option_id' => ['Cannot generate departures for a retired tour option.'],
                    ]);
                }

                $options = new EloquentCollection([$option]);
            } else {
                $options = $lockedTour->activeOptions();
            }

            $all = new Collection;
            foreach ($options as $option) {
                foreach ($this->generateForOption($option, $startDate, $endDate, $resolvedTimezone, $lockedTour) as $dep) {
                    $all->push($dep);
                }
            }

            return $all;
        });
    }

    /**
     * Bounded recurrence generation for a tour option.
     *
     * @return EloquentCollection<int, TourDeparture>
     */
    public function generateForOption(
        TourOption $option,
        CarbonInterface $startDate,
        ?CarbonInterface $endDate = null,
        ?string $timezone = null,
        ?Tour $lockedTour = null
    ): EloquentCollection {
        $timezone = $timezone ?: config('app.timezone', 'UTC');
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => ['The timezone must be a valid IANA timezone identifier.'],
            ]);
        }

        // Bounded generation horizon: max 180 days
        $start = Carbon::parse($startDate->format('Y-m-d'));
        $maxEnd = $start->copy()->addDays(self::MAX_HORIZON_DAYS);

        if ($endDate === null) {
            $end = $maxEnd;
        } else {
            $requestedEnd = Carbon::parse($endDate->format('Y-m-d'));
            $end = $requestedEnd->gt($maxEnd) ? $maxEnd : $requestedEnd;
        }

        return DB::transaction(function () use ($option, $start, $end, $timezone, $lockedTour): EloquentCollection {
            /** @var Tour $tour */
            $tour = $lockedTour ?? Tour::with(['availabilityRules', 'availabilityExceptions'])
                ->whereKey($option->tour_id)
                ->lockForUpdate()
                ->firstOrFail();

            $optionStartTime = $option->startTimeString();
            $generated = new EloquentCollection;

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $localDateStr = $cursor->format('Y-m-d');
                $currentDate = Carbon::parse($localDateStr);

                // 1. Tour operating day check
                if (! $tour->operatesOnDate($currentDate)) {
                    $cursor->addDay();

                    continue;
                }

                // 2. Option weekday coverage check
                if (! $option->coversWeekday($currentDate)) {
                    $cursor->addDay();

                    continue;
                }

                // 3. Blackout detection (AvailabilityController 'block' + legacy 'closed')
                $isClosed = $tour->availabilityExceptions
                    ->contains(fn (AvailabilityException $ex): bool => in_array($ex->exception_type, ['block', 'closed'], true)
                        && $ex->date->format('Y-m-d') === $localDateStr);

                if ($isClosed) {
                    $cursor->addDay();

                    continue;
                }

                // 4. Resolve local start time & authoritative UTC instant
                $timeStr = $optionStartTime
                    ?? $tour->startTimeForDate($currentDate)
                    ?? '09:00:00';

                $resolved = $this->resolveStartsAtInTimezone($localDateStr, $timeStr, $timezone);
                $resolvedLocalDate = $resolved['local_date'];
                $resolvedLocalTime = $resolved['local_time'];
                $startsAtUtc = $resolved['starts_at'];

                // 5. Resolve capacity (AvailabilityController 'capacity_override' + legacy 'capacity')
                $capacityException = $tour->availabilityExceptions
                    ->first(fn (AvailabilityException $ex): bool => in_array($ex->exception_type, ['capacity_override', 'capacity'], true)
                        && $ex->date->format('Y-m-d') === $localDateStr);

                $capacity = $capacityException
                    ? (int) $capacityException->capacity
                    : $option->effectiveCapacity($tour);

                if ($capacity < 1) {
                    $cursor->addDay();

                    continue;
                }

                // 6. Idempotent upsert preserving existing bookings and never resurrecting cancelled departures
                $existing = TourDeparture::query()
                    ->where('tour_option_id', $option->id)
                    ->where('starts_at', $startsAtUtc)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->status !== TourDeparture::STATUS_ACTIVE) {
                        $cursor->addDay();

                        continue;
                    }

                    // Capacity reduction guard (D4 Rule 10):
                    // Do not reduce capacity below already reserved seats
                    $reserved = $existing->reservedCapacity();
                    if ($capacity < $reserved) {
                        $capacity = max($capacity, $reserved);
                    }

                    $existing->update([
                        'capacity' => $capacity,
                        'timezone' => $timezone,
                        'local_date' => $resolvedLocalDate,
                        'local_time' => $resolvedLocalTime,
                    ]);

                    $generated->push($existing);
                } else {
                    try {
                        /** @var TourDeparture $departure */
                        $departure = DB::transaction(fn (): TourDeparture => TourDeparture::create([
                            'tour_id' => $tour->id,
                            'tour_option_id' => $option->id,
                            'starts_at' => $startsAtUtc,
                            'timezone' => $timezone,
                            'local_date' => $resolvedLocalDate,
                            'local_time' => $resolvedLocalTime,
                            'capacity' => $capacity,
                            'booking_cutoff_hours' => self::DEFAULT_CUTOFF_HOURS,
                            'status' => TourDeparture::STATUS_ACTIVE,
                        ]));
                    } catch (QueryException $exception) {
                        if (! $this->isUniqueViolation($exception)) {
                            throw $exception;
                        }

                        $departure = TourDeparture::query()
                            ->where('tour_option_id', $option->id)
                            ->where('starts_at', $startsAtUtc)
                            ->firstOrFail();

                        if ($departure->status !== TourDeparture::STATUS_ACTIVE) {
                            $cursor->addDay();

                            continue;
                        }
                    }

                    $generated->push($departure);
                }

                $cursor->addDay();
            }

            return $generated;
        });
    }

    /**
     * Update departure attributes with capacity reduction guard (D4 Rule 10).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function updateDeparture(
        TourDeparture $departure,
        array $attributes,
        bool $allowBookedCancellation = false
    ): TourDeparture {
        return DB::transaction(function () use ($departure, $attributes, $allowBookedCancellation): TourDeparture {
            Tour::whereKey($departure->tour_id)->lockForUpdate()->first();
            $locked = TourDeparture::whereKey($departure->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TourDeparture::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'status' => ['Only active departures can be updated.'],
                ]);
            }

            $safe = array_intersect_key($attributes, array_flip(['capacity', 'booking_cutoff_hours', 'status']));

            if (array_key_exists('booking_cutoff_hours', $safe)) {
                $cutoff = (int) $safe['booking_cutoff_hours'];
                if ($cutoff < 0 || $cutoff > 8760) {
                    throw ValidationException::withMessages([
                        'booking_cutoff_hours' => ['Booking cutoff hours must be between 0 and 8760.'],
                    ]);
                }
                $safe['booking_cutoff_hours'] = $cutoff;
            }

            $reserved = null;
            if (array_key_exists('capacity', $safe)) {
                $newCapacity = (int) $safe['capacity'];
                if ($newCapacity < 1 || $newCapacity > 1000) {
                    throw ValidationException::withMessages([
                        'capacity' => ['Departure capacity must be between 1 and 1000.'],
                    ]);
                }

                $reserved = $locked->reservedCapacity();
                if ($newCapacity < $reserved) {
                    throw ValidationException::withMessages([
                        'capacity' => ["Cannot reduce departure capacity below currently reserved seats ({$reserved})."],
                    ]);
                }
                $safe['capacity'] = $newCapacity;
            }

            if (array_key_exists('status', $safe)) {
                if (! in_array($safe['status'], [TourDeparture::STATUS_ACTIVE, TourDeparture::STATUS_CANCELLED], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Invalid departure status transition.'],
                    ]);
                }
                if ($safe['status'] === TourDeparture::STATUS_CANCELLED) {
                    $reserved ??= $locked->reservedCapacity();
                    if ($reserved > 0 && ! $allowBookedCancellation) {
                        throw ValidationException::withMessages([
                            'status' => ["Cannot cancel departure with {$reserved} active reserved seats without confirmation."],
                        ]);
                    }
                }
            }

            $locked->update($safe);
            $locked->refresh();

            return $locked->load('tourOption');
        });
    }

    /**
     * Cancel a departure (D3), refusing booked departures unless explicitly confirmed and never deleting rows.
     */
    public function cancelDeparture(TourDeparture $departure, bool $allowBookedCancellation = false): TourDeparture
    {
        return DB::transaction(function () use ($departure, $allowBookedCancellation): TourDeparture {
            Tour::whereKey($departure->tour_id)->lockForUpdate()->first();
            $locked = TourDeparture::whereKey($departure->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TourDeparture::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Completed departures cannot be cancelled.'],
                ]);
            }

            if ($locked->status === TourDeparture::STATUS_CANCELLED) {
                return $locked->load('tourOption');
            }

            $reserved = $locked->reservedCapacity();
            if ($reserved > 0 && ! $allowBookedCancellation) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot cancel departure with {$reserved} active reserved seats without explicit confirmation."],
                ]);
            }

            $locked->update(['status' => TourDeparture::STATUS_CANCELLED]);

            return $locked->fresh('tourOption') ?? $locked;
        });
    }
}
