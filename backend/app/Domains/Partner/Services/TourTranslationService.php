<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Requests\TourContentRules;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

    /**
     * Persist the desired pending/stale generation state for a changed English
     * source and return the per-locale generation plans WITHOUT dispatching.
     *
     * Must run inside the source transaction/lock (Tour -> EN -> es/it): old
     * derivatives and their translated hashes are retained, unchanged sources
     * (including canonical key-order-only rewrites) and blank/missing English
     * plan nothing, and media/settings-only saves skip regeneration. The
     * caller dispatches the returned plans after commit via
     * dispatchPlannedTranslations() so a lost queue push can never fail the
     * committed source write.
     *
     * @return array<int, array{tour_id: int, locale: string, source_hash: string}>
     */
    public function planSourceDispatches(Tour $tour, ?string $previousHash): array
    {
        $english = $tour->translations()->where('locale', 'en')->first();
        if (! $english || blank($english->title)) {
            return [];
        }

        $hash = $this->sourceHash($english);
        if ($previousHash === $hash) {
            return [];
        }

        $planned = [];
        foreach (['es', 'it'] as $locale) {
            $state = TourTranslationState::firstOrNew(['tour_id' => $tour->id, 'locale' => $locale]);
            if ($state->exists && $state->source_hash === $hash && in_array($state->status, ['pending', 'ready'], true)) {
                continue;
            }

            $state->source_hash = $hash;
            $state->status = $tour->translations()->where('locale', $locale)->exists() ? 'stale' : 'pending';
            $state->last_error_code = null;
            $state->save();

            $planned[] = ['tour_id' => $tour->id, 'locale' => $locale, 'source_hash' => $hash];
        }

        return $planned;
    }

    /**
     * Dispatch planned generation jobs after the source transaction committed.
     *
     * The plans are pushed from a DB::afterCommit callback on the current
     * default connection, so nothing executes while the enclosing source
     * transaction is still open: a rolled-back source schedules nothing,
     * and every locale is attempted only after the true outermost commit
     * (transaction level 0). Each locale push is guarded on its own and the
     * PendingDispatch destructor runs inside the try, so a failing queue
     * push (lost connection, failing worker) is contained, the
     * already-committed source save stays successful, and the remaining
     * locale is still attempted. A push failure logs a fixed sanitized
     * category carrying only the tour/locale identifiers: no exception,
     * provider, key, body, prompt, or hash text is ever logged. The
     * persisted pending/stale desired states stay recoverable via
     * reconciliation. Only the queue push is guarded here; source
     * SQL/validation failures propagate from the source transaction
     * itself and are never swallowed.
     *
     * @param  array<int, array{tour_id: int, locale: string, source_hash: string}>  $planned
     */
    public function dispatchPlannedTranslations(array $planned): void
    {
        if ($planned === []) {
            return;
        }

        DB::afterCommit(function () use ($planned) {
            foreach ($planned as $plan) {
                try {
                    $pending = GenerateTourTranslationJob::dispatch(
                        $plan['tour_id'],
                        $plan['locale'],
                        $plan['source_hash']
                    );
                    unset($pending);
                } catch (\Throwable) {
                    Log::warning('tour_translation.dispatch_failed', [
                        'tour_id' => $plan['tour_id'],
                        'locale' => $plan['locale'],
                    ]);

                    continue;
                }
            }
        });
    }

    public function queueIfChanged(Tour $tour, ?string $previousHash): void
    {
        $this->dispatchPlannedTranslations($this->planSourceDispatches($tour, $previousHash));
    }

    /**
     * Bounded reconciliation for one tour under the shared Tour-first lock.
     *
     * Rechecks the current English hash inside the lock and requeues every
     * recoverable locale: orphan pending, stale, failed, and apparent-ready
     * rows (ready status with a mismatched translated hash or a missing
     * derivative row), even when they already carry the current desired hash.
     * Genuinely current ready derivatives (row present, both hashes matching)
     * are untouched: no provider call, no derivative rewrite, no state write,
     * no dispatch. Stored derivatives are never deleted or rewritten here.
     *
     * @return array<int, array{tour_id: int, locale: string, source_hash: string}>
     */
    public function reconcileTour(Tour $tour): array
    {
        return $this->withContentLock($tour->id, function () use ($tour) {
            $english = TourTranslation::where('tour_id', $tour->id)->where('locale', 'en')->first();
            if (! $english || blank($english->title)) {
                return [];
            }

            $hash = $this->sourceHash($english);
            $planned = [];
            foreach (['es', 'it'] as $locale) {
                $state = TourTranslationState::where('tour_id', $tour->id)->where('locale', $locale)->first();
                $rowExists = TourTranslation::where('tour_id', $tour->id)->where('locale', $locale)->exists();
                $recoveryStatus = $rowExists ? 'stale' : 'pending';

                if ($state && $state->status === 'ready' && $state->translated_hash === $hash && $rowExists) {
                    continue;
                }

                if (! $state || $state->source_hash !== $hash) {
                    TourTranslationState::updateOrCreate(
                        ['tour_id' => $tour->id, 'locale' => $locale],
                        ['source_hash' => $hash, 'status' => $recoveryStatus, 'last_error_code' => null]
                    );
                } elseif ($state->status === 'failed' || $state->status === 'ready') {
                    // Same desired hash but recoverable: a sanitized failure
                    // or an apparent ready whose derivative is missing or
                    // hash-mismatched. Re-arm to pending/stale without
                    // touching stored derivatives.
                    $state->update(['status' => $recoveryStatus, 'last_error_code' => null]);
                }
                // Pending/stale rows already carrying the current hash are
                // orphaned dispatches: requeue without state churn.

                $planned[] = ['tour_id' => $tour->id, 'locale' => $locale, 'source_hash' => $hash];
            }

            return $planned;
        });
    }

    /**
     * Whether the tour holds at least one genuinely current derivative:
     * ready status, translated hash matching the fresh English source, and
     * the derivative row present. Read-only; used by --refresh-ready to
     * repair lost search projections without provider or content writes.
     */
    public function hasCurrentDerivatives(Tour $tour): bool
    {
        $english = $tour->translations()->where('locale', 'en')->first();
        if (! $english) {
            return false;
        }

        $hash = $this->sourceHash($english);
        foreach (['es', 'it'] as $locale) {
            $state = $tour->translationStates()->where('locale', $locale)->first();
            if ($state && $state->status === 'ready' && $state->translated_hash === $hash
                && $tour->translations()->where('locale', $locale)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract traveler-facing source strings for provider translation.
     *
     * Only authored text paths are emitted (scalar title/description/
     * meeting_point/cancellation_policy, text-list items, day titles/
     * descriptions, stop titles/descriptions); structural day numbers and
     * stop durations are never emitted, even when a malformed persisted
     * value is stored as a string. Blank strings are skipped; exact source
     * paths are preserved in payload order for shape validation.
     */
    public function strings(array $payload): array
    {
        $strings = [];

        foreach ($payload as $field => $value) {
            if (in_array($field, ['title', 'description', 'meeting_point', 'cancellation_policy'], true)) {
                if (is_string($value) && trim($value) !== '') {
                    $strings[$field] = $value;
                }
            } elseif (in_array($field, ['highlights', 'inclusions', 'exclusions', 'important_information'], true)) {
                if (! is_array($value)) {
                    continue;
                }
                foreach ($value as $index => $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $strings[$field . '.' . $index] = $item;
                    }
                }
            } elseif ($field === 'itinerary') {
                if (! is_array($value)) {
                    continue;
                }
                foreach ($value as $dayIndex => $day) {
                    if (! is_array($day)) {
                        continue;
                    }
                    foreach (['title', 'description'] as $textField) {
                        if (array_key_exists($textField, $day)
                            && is_string($day[$textField])
                            && trim($day[$textField]) !== '') {
                            $strings["itinerary.{$dayIndex}.{$textField}"] = $day[$textField];
                        }
                    }
                    if (array_key_exists('stops', $day) && is_array($day['stops'])) {
                        foreach ($day['stops'] as $stopIndex => $stop) {
                            if (! is_array($stop)) {
                                continue;
                            }
                            foreach (['title', 'description'] as $textField) {
                                if (array_key_exists($textField, $stop)
                                    && is_string($stop[$textField])
                                    && trim($stop[$textField]) !== '') {
                                    $strings["itinerary.{$dayIndex}.stops.{$stopIndex}.{$textField}"] = $stop[$textField];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $strings;
    }

    /**
     * Strict source/output boundary for provider translation.
     *
     * Validates structure and bounds (required types, canonical list shapes,
     * day/duration ranges) plus real-integer day/duration types. Shared by
     * applyStrings so malformed persisted source (scalar itinerary, scalar
     * days, scalar/null stops, digit-string integers) fails with
     * ValidationException before any translated-key shape matching, and the
     * reconstructed output is rechecked afterward with the same boundary.
     */
    public function validateTranslatablePayload(array $payload): void
    {
        Validator::make($payload, [
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:5000',
            'highlights' => 'nullable|list|max:30',
            'highlights.*' => 'string|max:500',
            'inclusions' => 'nullable|list|max:30',
            'inclusions.*' => 'string|max:500',
            'exclusions' => 'nullable|list|max:30',
            'exclusions.*' => 'string|max:500',
            'important_information' => 'nullable|list|max:30',
            'important_information.*' => 'string|max:500',
            'meeting_point' => 'nullable|string|max:500',
            'cancellation_policy' => 'nullable|string|max:2000',
            ...TourContentRules::itineraryRules('itinerary'),
        ])->validate();

        $this->rejectCoercedIntegers($payload);
    }

    public function applyStrings(array $source, array $translated): array
    {
        $this->validateTranslatablePayload($source);

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

        $this->validateTranslatablePayload($source);

        return $source;
    }

    /**
     * Reject persisted digit-string day/duration values.
     *
     * Laravel's `integer` rule accepts digit strings for compatibility, but
     * translated stored JSON must keep real integers; fractional and
     * non-numeric values are already rejected by that rule, while digit
     * strings that slipped through are rejected here instead of being
     * silently cast, preserving every integer/null without coercion.
     */
    private function rejectCoercedIntegers(array $source): void
    {
        $itinerary = $source['itinerary'] ?? null;
        if (! is_array($itinerary)) {
            return;
        }
        foreach (array_values($itinerary) as $day) {
            if (! is_array($day)) {
                continue;
            }
            if (array_key_exists('day', $day) && ! is_int($day['day'])) {
                throw ValidationException::withMessages(['itinerary' => 'Day must be a JSON integer.']);
            }
            if (array_key_exists('stops', $day) && is_array($day['stops'])) {
                foreach (array_values($day['stops']) as $stop) {
                    if (! is_array($stop)) {
                        continue;
                    }
                    if (array_key_exists('duration_minutes', $stop)
                        && $stop['duration_minutes'] !== null
                        && ! is_int($stop['duration_minutes'])) {
                        throw ValidationException::withMessages(['itinerary' => 'Duration must be a JSON integer.']);
                    }
                }
            }
        }
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
