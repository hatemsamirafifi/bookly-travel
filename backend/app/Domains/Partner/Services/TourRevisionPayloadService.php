<?php

namespace App\Domains\Partner\Services;

use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Models\TourMedia;
use App\Domains\Partner\Requests\TourContentRules;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tour-content snapshot projection for EGM-003 (ADR 0024, design v3 §3).
 *
 * The stored snapshot has four tiers and no other content:
 *  - `source`: the nine canonical authored fields of the fixed source locale
 *  - `presentation`: category_id, slug, location, location_slug,
 *    duration_minutes, duration_label, group_size_min, group_size_max,
 *    difficulty_level, guide_languages, cover_image_url
 *  - `options`: active option IDs in order with authored title/pickup text
 *  - `media`: the faithful stored/public media projection
 *
 * It contains no derivative text, currency, prices, stock, partner ID,
 * lifecycle or provider state. Legacy baselines use the faithful projection
 * (no normalization, no invented approved content); candidate patches
 * normalize and validate only changed fields against the existing bounds and
 * preserve omitted legacy fields.
 *
 * Materialization never calls `TourService::syncOptions()`: that method also
 * changes price/capacity/guide codes/schedule/status/order. The option
 * materializer is text-only.
 */
class TourRevisionPayloadService
{
    public const SOURCE_FIELDS = TourContentRules::SOURCE_FIELDS;

    public const PRESENTATION_FIELDS = [
        'category_id',
        'slug',
        'location',
        'location_slug',
        'duration_minutes',
        'duration_label',
        'group_size_min',
        'group_size_max',
        'difficulty_level',
        'guide_languages',
        'cover_image_url',
    ];

    public const MAX_FIELD_REFS = 30;

    public const MAX_FIELD_REF_NOTE = 500;

    public function __construct(private readonly TourTranslationService $translations) {}

    /**
     * The faithful snapshot of what is stored right now. Every legacy quirk
     * (missing rows, non-URL covers, unnormalized itineraries) is preserved
     * byte-for-byte so adoption never invents approved content.
     *
     * @return array<string, mixed>
     */
    public function captureSnapshot(Tour $tour): array
    {
        return [
            'source' => $this->captureSource($tour),
            'presentation' => $this->capturePresentation($tour),
            'options' => $this->captureOptions($tour),
            'media' => $this->captureMedia($tour),
        ];
    }

    /**
     * The canonical source identity hash for a tour, using the existing
     * source-hash algorithm/serialization unchanged. Computed against the
     * stored rows, so option texts participate exactly like `planSourceDispatches`.
     */
    public function contentHash(Tour $tour): ?string
    {
        $source = $tour->translations()->where('locale', $tour->sourceLocale())->first();

        if ($source === null) {
            return null;
        }

        return $this->translations->sourceHash($source, $tour);
    }

    /** The canonical content hash of the whole stored snapshot. */
    public function hashPayload(array $payload): string
    {
        // Array order participates in identity: the stored tiers are emitted in
        // fixed key order and the JSON never re-sorts, so a reorder hashes
        // differently (design v3 §3).
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The canonical source identity hash of a stored snapshot, reproducing the
     * existing source-hash algorithm and serialization exactly (design v3 §7:
     * "Hash source text/options with the existing algorithm unchanged"). A
     * snapshot whose source and option text equal the live rows hashes the same
     * as `TourTranslationService::sourceHash()`; a changed payload hashes
     * differently. Tours with no options keep the option-less legacy byte set.
     */
    public function canonicalHashOfPayload(array $payload): string
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $rawOptions = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        $optionsList = [];
        foreach ($rawOptions as $key => $opt) {
            if (! is_array($opt)) {
                continue;
            }
            $optionsList[] = [
                'id' => $opt['id'] ?? $key,
                'title' => $opt['title'] ?? null,
                'pickup_instructions' => $opt['pickup_instructions'] ?? null,
            ];
        }

        return $this->translations->sourceHashFromSnapshot($source, $optionsList);
    }

