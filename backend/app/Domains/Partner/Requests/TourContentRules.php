<?php

namespace App\Domains\Partner\Requests;

/**
 * Reusable source-language input boundary for partner tour content (Spec 019
 * T008, extended for a configurable authored source locale).
 *
 * Normalization contract: explicit nested `translations.{sourceLocale}` keys
 * win for that field; top-level shorthand fills absent nested keys; omitted
 * keys preserve existing values (stay absent from the patch); explicit null
 * clears nullable text; `[]` clears lists. Title is a non-null string
 * whenever supplied. Every authored locale other than the tour's source
 * locale and every platform-owned revision/readiness key is rejected with
 * its precise offending path, including present-but-null/empty values.
 * Opaque draft snapshot payloads keep their own boundary and are validated
 * only on materialization (US1).
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
     * Merge shorthand and nested source-locale input into one canonical patch.
     *
     * Only provided fields appear in the result; omitted keys stay absent
     * so services can preserve existing values. Explicit null and `[]`
     * are retained with their distinct clearing meanings. The nested
     * `translations.{sourceLocale}` entry wins per field; top-level shorthand
     * fills the rest, so callers that only ever sent top-level English
     * content keep working regardless of the chosen source locale.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeSourcePatch(array $input, string $sourceLocale = 'en'): array
    {
        $nested = $input['translations'][$sourceLocale] ?? [];
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
     * English-source alias of normalizeSourcePatch() kept for existing callers.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeEnglishPatch(array $input): array
    {
        return self::normalizeSourcePatch($input, 'en');
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
     * Shared source-field rules for create ($isCreate) and update, rooted at
     * the authored source locale. Title is a non-null string whenever
     * supplied; description is a nullable draft string with the existing
     * top-level create minimum preserved. Source collections are canonical
     * JSON lists (`list`): authored associative objects fail at their exact
     * path while `[]` stays a valid clear.
     *
     * The default English source keeps every pre-source-locale caller on
     * `translations.en`, so old request shapes validate unchanged.
     *
     * @return array<string, mixed>
     */
    public static function sourceRules(bool $isCreate, string $sourceLocale = 'en'): array
    {
        $title = $isCreate ? 'required|string|max:120' : 'sometimes|string|max:120';
        $description = $isCreate
            ? 'required|string|min:100|max:5000'
            : 'sometimes|nullable|string|max:5000';

        $path = "translations.{$sourceLocale}";

        return [
            'title' => $title,
            'description' => $description,
            'translations' => 'sometimes|nullable|array',
            "{$path}" => 'sometimes|nullable|array',
            "{$path}.title" => 'sometimes|string|max:120',
            "{$path}.description" => 'sometimes|nullable|string|max:5000',
            "{$path}.highlights" => 'sometimes|nullable|list|max:30',
            "{$path}.highlights.*" => 'string|max:500',
            "{$path}.inclusions" => 'sometimes|nullable|list|max:30',
            "{$path}.inclusions.*" => 'string|max:500',
            "{$path}.exclusions" => 'sometimes|nullable|list|max:30',
            "{$path}.exclusions.*" => 'string|max:500',
            "{$path}.meeting_point" => 'sometimes|nullable|string|max:500',
            "{$path}.cancellation_policy" => 'sometimes|nullable|string|max:2000',
            "{$path}.important_information" => 'sometimes|nullable|list|max:30',
            "{$path}.important_information.*" => 'string|max:500',
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
     * Shared spoken guide-language boundary (Spec 019 US4 T050).
     *
     * Both the canonical `guide_languages` and the compatibility `languages`
     * alias accept the same shape: nullable code list, at most 20 entries,
     * stable case-insensitive 2/3-letter codes with supported subtags.
     * Canonical presence wins (even [] or null); omitted canonical accepts
     * the alias. Normalization (lowercase, distinct preserving order) lives
     * in the domain service; `distinct` here rejects exact authored
     * duplicates while normalized duplicates dedupe on write.
     *
     * @return array<string, mixed>
     */
    public static function guideLanguageRules(): array
    {
        return [
            'guide_languages' => 'sometimes|nullable|array|max:20',
            'guide_languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'languages' => 'sometimes|nullable|array|max:20',
            'languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
        ];
    }

    /**
     * Shared ordered media boundary (Spec 019 US4 T050).
     *
     * At most 20 ordered objects with distinct authored valid HTTP(S) URLs
     * (max 2048), optional nullable alt_text (max 500 Unicode characters)
     * and boolean is_cover. Source and media validate atomically at the
     * Request boundary so no partial mutation reaches the service.
     *
     * @return array<string, mixed>
     */
    public static function mediaRules(): array
    {
        return [
            'media' => 'sometimes|array|max:20',
            'media.*.url' => 'required|url|max:2048|distinct|regex:/^https?:\/\//i',
            'media.*.is_cover' => 'sometimes|boolean',
            'media.*.alt_text' => 'sometimes|nullable|string|max:500',
        ];
    }

    /**
     * Shared purchasable-option boundary for partner create/update (bounded
     * list of departure variants authored in the tour's source language).
     *
     * Field bounds: title required nonblank (max 120 like the tour title);
     * pickup required-nullable text (max 500 like meeting point); price in
     * cents min 1 (a zero-price option is not sellable); capacity per
     * departure 1-1000 or null (inherits the tour capacity); start time
     * H:mm[:ss]; weekdays 0-6 distinct integers (Carbon dayOfWeek) or null
     * (every operating weekday). `id` is optional: on update it scopes the
     * write to an existing option of THIS tour, on create options are new
     * rows and any authored id is rejected. `source_locale` and every other
     * partner-forbidden key is handled by forbiddenPresentPaths().
     *
     * @return array<string, mixed>
     */
    public static function optionRules(): array
    {
        return [
            'options' => 'sometimes|list|max:20',
            'options.*' => 'array',
            'options.*.id' => 'sometimes|integer|min:1',
            'options.*.title' => 'required|string|max:120|regex:/.*\\S.*/',
            'options.*.pickup_instructions' => 'sometimes|nullable|string|max:500',
            'options.*.guide_languages' => 'required|list|max:20',
            'options.*.guide_languages.*' => 'string|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'options.*.price_amount' => 'required|integer|min:1|max:10000000',
            'options.*.group_size_max' => 'sometimes|nullable|integer|min:1|max:1000',
            'options.*.start_time' => ['sometimes', 'nullable', 'regex:/^([01]?\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/i'],
            'options.*.weekdays' => 'sometimes|nullable|list|max:7',
            'options.*.weekdays.*' => 'integer|between:0,6',
        ];
    }

    /**
     * The id field must be absent on create (every option is a new row) and,
     * on update, must reference an option of the tour being edited. This pair
     * of checks runs inside the Request `after` hook because both need the
     * request context (isCreate) and — for update — a scoped storage lookup.
     *
     * @param  array<int, mixed>  $options  raw options list from the input
     * @return array<string, string> offending path => message
     */
    public static function optionIdPaths(array $options, bool $isCreate, ?callable $scopedExists = null): array
    {
        $paths = [];
        $seen = [];

        foreach (array_values($options) as $index => $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;

            if ($isCreate) {
                if ($id !== null) {
                    $paths["options.{$index}.id"] = 'Options cannot specify ids when creating a tour.';
                }

                continue;
            }

            if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                continue;
            }

            $id = (int) $id;

            if (isset($seen[$id])) {
                $paths["options.{$index}.id"] = 'Options contain duplicate ids.';
            } else {
                $seen[$id] = true;
            }

            if ($scopedExists !== null && ! $scopedExists($id)) {
                $paths["options.{$index}.id"] = 'The selected option does not belong to this tour.';
            }
        }

        return $paths;
    }

    /**
     * Normalize raw option input into storage-ready rows, preserving authored
     * order (sort_order). Guide codes are lowercased, trimmed and deduped in
     * first-seen order; weekdays are cast to integers (an empty list means
     * "every operating weekday", stored as null like an omitted list);
     * start_time is normalized to H:i:s. Unknown keys never reach this
     * projection: writes assemble the storage payload field by field.
     *
     * Create passes `stripIds: true` because every option on a brand-new
     * tour is a new row and any authored id would otherwise collide with the
     * scoped-lookup guard in the service.
     *
     * @param  array<int, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeOptions(array $options, bool $stripIds = false): array
    {
        $normalized = [];

        foreach (array_values($options) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $weekdays = $item['weekdays'] ?? null;
            $weekdays = is_array($weekdays) && $weekdays !== []
                ? array_values(array_unique(array_map('intval', $weekdays)))
                : null;

            $startTime = $item['start_time'] ?? null;
            if (is_string($startTime) && $startTime !== '') {
                $parts = array_pad(explode(':', $startTime), 3, '00');
                $startTime = sprintf('%02d:%02d:%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2]);
            } else {
                $startTime = null;
            }

            $pickup = $item['pickup_instructions'] ?? null;

            $normalized[] = [
                'id' => isset($item['id']) && ! $stripIds ? (int) $item['id'] : null,
                'title' => trim((string) ($item['title'] ?? '')),
                'pickup_instructions' => is_string($pickup) && trim($pickup) === '' ? null : $pickup,
                'guide_languages' => array_values(array_unique(array_map(
                    static fn ($code): string => strtolower(trim((string) $code)),
                    array_values(is_array($item['guide_languages'] ?? null) ? $item['guide_languages'] : []),
                ))),
                'price_amount' => (int) ($item['price_amount'] ?? 0),
                'group_size_max' => isset($item['group_size_max']) ? (int) $item['group_size_max'] : null,
                'start_time' => $startTime,
                'weekdays' => $weekdays,
            ];
        }

        return $normalized;
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
     * offending path: any authored translations locale other than the tour's
     * authored source (whatever its value, including null/empty); any key
     * inside the source locale entry outside the authored source-field
     * allowlist; any platform-owned revision/readiness or provider/job/error
     * state key at top level; and, when the source locale is immutable,
     * `source_locale` itself. Presence is key existence, so
     * present-but-null/empty values are still reported. Valid
     * lifecycle/pricing/availability keys are untouched. Deeper unknown
     * keys (inside days/stops) never reach writes because only validated
     * data is applied.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string> offending path => message
     */
    public static function forbiddenPresentPaths(array $input, string $sourceLocale = 'en', bool $allowSourceLocale = true): array
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

        if (! $allowSourceLocale && array_key_exists('source_locale', $input)) {
            $paths['source_locale'] = 'Source language cannot be changed after creation.';
        }

        $translations = $input['translations'] ?? null;
        if (is_array($translations)) {
            foreach ($translations as $locale => $entry) {
                if ($locale === $sourceLocale) {
                    if (is_array($entry)) {
                        foreach ($entry as $key => $value) {
                            if (in_array($key, self::PLATFORM_OWNED_KEYS, true)) {
                                $paths["translations.{$locale}.{$key}"] = 'Revision state is platform-owned.';
                            } elseif (in_array($key, self::PROVIDER_STATE_KEYS, true)) {
                                $paths["translations.{$locale}.{$key}"] = 'Provider state is platform-owned.';
                            } elseif (! in_array($key, self::SOURCE_FIELDS, true)) {
                                $paths["translations.{$locale}.{$key}"] = 'Unsupported source field.';
                            }
                        }
                    }

                    continue;
                }
                $paths["translations.{$locale}"] = 'Partners may edit source-language content only.';
            }
        }

        return $paths;
    }
}
