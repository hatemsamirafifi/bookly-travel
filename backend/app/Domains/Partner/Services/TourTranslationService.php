<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Support\Facades\Validator;

class TourTranslationService
{
    private const FIELDS = [
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

    public function sourcePayload(TourTranslation $english): array
    {
        $payload = [];
        foreach (self::FIELDS as $field) {
            $payload[$field] = $english->{$field};
        }

        return $payload;
    }

    public function sourceHash(TourTranslation $english): string
    {
        return hash('sha256', json_encode($this->sourcePayload($english), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function queueIfChanged(Tour $tour, ?string $previousHash): void
    {
        $english = $tour->translations()->where('locale', 'en')->first();
        if (! $english || blank($english->title)) {
            return;
        }

        $hash = $this->sourceHash($english);
        if ($previousHash === $hash) {
            return;
        }

        foreach (['es', 'it'] as $locale) {
            $state = TourTranslationState::firstOrNew(['tour_id' => $tour->id, 'locale' => $locale]);
            if ($state->exists && $state->source_hash === $hash && in_array($state->status, ['pending', 'ready'], true)) {
                continue;
            }

            $state->source_hash = $hash;
            $state->status = $tour->translations()->where('locale', $locale)->exists() ? 'stale' : 'pending';
            $state->last_error_code = null;
            $state->save();

            GenerateTourTranslationJob::dispatch($tour->id, $locale, $hash)->afterCommit();
        }
    }

    public function strings(array $payload): array
    {
        $strings = [];
        $walk = function (array $items, string $prefix = '') use (&$walk, &$strings): void {
            foreach ($items as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                if (is_array($value)) {
                    $walk($value, $path);
                } elseif (is_string($value) && trim($value) !== '') {
                    $strings[$path] = $value;
                }
            }
        };
        $walk($payload);

        return $strings;
    }

    public function applyStrings(array $source, array $translated): array
    {
        $expected = $this->strings($source);
        if (array_keys($translated) !== array_keys($expected)) {
            throw new \InvalidArgumentException('translation_shape');
        }

        foreach ($translated as $path => $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException('translation_shape');
            }
            data_set($source, $path, $value);
        }

        Validator::make($source, [
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:5000',
            'highlights' => 'nullable|array|max:30',
            'highlights.*' => 'string|max:500',
            'inclusions' => 'nullable|array|max:30',
            'inclusions.*' => 'string|max:500',
            'exclusions' => 'nullable|array|max:30',
            'exclusions.*' => 'string|max:500',
            'important_information' => 'nullable|array|max:30',
            'important_information.*' => 'string|max:500',
            'meeting_point' => 'nullable|string|max:500',
            'cancellation_policy' => 'nullable|string|max:2000',
            'itinerary' => 'nullable|array|max:30',
            'itinerary.*.title' => 'required|string|max:160',
            'itinerary.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stops' => 'nullable|array|max:20',
            'itinerary.*.stops.*.title' => 'required|string|max:160',
            'itinerary.*.stops.*.description' => 'nullable|string|max:2000',
        ])->validate();

        return $source;
    }

    public function currentTranslation(Tour $tour, string $locale): ?TourTranslation
    {
        $translations = $tour->relationLoaded('translations') ? $tour->translations : $tour->translations()->get();
        $english = $translations->firstWhere('locale', 'en');
        if ($locale === 'en') {
            return $english;
        }
        if (! $english) {
            return null;
        }

        $states = $tour->relationLoaded('translationStates') ? $tour->translationStates : $tour->translationStates()->get();
        $state = $states->firstWhere('locale', $locale);

        return $state
            && $state->status === 'ready'
            && $state->translated_hash === $this->sourceHash($english)
                ? $translations->firstWhere('locale', $locale)
                : null;
    }

    public function publicStatus(Tour $tour, string $locale): string
    {
        if ($locale === 'en') {
            return 'source';
        }

        $states = $tour->relationLoaded('translationStates') ? $tour->translationStates : $tour->translationStates()->get();

        $status = $states->firstWhere('locale', $locale)->status ?? 'pending';

        return $status === 'ready' && ! $this->currentTranslation($tour, $locale)
            ? 'stale'
            : $status;
    }
}
