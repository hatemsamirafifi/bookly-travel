<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Requests\TourContentRules;
use Illuminate\Support\Facades\Validator;

/**
 * Legacy itinerary adoption normalizer (Spec 019 T067).
 *
 * Converts raw `tours.itinerary` legacy payloads into the canonical
 * structured day array accepted as English source (`day`/`title`/
 * `description`/`stops` with `title`/`description`/`duration_minutes`
 * stops). The raw legacy column is never rewritten here; callers keep it
 * verbatim and persist only the canonical result.
 *
 * Contract:
 * - Valid legacy list of strings becomes positional days starting at 1
 *   (title bound 160, description null, stops []).
 * - Valid legacy structured days require an explicit native-integer day
 *   1-30 (numeric strings, missing, and null are invalid), a non-blank
 *   title of at most 160 characters, a nullable description of at most
 *   2000 characters, and an optional stops list (omitted defaults to [],
 *   explicit null is invalid, at most 20 entries with non-blank titles of
 *   at most 160 characters, nullable descriptions of at most 2000
 *   characters, and nullable native-integer durations 1-1440).
 * - The whole itinerary is a JSON list of at most 30 entries. Associative,
 *   scalar, mixed, or partially invalid payloads are never coerced,
 *   dropped piecemeal, or truncated: the whole record normalizes to [] and
 *   is reported invalid. Repeated valid day numbers preserve order.
 * - Unknown extra object fields canonicalize down to the accepted keys
 *   without fabricated content.
 * - Malformed or empty input normalizes to []. An empty legacy record is a
 *   valid empty source; malformed records are invalid. The `valid` flag
 *   keeps that distinction truthful for caller counters.
 *
 * @return array{valid: bool, itinerary: array<int, array<string, mixed>>}
 */
final class LegacyTourItineraryNormalizer
{
    private const MAX_DAYS = 30;

    private const MAX_TITLE = 160;

    /**
     * Normalize one raw legacy payload.
     *
     * @return array{valid: bool, itinerary: array<int, array<string, mixed>>}
     */
    public function normalize(mixed $legacy): array
    {
        if ($legacy === null) {
            return ['valid' => true, 'itinerary' => []];
        }

        if (! is_array($legacy) || ! array_is_list($legacy)) {
            return ['valid' => false, 'itinerary' => []];
        }

        if (count($legacy) > self::MAX_DAYS) {
            return ['valid' => false, 'itinerary' => []];
        }

        if ($legacy === []) {
            return ['valid' => true, 'itinerary' => []];
        }

        $days = $legacy;

        $allStrings = true;
        foreach ($days as $day) {
            if (! is_string($day)) {
                $allStrings = false;

                break;
            }
        }

        if ($allStrings) {
            return $this->normalizePositional($days);
        }

        $allArrays = true;
        foreach ($days as $day) {
            if (! is_array($day)) {
                $allArrays = false;

                break;
            }
        }

        if (! $allArrays) {
            return ['valid' => false, 'itinerary' => []];
        }

        return $this->normalizeStructured($days);
    }

    /**
     * @param  array<int, string>  $days
     * @return array{valid: bool, itinerary: array<int, array<string, mixed>>}
     */
    private function normalizePositional(array $days): array
    {
        $itinerary = [];
        foreach ($days as $index => $title) {
            if (preg_match('/\S/u', $title) !== 1 || mb_strlen($title) > self::MAX_TITLE) {
                return ['valid' => false, 'itinerary' => []];
            }

            $itinerary[] = [
                'day' => $index + 1,
                'title' => $title,
                'description' => null,
                'stops' => [],
            ];
        }

        return ['valid' => true, 'itinerary' => $itinerary];
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     * @return array{valid: bool, itinerary: array<int, array<string, mixed>>}
     */
    private function normalizeStructured(array $days): array
    {
        // Stops shape is checked before TourContentRules normalization because
        // that layer defaults omitted stops to [] and reindexes lists: an
        // explicit null or an associative stops object must stay invalid
        // instead of being coerced into a valid shape.
        foreach ($days as $day) {
            if (array_key_exists('stops', $day)) {
                if (! is_array($day['stops']) || ! array_is_list($day['stops'])) {
                    return ['valid' => false, 'itinerary' => []];
                }
            }
        }

        // Absent optional stops default to []; every other value passes
        // through for validation to judge.
        $normalized = TourContentRules::normalizeItinerary($days);

        $validator = Validator::make(
            ['itinerary' => $normalized],
            TourContentRules::itineraryRules('itinerary')
        );

        if ($validator->fails()) {
            return ['valid' => false, 'itinerary' => []];
        }

        // Laravel's integer rule accepts digit strings for compatibility;
        // persisted JSON integers must be native, so digit strings (and any
        // other non-integer) are rejected here instead of being cast.
        foreach (array_values($normalized) as $day) {
            if (! is_array($day) || ! array_key_exists('day', $day) || ! is_int($day['day'])) {
                return ['valid' => false, 'itinerary' => []];
            }

            foreach (array_values($day['stops'] ?? []) as $stop) {
                if (! is_array($stop)) {
                    return ['valid' => false, 'itinerary' => []];
                }

                if (
                    array_key_exists('duration_minutes', $stop)
                    && $stop['duration_minutes'] !== null
                    && ! is_int($stop['duration_minutes'])
                ) {
                    return ['valid' => false, 'itinerary' => []];
                }
            }
        }

        // Canonical fixed key order with null defaults; unknown extra object
        // fields are left behind without fabricating content. Bounds and
        // types already passed validation above.
        $itinerary = [];
        foreach (array_values($normalized) as $day) {
            $stops = [];
            foreach (array_values($day['stops'] ?? []) as $stop) {
                $stops[] = [
                    'title' => $stop['title'],
                    'description' => $stop['description'] ?? null,
                    'duration_minutes' => $stop['duration_minutes'] ?? null,
                ];
            }

            $itinerary[] = [
                'day' => $day['day'],
                'title' => $day['title'],
                'description' => $day['description'] ?? null,
                'stops' => $stops,
            ];
        }

        return ['valid' => true, 'itinerary' => $itinerary];
    }
}