    /**
     * Merge a bounded patch into a base snapshot, normalizing and validating
     * only the supplied (changed) fields. Omitted keys keep the stored legacy
     * value; the result is stored BEFORE hashing so the hash covers the
     * normalized result.
     *
     * @param  array<string, mixed>  $patch  input tiers: source/presentation/options/media
     * @param  array<string, mixed>|null  $base  the snapshot being replaced
     * @param  bool  $isBaseline  baselines stay faithful and validate nothing
     * @return array<string, mixed> the complete normalized snapshot
     */
    public function mergePatch(array $patch, ?array $base, Tour $tour, bool $isBaseline = false): array
    {
        $base ??= $this->captureSnapshot($tour);
        if ($isBaseline) {
            return $base;
        }
        $source = $patch['source'] ?? [];
        $presentation = $patch['presentation'] ?? [];
        $options = $patch['options'] ?? [];
        $media = $patch['media'] ?? null;

        if (! is_array($source) || ! is_array($presentation) || ! is_array($options)) {
            throw TourRevisionException::invalid(TourRevisionException::REVISION_CONTENT_INVALID, [
                'payload' => 'A revision patch supplies source, presentation and options objects.',
            ]);
        }

        $merged = $base;

        if ($source !== []) {
            $merged['source'] = $this->normalizeSourcePatch($source, $base['source'] ?? []);
        }

        if ($presentation !== []) {
            $merged['presentation'] = $this->normalizePresentationPatch($presentation, $base['presentation'] ?? [], $tour);
        }

        if (array_key_exists('options', $patch) && is_array($patch['options'])) {
            $merged['options'] = $this->normalizeOptionsPatch($options, $base['options'] ?? [], $tour);
        }

        if (is_array($media)) {
            $merged['media'] = $this->normalizeMediaPatch($media, $base['media'] ?? [], $isBaseline);
            $merged['presentation']['cover_image_url'] = null;
            foreach ($merged['media'] as $image) {
                if ($image['is_cover']) {
                    $merged['presentation']['cover_image_url'] = $image['url'];
                    break;
                }
            }
        }

        return $merged;
    }

    /**
     * Validate required source fields and current option structure at submission
     * and approval. Omitted optional legacy values stay faithful; changed
     * optional fields were validated before the immutable payload was stored.
     *
     * @return array<string, string> offending path => message
     */
    public function validateFullSnapshot(array $payload, ?Tour $tour = null): array
    {
        $paths = [];

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];

