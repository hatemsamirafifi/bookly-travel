<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Support\Facades\DB;
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
        $raw = [];
        foreach (self::FIELDS as $field) {
            $raw[$field] = $english->{$field};
        }

        return $this->normalizeSourceProjection($raw);
    }

    /**
     * Canonicalize the stored English projection for revision identity.
     *
     * Day/stop objects are rebuilt in fixed key order
     * (day/title/description/stops and title/description/duration_minutes)
     * with absent optional stops defaulting to `[]`. Malformed structures
     * (scalar days/stops, non-array stops lists) are preserved verbatim so
     * they hash distinctly from valid or empty source instead of colliding
     * with it; values are never coerced (numeric strings stay strings).
     * Bounds are checked separately by TourContentRules; this projection
     * retains the stored values used for revision identity.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeSourceProjection(array $raw): array
    {
        $payload = [];
        foreach (self::FIELDS as $field) {
            $value = array_key_exists($field, $raw) ? $raw[$field] : null;
            $payload[$field] = $field === 'itinerary'
                ? $this->normalizeItineraryForHash($value)
                : $value;
        }

        return $payload;
    }

    /**
     * Canonicalize the stored itinerary for revision identity.
     *
     * Day/stop object keys use a fixed order. Scalar days/stops and
     * non-array stops/itineraries pass through without coercion, preserving
     * their distinction from valid and empty source.
     */
    private function normalizeItineraryForHash(mixed $itinerary): mixed
    {
        if ($itinerary === null) {
            return null;
        }
        if (! is_array($itinerary)) {
            return $itinerary;
        }

        $days = [];
        foreach (array_values($itinerary) as $day) {
            if (! is_array($day)) {
                $days[] = $day;

                continue;
            }
            $normalizedDay = [
                'day' => array_key_exists('day', $day) ? $day['day'] : null,
                'title' => array_key_exists('title', $day) ? $day['title'] : null,
                'description' => array_key_exists('description', $day) ? $day['description'] : null,
                'stops' => array_key_exists('stops', $day) ? $day['stops'] : [],
            ];
            if (is_array($normalizedDay['stops'])) {
                $normalizedStops = [];
                foreach (array_values($normalizedDay['stops']) as $stop) {
                    if (! is_array($stop)) {
                        $normalizedStops[] = $stop;

                        continue;
                    }
                    $normalizedStops[] = [
                        'title' => array_key_exists('title', $stop) ? $stop['title'] : null,
                        'description' => array_key_exists('description', $stop) ? $stop['description'] : null,
                        'duration_minutes' => array_key_exists('duration_minutes', $stop) ? $stop['duration_minutes'] : null,
                    ];
                }
                $normalizedDay['stops'] = $normalizedStops;
            }
            $days[] = $normalizedDay;
        }

        return $days;
    }

    public function sourceHash(TourTranslation $english): string
    {
        // Serialization flags are unchanged from the inherited implementation
        // so existing persisted desired hashes stay comparable; only the
        // documented key-order canonicalization affects identity.
        return hash('sha256', json_encode($this->sourcePayload($english), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Reusable Tour-first transaction boundary for source/completion work.
     *
     * Lock order is always Tour -> EN translation -> states in es/it order.
     * Provider/network work must stay outside this boundary; callers recheck
     * the fresh source hash inside the callback before mutating state.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withContentLock(int $tourId, callable $callback): mixed
    {
        return DB::transaction(function () use ($tourId, $callback) {
            Tour::where('id', $tourId)->lockForUpdate()->first();
            TourTranslation::where('tour_id', $tourId)
                ->where('locale', 'en')
                ->lockForUpdate()
                ->first();
            foreach (['es', 'it'] as $locale) {
                TourTranslationState::where('tour_id', $tourId)
                    ->where('locale', $locale)
                    ->lockForUpdate()
                    ->first();
            }

            return $callback();
        });
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
