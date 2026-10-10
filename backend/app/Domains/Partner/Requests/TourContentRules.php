<?php

namespace App\Domains\Partner\Requests;

/**
 * Reusable English input boundary for partner tour content (Spec 019 T008).
 *
 * Normalization contract: explicit nested `translations.en` keys win for
 * that field; top-level shorthand fills absent nested keys; omitted keys
 * preserve existing values (stay absent from the patch); explicit null
 * clears nullable text; `[]` clears lists. Title is a non-null string
 * whenever supplied. Every authored non-English locale and every
 * platform-owned revision/readiness key is rejected with its precise
 * offending path, including present-but-null/empty values. Opaque draft
 * snapshot payloads keep their own boundary and are validated only on
 * materialization (US1).
 */
class TourContentRules
{
    public const SOURCE_FIELDS = [
        'title',
        'description',
        'highlights',
        'inclusions',
        'exclusions',
        'meeting_point',
        'cancellation_policy',
        'itinerary',
        'important_information',
    ];

    /**
     * Platform-owned revision/readiness keys partners must never author,
     * at top level or nested inside any translations entry.
     */
    public const PLATFORM_OWNED_KEYS = [
        'source_hash',
        'translated_hash',
        'translation_status',
        'translation_statuses',
        'readiness',
        'last_error_code',
    ];

    /**
     * Provider/job/error state fields partners must never author. Checked
     * at top level alongside the revision keys; unexpected keys nested
     * inside `translations.en` are rejected through the source-field
     * allowlist instead.
     */
    public const PROVIDER_STATE_KEYS = [
        'provider',
        'provider_body',
        'provider_error',
        'provider_response',
        'job_id',
        'job_state',
        'generation_status',
    ];

    /**
     * Merge shorthand and nested English input into one canonical patch.
     *
     * Only provided fields appear in the result; omitted keys stay absent
     * so services can preserve existing values. Explicit null and `[]`
     * are retained with their distinct clearing meanings.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeEnglishPatch(array $input): array
    {
        $nested = $input['translations']['en'] ?? [];
        if (! is_array($nested)) {
            $nested = [];
        }

        $patch = [];
        foreach (self::SOURCE_FIELDS as $field) {
            if (array_key_exists($field, $nested)) {
                $patch[$field] = $nested[$field];
            } elseif (array_key_exists($field, $input)) {
                $patch[$field] = $input[$field];
            }
        }

        return $patch;
    }

    /**
     * Normalize an authored itinerary for validation: preserve array order
     * (no day-number uniqueness is invented, repeated valid days stay
     * valid), reindex day/stop lists canonically, default absent optional
     * stops to `[]`, and pass every other value through untouched so
     * validation reports exact field errors for malformed input instead of
     * this layer silently clearing it.
     */
    public static function normalizeItinerary(mixed $value): mixed
    {
        if ($value === null || ! is_array($value)) {
            return $value;
        }

        $days = [];
        foreach (array_values($value) as $day) {
            if (is_array($day)) {
                if (! array_key_exists('stops', $day)) {
                    $day['stops'] = [];
                } elseif (is_array($day['stops'])) {
                    $day['stops'] = array_values($day['stops']);
                }
            }
            $days[] = $day;
        }

        return $days;
    }

