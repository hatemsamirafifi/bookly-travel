<?php

namespace App\Domains\Partner\Requests;

use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Owned tour revision source (design v3 §6 `POST tours/{id}/revisions`).
 *
 * The source patch reuses the existing authored `translations.{fixed_source_locale}`
 * / top-level shorthand normalization, and the presentation tier accepts the
 * documented governed aliases, so an owner maps the owned snapshot back into
 * the write shape instead of round-tripping platform metadata. The observed
 * base token is required and is never assigned to a stored counter.
 */
class StoreTourRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(TourService::class)->requireOwnedTour(
            (int) $this->route('id'),
            (int) $this->attributes->get('partner_id')
        );

        return true;
    }

    private function storedSourceLocale(): string
    {
        $tour = Tour::find((int) $this->route('id'));

        return $tour?->sourceLocale() ?? Tour::DEFAULT_SOURCE_LOCALE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sourceLocale = $this->storedSourceLocale();

        return [
            'base_revision_id' => 'required|integer|min:1',
            'change_summary' => 'sometimes|nullable|string|max:500',
            'source' => 'sometimes|nullable|array',
            'source.title' => 'sometimes|nullable|string|max:120',
            'source.description' => 'sometimes|nullable|string|max:5000',
            'source.highlights' => 'sometimes|nullable|list|max:30',
            'source.highlights.*' => 'string|max:500',
            'source.inclusions' => 'sometimes|nullable|list|max:30',
            'source.inclusions.*' => 'string|max:500',
            'source.exclusions' => 'sometimes|nullable|list|max:30',
            'source.exclusions.*' => 'string|max:500',
            'source.meeting_point' => 'sometimes|nullable|string|max:500',
            'source.cancellation_policy' => 'sometimes|nullable|string|max:2000',
            'source.important_information' => 'sometimes|nullable|list|max:30',
            'source.important_information.*' => 'string|max:500',
            'source.itinerary' => 'sometimes|nullable|list|max:30',
            ...TourContentRules::itineraryRules('source.itinerary'),
            'presentation' => 'sometimes|nullable|array',
            'presentation.category_id' => 'sometimes|integer|exists:categories,id',
            'presentation.category' => 'sometimes|string|max:50',
            'presentation.slug' => 'sometimes|string|max:160',
            'presentation.location' => 'sometimes|nullable|string|max:150',
            'presentation.location_slug' => 'sometimes|nullable|string|max:150',
            'presentation.duration_minutes' => 'sometimes|nullable|integer|min:1|max:10080',
            'presentation.duration_label' => 'sometimes|nullable|string|max:80',
            'presentation.duration_value' => 'sometimes|integer|min:1',
            'presentation.duration_unit' => 'sometimes|string|in:hour,day',
            'presentation.group_size_min' => 'sometimes|integer|min:1|max:1000',
            'presentation.group_size_max' => 'sometimes|integer|min:1|max:1000',
            'presentation.difficulty_level' => 'sometimes|nullable|in:easy,moderate,challenging',
            'presentation.guide_languages' => 'sometimes|nullable|array|max:20',
            'presentation.guide_languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'presentation.cover_image_url' => 'sometimes|nullable|url|max:2048|regex:/^https?:\/\//i',
            'options' => 'sometimes|nullable|list|max:20',
            'options.*.id' => 'required|integer|min:1|distinct',
            'options.*.title' => 'required_with:options.*.pickup_instructions|string|max:120|regex:/.*\\S.*/',
            'options.*.pickup_instructions' => 'sometimes|nullable|string|max:500',
            ...TourContentRules::mediaRules(),
            'media' => 'sometimes|nullable|list|max:20',
            ...TourContentRules::sourceRules(false, $sourceLocale),
            'itinerary' => 'sometimes|nullable|list|max:30',
            ...TourContentRules::itineraryRules('itinerary'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $sourceLocale = $this->storedSourceLocale();

        $validator->after(function (Validator $validator) use ($sourceLocale): void {
            $input = $this->all();
            foreach ($this->observedInputKeys() as $key) {
                unset($input[$key]);
            }
            foreach (TourContentRules::forbiddenPresentPaths($input, $sourceLocale, allowSourceLocale: false) as $path => $message) {
                $validator->errors()->add($path, $message);
            }

            $presentationKeys = [
                'category', 'category_id', 'slug', 'location', 'destination', 'location_slug',
                'duration_minutes', 'duration_label', 'duration_value', 'duration_unit',
                'group_size_min', 'group_size_max', 'difficulty_level', 'guide_languages',
                'languages', 'cover_image_url',
            ];
            $allowedKeys = [
                ...$this->observedInputKeys(), ...TourContentRules::SOURCE_FIELDS,
                ...$presentationKeys, 'source', 'presentation', 'translations', 'options', 'media',
                'change_summary',
            ];

            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowedKeys, true)) {
                    $validator->errors()->add($key, 'This field does not belong to a content revision.');
                }
            }

            foreach (['source' => TourContentRules::SOURCE_FIELDS, 'presentation' => $presentationKeys] as $tier => $keys) {
                $values = $this->input($tier);
                if (! is_array($values)) {
                    continue;
                }
                foreach (array_keys($values) as $key) {
                    if (! in_array($key, $keys, true)) {
                        $validator->errors()->add("{$tier}.{$key}", 'This field does not belong to this content tier.');
                    }
                }
            }

            // Published option additions, reactivation, retirement and
            // reordering are refused in this slice (fail closed, no A3); the
            // revision option tier is text-only.
            if (! is_array($options = $this->input('options'))) {
                return;
            }

            foreach (array_values($options) as $index => $option) {
                if (! is_array($option)) {
                    $validator->errors()->add("options.{$index}", 'Options must be text revisions with an id.');

                    continue;
                }

                foreach (array_keys($option) as $key) {
                    if (in_array($key, ['id', 'title', 'pickup_instructions'], true)) {
                        continue;
                    }

                    $validator->errors()->add("options.{$index}.{$key}", 'Only option identity and authored text may be revised here.');
                }

                if (! isset($option['id'])) {
                    $validator->errors()->add("options.{$index}.id", 'Option text revisions reference an existing option id.');
                }
            }
        });
    }

    /** @return list<string> */
    protected function observedInputKeys(): array
    {
        return ['base_revision_id'];
    }

    /**
     * The observed base revision id this candidate is built on.
     */
    public function observedBaseRevisionId(): ?int
    {
        $value = $this->input('base_revision_id');

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    public function hasChangeSummary(): bool
    {
        return array_key_exists('change_summary', $this->all());
    }

    public function changeSummary(): ?string
    {
        $value = $this->input('change_summary');

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * The bounded, tier-normalized patch the revision service stores.
     *
     * @return array<string, mixed>
     */
    public function contentPatch(): array
    {
        $sourceLocale = $this->storedSourceLocale();
        $patch = [];

        if (is_array($this->input('source'))) {
            $patch['source'] = $this->input('source');
        } else {
            $shorthand = array_diff_key($this->all(), array_flip([
                'base_revision_id', 'observed_payload_hash', 'change_summary', 'presentation', 'options', 'media', 'note', 'source',
            ]));
            $normalized = TourContentRules::normalizeSourcePatch($shorthand, $sourceLocale);

            if ($normalized !== []) {
                $patch['source'] = $normalized;
            }
        }

        if (is_array($presentation = $this->input('presentation'))) {
            $patch['presentation'] = $presentation;
        } else {
            $governed = [];

            foreach ([
                'category', 'category_id', 'slug', 'location', 'destination', 'location_slug',
                'duration_minutes', 'duration_label', 'duration_value', 'duration_unit',
                'group_size_min', 'group_size_max', 'difficulty_level', 'guide_languages',
                'languages', 'cover_image_url',
            ] as $alias) {
                if (array_key_exists($alias, $this->all())) {
                    $governed[$alias] = $this->input($alias);
                }
            }

            if ($governed !== []) {
                $patch['presentation'] = $governed;
            }
        }

        if (is_array($this->input('options'))) {
            $patch['options'] = $this->normalizedOptionTexts($this->input('options'));
        }

        if (is_array($this->input('media'))) {
            $patch['media'] = array_values($this->input('media'));
        }

        return $patch;
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array<int|string, array<string, mixed>>
     */
    private function normalizedOptionTexts(array $options): array
    {
        $patch = [];

        foreach (array_values($options) as $option) {
            if (! is_array($option) || ! isset($option['id'])) {
                continue;
            }

            $id = (int) $option['id'];

            if ($id < 1) {
                continue;
            }

            $entry = [];

            if (array_key_exists('title', $option)) {
                $entry['title'] = $option['title'];
            }

            if (array_key_exists('pickup_instructions', $option)) {
                $entry['pickup_instructions'] = $option['pickup_instructions'];
            }

            $patch[$id] = $entry;
        }

        return $patch;
    }
}
