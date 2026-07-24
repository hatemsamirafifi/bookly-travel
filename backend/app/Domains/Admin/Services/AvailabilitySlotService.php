<?php

namespace App\Domains\Admin\Services;

use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes read-only availability "slots" for a tour over a date range
 * (Spec 013, US8, FR-014).
 *
 * Availability is partner-owned and defined by AvailabilityRule (recurring /
 * specific-date) + AvailabilityException (block / capacity_override), with
 * the tour's `group_size_max` as the default capacity. This service derives a
 * per-date slot with a booking state (empty / partially_booked / full /
 * unavailable) for admin oversight — it never mutates anything.
 */
class AvailabilitySlotService
{
    /**
     * @return list<array{date: string, weekday: string, capacity: int, booked: int, remaining: int, state: string}>
     */
    public function slotsForRange(Tour $tour, Carbon $from, int $days = 14): array
    {
        $tour->load(['availabilityRules', 'availabilityExceptions']);

        $exceptions = $tour->availabilityExceptions->keyBy(fn ($e) => optional($e->date)->toDateString());
        $bookingsByDate = $this->bookingsByDate($tour, $from, $days);

        $slots = [];
        for ($i = 0; $i < $days; $i++) {
            $date = (clone $from)->addDays($i);
            $key = $date->toDateString();

            $blocked = false;
            $capacity = (int) $tour->group_size_max;
            $capacityOverridden = false;

            if ($exceptions->has($key)) {
                $exception = $exceptions->get($key);
                if ($exception->exception_type === 'block') {
                    $blocked = true;
                } elseif ($exception->exception_type === 'capacity_override' && $exception->capacity !== null) {
                    $capacity = (int) $exception->capacity;
                    $capacityOverridden = true;
                }
            }

            // A capacity_override exception wins over the recurring/specific-date
            // rule capacity; only fall back to the rule when neither a block nor
            // an override applies to this date.
            if (! $blocked && ! $capacityOverridden) {
                $ruleCapacity = $this->ruleCapacityForDate($tour, $date);
                if ($ruleCapacity !== null) {
                    $capacity = $ruleCapacity;
                }
            }

            $booked = $bookingsByDate[$key] ?? 0;
            $remaining = $blocked ? 0 : max(0, $capacity - $booked);
            $state = $this->state($blocked, $capacity, $booked);

            $slots[] = [
                'date' => $key,
                'weekday' => $date->shortEnglishDayOfWeek,
                'capacity' => $capacity,
                'booked' => $booked,
                'remaining' => $remaining,
                'state' => $state,
            ];
        }

        return $slots;
    }

    private function ruleCapacityForDate(Tour $tour, Carbon $date): ?int
    {
        foreach ($tour->availabilityRules as $rule) {
            if ($rule->rule_type === 'specific_date') {
                if ($rule->start_date?->toDateString() === $date->toDateString()) {
                    return (int) $rule->capacity;
                }
                continue;
            }

            // recurring
            $start = $rule->start_date?->startOfDay();
            $end = $rule->end_date?->endOfDay();
            if ($start && $date->lt($start)) {
                continue;
            }
            if ($end && $date->gt($end)) {
                continue;
            }
            $daysOfWeek = $rule->days_of_week ?? [];
            if (in_array($date->dayOfWeek, $daysOfWeek, true)) {
                return (int) $rule->capacity;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function bookingsByDate(Tour $tour, Carbon $from, int $days): array
    {
        $to = (clone $from)->addDays($days - 1)->toDateString();

        $rows = DB::table('bookings')
            ->select('tour_date', DB::raw('SUM(participant_count) as booked'))
            ->where('tour_id', $tour->id)
            ->whereIn('status', ['pending_payment', 'confirmed', 'completed'])
            ->whereBetween('tour_date', [$from->toDateString(), $to])
            ->groupBy('tour_date')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->tour_date] = (int) $row->booked;
        }

        return $map;
    }

    private function state(bool $blocked, int $capacity, int $booked): string
    {
        if ($blocked || $capacity <= 0) {
            return 'unavailable';
        }
        if ($booked <= 0) {
            return 'empty';
        }
        if ($booked >= $capacity) {
            return 'full';
        }

        return 'partially_booked';
    }

    /**
     * Render the derived slots as a read-only HTML table for the Filament view page.
     */
    public function renderSlotsTable(Tour $tour): string
    {
        $slots = $this->slotsForRange($tour, Carbon::today());

        $colors = [
            'empty' => '#10b981',
            'partially_booked' => '#f59e0b',
            'full' => '#ef4444',
            'unavailable' => '#9ca3af',
        ];

        $rows = '';
        foreach ($slots as $slot) {
            $color = $colors[$slot['state']] ?? '#9ca3af';
            $stateLabel = ucfirst(str_replace('_', ' ', $slot['state']));
            $rows .= '<tr>'
                . '<td style="padding:4px 8px">' . e($slot['date']) . '</td>'
                . '<td style="padding:4px 8px">' . e($slot['weekday']) . '</td>'
                . '<td style="padding:4px 8px">' . $slot['capacity'] . '</td>'
                . '<td style="padding:4px 8px">' . $slot['booked'] . '</td>'
                . '<td style="padding:4px 8px">' . $slot['remaining'] . '</td>'
                . '<td style="padding:4px 8px"><span data-slot-state="' . e($slot['state']) . '" style="color:' . $color . ';font-weight:600">' . $stateLabel . '</span></td>'
                . '</tr>';
        }

        return '<table class="w-full text-sm" data-availability-slots>'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:4px 8px">Date</th>'
            . '<th style="text-align:left;padding:4px 8px">Day</th>'
            . '<th style="text-align:left;padding:4px 8px">Capacity</th>'
            . '<th style="text-align:left;padding:4px 8px">Booked</th>'
            . '<th style="text-align:left;padding:4px 8px">Remaining</th>'
            . '<th style="text-align:left;padding:4px 8px">State</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';
    }
}