<?php

namespace App\Domains\Search\Support;

/**
 * Duration bucket thresholds, defined once and consumed by both the
 * Meilisearch filter expression (SearchToursAction::durationFilter) and the
 * facet-count SQL CASE (facetAggregates), so the two cannot drift.
 *
 * Half-day: ≤ 240 min (4h); Full-day: 241–480 min (4–8h); Multi-day: > 480.
 */
enum DurationBucket: string
{
    case HalfDay = 'half-day';
    case FullDay = 'full-day';
    case MultiDay = 'multi-day';

    public static function halfDayMax(): int
    {
        return 240;
    }

    public static function fullDayMax(): int
    {
        return 480;
    }

    /**
     * Meilisearch filter expression for the bucket, e.g.
     * `duration_minutes >= 241 AND duration_minutes <= 480`.
     */
    public function filterExpression(): string
    {
        return match ($this) {
            self::HalfDay => 'duration_minutes <= ' . self::halfDayMax(),
            self::FullDay => 'duration_minutes >= ' . (self::halfDayMax() + 1)
                . ' AND duration_minutes <= ' . self::fullDayMax(),
            self::MultiDay => 'duration_minutes > ' . self::fullDayMax(),
        };
    }

    /**
     * SQL CASE WHEN fragment for counting tours per bucket. Exposed so the
     * facet aggregate query and the bucket definition share thresholds.
     */
    public static function sqlCase(): string
    {
        return 'CASE '
            . 'WHEN duration_minutes <= ' . self::halfDayMax() . " THEN '" . self::HalfDay->value . "' "
            . 'WHEN duration_minutes <= ' . self::fullDayMax() . " THEN '" . self::FullDay->value . "' "
            . "ELSE '" . self::MultiDay->value . "' "
            . 'END';
    }
}