    /**
     * Shared source-field rules for create ($isCreate) and update. Title is
     * a non-null string whenever supplied; description is a nullable draft
     * string with the existing top-level create minimum preserved. Source
     * collections are canonical JSON lists (`list`): authored associative
     * objects fail at their exact path while `[]` stays a valid clear.
     *
     * @return array<string, mixed>
     */
    public static function sourceRules(bool $isCreate): array
    {
        $title = $isCreate ? 'required|string|max:120' : 'sometimes|string|max:120';
        $description = $isCreate
            ? 'required|string|min:100|max:5000'
            : 'sometimes|nullable|string|max:5000';

        return [
            'title' => $title,
            'description' => $description,
            'translations' => 'sometimes|nullable|array',
            'translations.en' => 'sometimes|nullable|array',
            'translations.en.title' => 'sometimes|string|max:120',
            'translations.en.description' => 'sometimes|nullable|string|max:5000',
            'translations.en.highlights' => 'sometimes|nullable|list|max:30',
            'translations.en.highlights.*' => 'string|max:500',
            'translations.en.inclusions' => 'sometimes|nullable|list|max:30',
            'translations.en.inclusions.*' => 'string|max:500',
            'translations.en.exclusions' => 'sometimes|nullable|list|max:30',
            'translations.en.exclusions.*' => 'string|max:500',
            'translations.en.meeting_point' => 'sometimes|nullable|string|max:500',
            'translations.en.cancellation_policy' => 'sometimes|nullable|string|max:2000',
            'translations.en.important_information' => 'sometimes|nullable|list|max:30',
            'translations.en.important_information.*' => 'string|max:500',
            'highlights' => 'sometimes|nullable|list|max:30',
            'highlights.*' => 'string|max:500',
            'inclusions' => 'sometimes|nullable|list|max:30',
            'inclusions.*' => 'string|max:500',
            'exclusions' => 'sometimes|nullable|list|max:30',
            'exclusions.*' => 'string|max:500',
            'important_information' => 'sometimes|nullable|list|max:30',
            'important_information.*' => 'string|max:500',
            'meeting_point' => 'sometimes|nullable|string|max:500',
            'cancellation_policy' => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * Itinerary rules rooted at the given array path (shorthand
     * `itinerary` or nested `translations.en.itinerary`). Days and stops
     * are canonical JSON lists: authored associative objects fail at their
     * exact path. Day numbers are required integers 1-30; titles are
     * required nonblank strings; descriptions are nullable strings; stops
     * is an ordered list with at most 20 entries (absent defaults to `[]`,
     * explicit null is rejected); durations are nullable integers 1-1440.
     * Empty itineraries and zero-stop days (`[]`) are valid.
     *
     * @return array<string, mixed>
     */
    public static function itineraryRules(string $prefix = 'itinerary'): array
    {
        return [
            $prefix => 'sometimes|nullable|list|max:30',
            "{$prefix}.*.day" => 'required|integer|min:1|max:30',
            "{$prefix}.*.title" => 'required|string|max:160|regex:/.*\\S.*/',
            "{$prefix}.*.description" => 'sometimes|nullable|string|max:2000',
            "{$prefix}.*.stops" => 'sometimes|list|max:20',
            "{$prefix}.*.stops.*.title" => 'required|string|max:160|regex:/.*\\S.*/',
            "{$prefix}.*.stops.*.description" => 'sometimes|nullable|string|max:2000',
            "{$prefix}.*.stops.*.duration_minutes" => 'sometimes|nullable|integer|min:1|max:1440',
        ];
    }

    /**
     * Cast validated numeric-string day and duration values to JSON integers.
     *
     * Laravel's `integer` rule accepts digit strings for compatibility; this
     * runs only AFTER validation, so fractional inputs were already rejected
     * and are never truncated into valid integers here. Nulls and stored
     * types are preserved; non-array input passes through untouched.
     */
    public static function castItineraryInts(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(static function (mixed $day): mixed {
            if (! is_array($day)) {
                return $day;
            }
            if (array_key_exists('day', $day) && is_numeric($day['day'])) {
                $day['day'] = (int) $day['day'];
            }
            if (array_key_exists('stops', $day) && is_array($day['stops'])) {
                $day['stops'] = array_map(static function (mixed $stop): mixed {
                    if (! is_array($stop)) {
                        return $stop;
                    }
                    if (
                        array_key_exists('duration_minutes', $stop)
                        && $stop['duration_minutes'] !== null
                        && is_numeric($stop['duration_minutes'])
                    ) {
                        $stop['duration_minutes'] = (int) $stop['duration_minutes'];
                    }

                    return $stop;
                }, $day['stops']);
            }

            return $day;
        }, $value);
    }

    /**
     * Scan raw input for every forbidden present key and return its precise
     * offending path: any authored translations locale other than `en`
     * (whatever its value, including null/empty); any key inside
     * `translations.en` outside the authored source-field allowlist; and
     * any platform-owned revision/readiness or provider/job/error state
     * key at top level. Presence is key existence, so
     * present-but-null/empty values are still reported. Valid
     * lifecycle/pricing/availability keys are untouched. Deeper unknown
     * keys (inside days/stops) never reach writes because only validated
     * data is applied.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string> offending path => message
     */
    public static function forbiddenPresentPaths(array $input): array
    {
        $paths = [];

        foreach (self::PLATFORM_OWNED_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $paths[$key] = 'Revision state is platform-owned.';
            }
        }
        foreach (self::PROVIDER_STATE_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $paths[$key] = 'Provider state is platform-owned.';
            }
        }

        $translations = $input['translations'] ?? null;
        if (is_array($translations)) {
            foreach ($translations as $locale => $entry) {
                if ($locale === 'en') {
                    if (is_array($entry)) {
                        foreach ($entry as $key => $value) {
                            if (in_array($key, self::PLATFORM_OWNED_KEYS, true)) {
                                $paths["translations.en.{$key}"] = 'Revision state is platform-owned.';
                            } elseif (in_array($key, self::PROVIDER_STATE_KEYS, true)) {
                                $paths["translations.en.{$key}"] = 'Provider state is platform-owned.';
                            } elseif (! in_array($key, self::SOURCE_FIELDS, true)) {
                                $paths["translations.en.{$key}"] = 'Unsupported English source field.';
                            }
                        }
                    }

                    continue;
                }
                $paths["translations.{$locale}"] = 'Partners may edit English source content only.';
            }
        }

        return $paths;
    }
}