        try {
            Validator::make($source, [
                'title' => ['required', 'string', 'max:120'],
                'description' => ['required', 'string', 'max:5000'],
            ])->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $path => $messages) {
                foreach ((array) $messages as $message) {
                    $paths["source.{$path}"] = $message;
                }
            }
        }

        if (blank($source['title'] ?? null)) {
            $paths['source.title'] ??= 'A published tour requires a source title.';
        }
        if (blank($source['description'] ?? null)) {
            $paths['source.description'] ??= 'A published tour requires a source description.';
        }

        if ($tour !== null) {
            $this->assertCurrentOptionStructure($payload['options'] ?? [], $tour);
        }

        return $paths;
    }

    /** Require the exact current active option set/order under the caller's Tour lock. */
    public function assertCurrentOptionStructure(array $options, Tour $tour): void
    {
        $current = TourOption::query()->where('tour_id', $tour->id)
            ->where('status', TourOption::STATUS_ACTIVE)->orderBy('sort_order')->orderBy('id')
            ->pluck('id')->map(fn ($id): string => (string) $id)->all();
        if (array_map('strval', array_keys($options)) !== $current) {
            throw TourRevisionException::invalid(TourRevisionException::OPTION_STRUCTURE_NOT_SUPPORTED,
                ['options' => 'Revision option IDs must match the current active options in order.']);
        }
    }

    /**
     * The stored snapshot of one tour's authored source tier. A missing source
     * row is represented as canonical-schema nulls (the canonical schema fields
     * are always defined, even when their legacy value is null).
     *
     * @return array<string, mixed>
     */
    private function captureSource(Tour $tour): array
    {
        $source = $tour->translations()->where('locale', $tour->sourceLocale())->first();

        $payload = [];
        foreach (self::SOURCE_FIELDS as $field) {
            $payload[$field] = $source?->getAttribute($field);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePresentation(Tour $tour): array
    {
        $payload = [];
        foreach (self::PRESENTATION_FIELDS as $field) {
            $payload[$field] = $tour->getAttribute($field);
        }

        // Guide codes are operational facts stored normalized null-or-list.
        $payload['guide_languages'] = $tour->guide_languages;

        return $payload;
    }

    /**
     * Active option IDs in display order with their authored texts. Retired
     * option rows stay available to bookings and never count as authored text.
     *
     * @return array<int|string, array<string, mixed>>|array{}
     */
    private function captureOptions(Tour $tour): array
    {
        $payload = [];

        foreach ($tour->activeOptions() as $option) {
            $payload[$option->id] = [
                'title' => $option->title,
                'pickup_instructions' => $option->pickup_instructions,
            ];
        }

        return $payload;
    }

    /**
     * The faithful stored/public media projection: cover-typed rows keep their
     * type flag, gallery rows carry `is_cover: false`, and the legacy cover
     * column is never invented into an extra row.
     *
     * @return array<int, array<string, mixed>>
     */
    private function captureMedia(Tour $tour): array
    {
        $rows = $tour->relationLoaded('media') ? $tour->media : $tour->media()->orderBy('sort_order')->get();

        $media = [];
        foreach ($rows->values() as $index => $row) {
            $media[] = [
                'position' => $index,
                'url' => $row->getAttribute('url'),
                'alt_text' => $row->getAttribute('alt_text'),
                'is_cover' => $row->getAttribute('type') === TourMedia::TYPE_COVER,
            ];
        }

        return $media;
    }

    /**
     * Normalize a changed source patch against the base source tier, using the
     * shared sanitizer and the existing bounds. Title is never written as null.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function normalizeSourcePatch(array $source, array $base): array
    {
        $errors = [];
        $normalized = $base;

        foreach (self::SOURCE_FIELDS as $field) {
            if (! array_key_exists($field, $source)) {
                continue;
            }

            $value = $source[$field];

            if ($field === 'title') {
                $value = is_string($value) ? $value : null;
            }

            if ($field === 'description' && is_string($value)) {
                $value = app(HtmlSanitizerService::class)->sanitize($value);
            }

            $normalized[$field] = $value;
            $path = "source.{$field}";

            try {
                $rules = array_filter(
                    TourContentRules::sourceRules(false),
                    fn (string $key): bool => $key === $field || str_starts_with($key, "{$field}."),
                    ARRAY_FILTER_USE_KEY,
                );
                Validator::make([$field => $value], $rules)->validate();
            } catch (ValidationException $exception) {
                unset($normalized[$field]);
                foreach ($exception->errors() as $errorPath => $messages) {
                    foreach ((array) $messages as $message) {
                        $errors["source.{$errorPath}"] ??= $message;
                    }
                }
            }
        }

        if (! array_key_exists('itinerary', $source)) {
            return $this->finishPatchErrors($normalized, $errors);
        }

        $normalized['itinerary'] = TourContentRules::castItineraryInts(
            TourContentRules::normalizeItinerary($source['itinerary'])
        );

        try {
            Validator::make($normalized, TourContentRules::itineraryRules('itinerary'))->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $errorPath => $messages) {
                foreach ((array) $messages as $message) {
                    $errors['source.itinerary'] ??= $message;
                }
            }
        }

        return $this->finishPatchErrors($normalized, $errors);
    }

    /**
     * @template T of array
     *
     * @param  T  $normalized
     * @param  array<string, string>  $errors
     * @return T
     */
    private function finishPatchErrors(array $normalized, array $errors): array
    {
        if ($errors !== []) {
            throw TourRevisionException::invalid(TourRevisionException::REVISION_CONTENT_INVALID, $errors);
        }

        return $normalized;
    }

    /**
     * Normalize changed governed presentation fields against the base tier.
     * Only fields present in the patch validate; omitted legacy values are
     * preserved exactly.
     *
     * @param  array<string, mixed>  $presentation
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function normalizePresentationPatch(array $presentation, array $base, Tour $tour): array
    {
        $errors = [];
        $normalized = $base;

        $aliases = $this->presentationAliases($presentation);

        foreach (self::PRESENTATION_FIELDS as $field) {
            if (! array_key_exists($field, $aliases) || ! array_key_exists($field, $normalized)) {
                continue;
            }

            $value = $aliases[$field];

            if ($field === 'guide_languages') {
                $normalizedCodes = $this->normalizeGuideLanguages($value);

                // Empty spoken-language storage is one state: a null column and
                // an empty list both read as "no codes", so an explicit clear
                // round-trips against legacy null storage instead of falsely
                // reporting a governed change.
                $normalized[$field] = $normalizedCodes === [] ? null : $normalizedCodes;

                continue;
            }

            if ($field === 'slug') {
                $trimmed = is_string($value) ? trim($value) : $value;
                $aliases[$field] = $trimmed;
                $normalized[$field] = is_string($trimmed) ? $trimmed : null;

                continue;
            }

            $normalized[$field] = $value;
        }

        foreach (self::PRESENTATION_FIELDS as $field) {
            if (! array_key_exists($field, $aliases)) {
                continue;
            }

            $path = "presentation.{$field}";
            $rule = $this->presentationRule($field, $tour);

            if ($rule === null) {
                continue;
            }

            try {
                Validator::make([$field => $aliases[$field]], [$field => $rule])->validate();
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $errorPath => $messages) {
                    foreach ((array) $messages as $message) {
                        $errors[$path] ??= $message;
                    }
                }
            }
        }

        return $this->finishPatchErrors($normalized, $errors);
    }

    /**
     * Accept the existing request aliases for the governed tier (category slug
     * vs id, destination vs location, duration value/unit vs minutes) so owners
     * never re-map platform metadata by hand.
     *
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    public function presentationAliases(array $presentation): array
    {
        $normalized = $presentation;

        if (array_key_exists('category', $normalized) && ! array_key_exists('category_id', $normalized)) {
            $category = is_string($normalized['category'])
                ? Category::where('slug', $normalized['category'])->first()
                : null;

            if ($category === null) {
                throw TourRevisionException::invalid(TourRevisionException::REVISION_CONTENT_INVALID, [
                    'presentation.category_id' => 'The selected category is invalid.',
                ], 'Unknown category: ' . (is_string($normalized['category']) ? $normalized['category'] : ''));
            }

            $normalized['category_id'] = $category->id;
        }

        // The `destination` alias maps onto the stored location exactly like the
        // historical update contract; an explicit `location` always wins.
        if (array_key_exists('destination', $normalized) && ! array_key_exists('location', $normalized)) {
            $normalized['location'] = $normalized['destination'];
            unset($normalized['destination']);
        }

        if (array_key_exists('location', $normalized) && ! array_key_exists('location_slug', $normalized)) {
            $normalized['location_slug'] = Str::slug(is_string($normalized['location']) ? $normalized['location'] : '');
        }

        return $this->applyDurationAlias($presentation === [] ? $normalized : array_merge($normalized, []));
    }

    /**
     * Compose the duration presentation tier from the existing value/unit
     * request alias when the patch supplies it without an explicit minute value.
     * A supplied `duration_label` is never overwritten by a derived alias.
     *
     * @param  array<string, mixed>  $presentation
     */
    public function applyDurationAlias(array $presentation): array
    {
        if (array_key_exists('duration_minutes', $presentation)
            || (! array_key_exists('duration_value', $presentation) && ! array_key_exists('duration_unit', $presentation))) {
            return $presentation;
        }

        $value = $presentation['duration_value'] ?? null;
        $unit = $presentation['duration_unit'] ?? 'hour';

        if (! is_numeric($value)) {
            return $presentation;
        }

        $minutes = $unit === 'day' ? (int) $value * 1440 : (int) $value * 60;
        unset($presentation['duration_value'], $presentation['duration_unit']);
        $presentation['duration_minutes'] = $minutes;

        if (! array_key_exists('duration_label', $presentation)) {
            $presentation['duration_label'] = $value . ' ' . $unit . ($value > 1 ? 's' : '');
        }

        return $presentation;
    }

    private function presentationRule(string $field, Tour $tour): ?string
    {
        return match ($field) {
            'category_id' => 'required|integer|exists:categories,id',
            'slug' => "required|string|max:160|unique:tours,slug,{$tour->id}",
            'location' => 'required|string|max:150',
            'location_slug' => 'sometimes|nullable|string|max:150',
            'duration_minutes' => 'required|integer|min:1|max:10080',
            'duration_label' => 'required|string|max:80',
            'group_size_min' => 'required|integer|min:1|max:1000',
            'group_size_max' => 'required|integer|min:1|max:1000',
            'difficulty_level' => 'sometimes|nullable|in:easy,moderate,challenging',
            'guide_languages' => 'sometimes|nullable|array|max:20',
            'cover_image_url' => 'sometimes|nullable|url|max:2048|regex:/^https?:\/\//i',
            default => null,
        };
    }

    /**
     * Normalize a changed option-text patch. Only existing option IDs of this
     * tour are accepted; identity, order and commercial values stay stored.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function normalizeOptionsPatch(array $options, array $base, Tour $tour): array
    {
        $errors = [];
        $normalized = $base;

        $validIds = TourOption::query()
            ->where('tour_id', $tour->id)
            ->where('status', TourOption::STATUS_ACTIVE)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        if (array_map('strval', array_keys($options)) !== $validIds
            || array_map('strval', array_keys($base)) !== $validIds) {
            throw TourRevisionException::invalid(TourRevisionException::OPTION_STRUCTURE_NOT_SUPPORTED,
                ['options' => 'Revision option IDs must match the current active options in order.']);
        }

        foreach ($options as $optionId => $entry) {
            $path = "options.{$optionId}";

            if (! in_array((string) $optionId, $validIds, true)) {
                $errors[$path] ??= 'The selected option does not belong to this tour.';

                continue;
            }

            if (! is_array($entry)) {
                $errors[$path] ??= 'Options supply objects.';

                continue;
            }

            foreach (array_diff(array_keys($entry), ['title', 'pickup_instructions']) as $key) {
                $errors["{$path}.{$key}"] = 'Only authored option text may be revised.';
            }

            $previous = $normalized[$optionId] ?? ['title' => null, 'pickup_instructions' => null];

            foreach (['title', 'pickup_instructions'] as $field) {
                if (! array_key_exists($field, $entry)) {
                    continue;
                }

                $value = $entry[$field];

                if ($field === 'title') {
                    if (! is_string($value) || trim($value) === '') {
                        $errors["{$path}.{$field}"] ??= 'The option title must be nonblank text.';

                        continue;
                    }

                    if (mb_strlen($value) > 120) {
                        $errors["{$path}.{$field}"] ??= 'The option title must not exceed 120 characters.';

                        continue;
                    }
                }

                if ($value !== null && ! is_string($value)) {
                    $errors["{$path}.{$field}"] ??= 'The option text must be a string.';

                    continue;
                }

                if (is_string($value) && mb_strlen($value) > 500) {
                    $errors["{$path}.{$field}"] ??= 'The option text must not exceed 500 characters.';

                    continue;
                }

                $previous[$field] = $value;
            }

            $normalized[$optionId] = $previous;
        }

        return $this->finishPatchErrors($normalized, $errors);
    }

    /**
     * Normalize a media patch using the existing request shape
     * (`url`, `alt_text`, `is_cover`). Baselines keep the faithful stored
     * projection; candidate media re-projects in stored order with the
     * documented cover/gallery rules.
     *
     * @param  array<array-key, mixed>  $media
     * @param  list<array<string, mixed>>  $base
     * @return list<array<string, mixed>>
     */
    public function normalizeMediaPatch(array $media, array $base, bool $isBaseline): array
    {
        if ($isBaseline) {
            return $base;
        }

        try {
            Validator::make(['media' => $media], TourContentRules::mediaRules())->validate();
        } catch (ValidationException $exception) {
            throw TourRevisionException::invalid(TourRevisionException::REVISION_CONTENT_INVALID,
                array_map(fn (array $messages): string => $messages[0], $exception->errors()));
        }
        if (! array_is_list($media)) {
            throw TourRevisionException::invalid(TourRevisionException::REVISION_CONTENT_INVALID, ['media' => 'Media must be an ordered list.']);
        }

        $errors = [];
        $normalized = [];
        $priorAlt = [];
        foreach ($base as $image) {
            if (! array_key_exists($image['url'], $priorAlt)) {
                $priorAlt[$image['url']] = $image['alt_text'] ?? null;
            }
        }

        foreach ($media as $index => $image) {
            if (! is_array($image)) {
                $errors["media.{$index}"] ??= 'Media entries supply objects.';

                continue;
            }

            $url = $image['url'] ?? null;

            if (! is_string($url) || trim($url) === '') {
                $errors["media.{$index}.url"] ??= 'Media requires a URL.';

                continue;
            }

            if (mb_strlen($url) > 2048) {
                $errors["media.{$index}.url"] ??= 'The media URL must not exceed 2048 characters.';

                continue;
            }

            $alt = array_key_exists('alt_text', $image) ? $image['alt_text'] : ($priorAlt[$url] ?? null);

            if ($alt !== null && ! is_string($alt)) {
                $errors["media.{$index}.alt_text"] ??= 'The media alt text must be a string.';

                continue;
            }

            if (is_string($alt) && mb_strlen($alt) > 500) {
                $errors["media.{$index}.alt_text"] ??= 'The media alt text must not exceed 500 characters.';

                continue;
            }

            $isCover = array_key_exists('is_cover', $image)
                ? filter_var($image['is_cover'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : false;

            if ($isCover === null) {
                $errors["media.{$index}.is_cover"] ??= 'The media flag must be a boolean.';

                continue;
            }

            $normalized[] = [
                'position' => $index,
                'url' => $url,
                'alt_text' => $alt,
                'is_cover' => $isCover,
            ];
        }

        $normalized = $this->finishPatchErrors($normalized, $errors);
        $cover = null;
        foreach ($normalized as $index => $image) {
            if ($image['is_cover']) {
                $cover = $index;
                break;
            }
        }
        $cover ??= $normalized === [] ? null : 0;
        foreach ($normalized as $index => &$image) {
            $image['is_cover'] = $index === $cover;
        }
        unset($image);

        return $normalized;
    }

    /**
     * Resolve bounded field references (design v3 §5). References are objects
     * `{"path":"source.title","note":"optional"}`; paths use the stored tiers
     * and must exist on the captured context. 1–30 references, notes at most
     * 500 characters.
     *
     * @param  array<array-key, mixed>  $refs
     * @param  array<string, mixed>  $contextPayload
     * @return list<array{path: string, note?: string}>
     */
    public function resolveFieldReferences(array $refs, array $contextPayload): array
    {
        $errors = [];
        $resolved = [];

        if (! array_is_list($refs) || count($refs) > self::MAX_FIELD_REFS) {
            throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                'field_refs' => 'Field references must be a list of at most ' . self::MAX_FIELD_REFS . ' entries.',
            ]);
        }

        foreach ($refs as $index => $ref) {
            $path = "field_refs.{$index}";

            if (! is_array($ref)) {
                $errors[$path] ??= 'A field reference supplies an object with a path.';

                continue;
            }

            $extra = array_diff_key($ref, ['path' => true, 'note' => true]);

            if ($extra !== []) {
                $errors[$path] ??= 'Only path and note are allowed on a field reference.';

                continue;
            }

            $referencePath = is_string($ref['path'] ?? null) ? trim((string) $ref['path']) : null;

            if ($referencePath === null || $referencePath === '') {
                $errors[$path] ??= 'A field reference requires a nonempty path.';

                continue;
            }

            if (mb_strlen($referencePath) > 200) {
                $errors[$path] ??= 'The field reference path must not exceed 200 characters.';

                continue;
            }

            $note = $ref['note'] ?? null;

            if ($note !== null && (! is_string($note) || mb_strlen($note) > self::MAX_FIELD_REF_NOTE)) {
                $errors[$path] ??= 'The field reference note must not exceed ' . self::MAX_FIELD_REF_NOTE . ' characters.';

                continue;
            }

            if (! $this->referenceExists($referencePath, $contextPayload)) {
                $errors[$path] ??= 'The referenced field does not exist on this content.';

                continue;
            }

            $noteString = is_string($note) ? trim($note) : '';

            $resolved[] = ['path' => $referencePath] + ($noteString !== '' ? ['note' => $noteString] : []);
        }

        if ($errors !== []) {
            throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, $errors);
        }

        if ($resolved === []) {
            throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                'field_refs' => 'At least one field reference is required.',
            ]);
        }

        return $resolved;
    }

    /**
     * Whether a reference path names a defined slot of the captured context.
     * Canonical schema fields are defined even when their stored value is null;
     * option/media references must identify existing entries.
     */
    public function referenceExists(string $path, array $payload): bool
    {
        $segments = explode('.', $path);

        $tier = array_shift($segments);

        if (! in_array($tier, ['source', 'presentation', 'options', 'media'], true)) {
            return false;
        }

        $value = $payload[$tier] ?? null;

        // Tier roots (e.g. 'source', 'presentation') alone are not field references.
        if ($segments === []) {
            return false;
        }

        if ($tier === 'source') {
            $field = array_shift($segments);

            if (! in_array($field, self::SOURCE_FIELDS, true)) {
                return false;
            }

            // Top-level canonical source slot is defined even when stored value is null.
            if ($segments === []) {
                return true;
            }

            // Descendant path under a null or non-array parent does not resolve.
            $fieldValue = is_array($value) ? ($value[$field] ?? null) : null;

            if ($fieldValue === null || ! is_array($fieldValue)) {
                return false;
            }

            return $this->pathExists($fieldValue, $segments);
        }

        if ($tier === 'presentation') {
            $field = array_shift($segments);

            if (! in_array($field, self::PRESENTATION_FIELDS, true)) {
                return false;
            }

            // Top-level canonical presentation slot is defined even when stored value is null.
            if ($segments === []) {
                return true;
            }

            // Descendant path under a null or non-array parent does not resolve.
            $fieldValue = is_array($value) ? ($value[$field] ?? null) : null;

            if ($fieldValue === null || ! is_array($fieldValue)) {
                return false;
            }

            return $this->pathExists($fieldValue, $segments);
        }

        if ($tier === 'options') {
            $id = array_shift($segments);
            if (! is_array($value) || ! array_key_exists((string) $id, $value)) {
                return false;
            }

            return count($segments) === 1 && in_array($segments[0], ['title', 'pickup_instructions'], true);
        }

        $position = array_shift($segments);
        if (! preg_match('/^(0|[1-9][0-9]*)$/D', $position)) {
            return false;
        }

        $entry = is_array($value) ? ($value[(int) $position] ?? null) : null;

        return is_array($entry) && count($segments) === 1 && $segments[0] === 'alt_text';
    }

    /**
     * @param  list<string>  $segments
     */
    private function pathExists(mixed $value, array $segments): bool
    {
        $current = $value;

        foreach ($segments as $segment) {
            if (! is_array($current)) {
                return false;
            }

            if (! array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    /**
     * The flat changed-path set between two stored snapshots, expressed in the
     * documented reference grammar so the changed paths can be compared with
     * the declared correction fields.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $candidate
     * @return list<string>
     */
    public function changedPaths(array $base, array $candidate): array
    {
        $paths = [];

        foreach (\array_intersect_key($candidate, $base) as $tier => $tierValue) {
            foreach ($this->diffValue($base[$tier] ?? null, $tierValue, "{$tier}") as $path => $changed) {
                if ($changed) {
                    $paths[] = $path;
                }
            }
        }

        foreach (['source', 'presentation', 'options', 'media'] as $tier) {
            if (array_key_exists($tier, $candidate) && ! array_key_exists($tier, $base)) {
                $paths[] = $tier;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The normalized before/after diff of two stored snapshots, in the review
     * grammar (path => before/after at reference granularity).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $candidate
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function diff(array $base, array $candidate): array
    {
        $diff = [];

        foreach (['source', 'presentation', 'options', 'media'] as $tier) {
            if (! array_key_exists($tier, $candidate) && ! array_key_exists($tier, $base)) {
                continue;
            }

            $this->diffInto($diff, $base[$tier] ?? null, $candidate[$tier] ?? null, "{$tier}");
        }

        return $diff;
    }

    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $diff
     */
    private function diffInto(array &$diff, mixed $base, mixed $candidate, string $prefix): void
    {
        if ($base === $candidate) {
            return;
        }

        if (is_array($base) && is_array($candidate)) {
            $keys = array_keys($base + $candidate);

            foreach ($keys as $key) {
                $this->diffInto($diff, $base[$key] ?? null, $candidate[$key] ?? null, "{$prefix}.{$key}");
            }

            return;
        }

        $diff[$prefix] = ['before' => $base, 'after' => $candidate];
    }

    /**
     * @return \Generator<string, bool>
     */
    private function diffValue(mixed $base, mixed $candidate, string $prefix): \Generator
    {
        if ($base === $candidate) {
            yield $prefix => false;

            return;
        }

        if (is_array($base) && is_array($candidate)) {
            $keys = array_keys($base + $candidate);

            foreach ($keys as $key) {
                yield from $this->diffValue($base[$key] ?? null, $candidate[$key] ?? null, "{$prefix}.{$key}");
            }

            return;
        }

        yield $prefix => true;
    }

    /**
     * Materialize ONLY the governed text/presentation tiers of an approved
     * revision onto the live tour. Never touches prices, capacities, guide
     * codes of options, option status/order, lifecycle or provider state.
     */
    public function materialize(Tour $tour, array $payload): void
    {
        $this->materializeSource($tour, $payload['source'] ?? []);
        $this->materializePresentation($tour, $payload['presentation'] ?? []);
        $this->materializeMedia($tour, $payload['media'] ?? []);
        $this->materializeOptionText($tour, $payload['options'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    public function materializeSource(Tour $tour, array $source): void
    {
        $update = [];
        $storedSource = $tour->translations()->where('locale', $tour->sourceLocale())->first();

        foreach (self::SOURCE_FIELDS as $field) {
            if (! array_key_exists($field, $source) || ! $this->tierChanged($storedSource?->getAttribute($field), $source[$field])) {
                continue;
            }

            $update[$field] = $source[$field];
        }

        if ($update === []) {
            return;
        }

        $tour->translations()->updateOrCreate(
            ['locale' => $tour->sourceLocale()],
            $update
        );
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    public function materializePresentation(Tour $tour, array $presentation): void
    {
        $update = [];

        foreach (self::PRESENTATION_FIELDS as $field) {
            if (! array_key_exists($field, $presentation) || ! $this->tierChanged($tour->getAttribute($field), $presentation[$field])) {
                continue;
            }

            $update[$field] = $presentation[$field];
        }

        if ($update === []) {
            return;
        }

        $tour->update($update);
    }

    /**
     * @param  list<array<string, mixed>>  $media
     */
    public function materializeMedia(Tour $tour, array $media): void
    {
        if ($this->mediaUnchanged($tour, $media)) {
            // Unchanged legacy media is never rewritten (design v3 §3): an
            // unusual stored shape stays exactly as adopted.
            return;
        }

        $cover = null;

        foreach ($media as $image) {
            if (! empty($image['is_cover'])) {
                $cover = $image['url'];

                break;
            }
        }

        $tour->media()->delete();

        foreach ($media as $image) {
            $tour->media()->create([
                'type' => ! empty($image['is_cover']) ? TourMedia::TYPE_COVER : TourMedia::TYPE_GALLERY,
                'url' => $image['url'],
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => (int) ($image['position'] ?? 0),
            ]);
        }

        $tour->update(['cover_image_url' => $cover]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function materializeOptionText(Tour $tour, array $options): void
    {
        // Text-only materializer: never calls syncOptions, never changes price,
        // capacity, guide codes, schedule, status or order. Only options that
        // still belong to this tour are touched.
        $scopedIds = TourOption::query()
            ->where('tour_id', $tour->id)
            ->whereIn('id', array_map('intval', array_keys($options)))
            ->pluck('id');

        if ($scopedIds->isEmpty()) {
            return;
        }

        foreach ($scopedIds as $optionId) {
            $entry = $options[$optionId] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $option = TourOption::query()->whereKey($optionId)->first();

            if ($option === null) {
                continue;
            }

            $update = [];

            foreach (['title', 'pickup_instructions'] as $field) {
                if (array_key_exists($field, $entry) && $this->tierChanged($option->{$field}, $entry[$field])) {
                    $update[$field] = $entry[$field];
                }
            }

            if ($update === []) {
                continue;
            }

            $option->update($update);
        }
    }

    /**
     * The faithful projection of the stored media rows, used to compare against
     * a candidate media tier so unchanged legacy media is never rewritten.
     */
    private function mediaUnchanged(Tour $tour, array $media): bool
    {
        $stored = $this->captureMedia($tour);

        return $stored === $media;
    }

    private function tierChanged(mixed $stored, mixed $payload): bool
    {
        return $stored !== $payload;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeGuideLanguages(mixed $languages): ?array
    {
        if ($languages === null) {
            return null;
        }

        if (! is_array($languages)) {
            return null;
        }

        $normalized = [];

        foreach ($languages as $language) {
            $code = strtolower(trim((string) $language));

            if ($code === '' || in_array($code, $normalized, true)) {
                continue;
            }

            $normalized[] = $code;
        }

        return $normalized;
    }

    /** A stored option projection used by published PUT comparisons. */
    public function optionsProjected(Tour $tour): array
    {
        return $this->captureOptions($tour);
    }

    /** The stored projection of a tour's media, used for comparisons. */
    public function mediaProjected(Tour $tour): array
    {
        return $this->captureMedia($tour);
    }
}
