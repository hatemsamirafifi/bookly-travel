<?php

namespace App\Domains\Partner\Jobs;

use App\Domains\Partner\Contracts\SourceAwareTourContentTranslator;
use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use App\Models\TourOption;
use App\Models\TourOptionTranslation;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateTourTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    /**
     * Permanent provider failure categories safe to persist verbatim.
     * Anything else (bodies, prompts, keys, whitespace, oversized text)
     * is recorded as translation_output_invalid instead.
     */
    private const PERMANENT_CODES = [
        'invalid_translation_request',
        'translation_not_configured',
        'translation_provider_rejected',
        'translation_provider_blocked',
        'translation_output_invalid',
    ];

    public function __construct(
        public readonly int $tourId,
        public readonly string $locale,
        public readonly string $sourceHash,
        /**
         * The canonical generation identity this work was planned for. Legacy
         * queued jobs serialized before EGM-003 carry no epoch at all: the
         * constructor tolerates its absence for queue deserialization, but a
         * null epoch never equals the current integer epoch and such a job
         * performs no success or failure write (design v3 §7).
         */
        public readonly ?int $generationEpoch = null,
    ) {}

    /**
     * The desired epoch of this job, tolerant of a legacy payload that never
     * carried the property: `isset()` on an uninitialized (or absent) typed
     * property is false without throwing, so an old serialized job can never
     * raise an uninitialized-property error.
     */
    private function desiredEpoch(): ?int
    {
        return isset($this->generationEpoch) ? (int) $this->generationEpoch : null;
    }

    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function handle(TourTranslationService $service, TourContentTranslator $translator): void
    {
        // Legacy queued jobs carry no generation identity: after the cutover
        // they safely no-op (reconciliation queues epoch-aware replacements).
        if (($epoch = $this->desiredEpoch()) === null) {
            return;
        }

        $tour = Tour::with(['translations', 'translationStates'])->find($this->tourId);
        if (! $tour || $tour->generationEpoch() !== $epoch) {
            return;
        }

        // Only generated derivative locales of this tour's authored source are
        // translatable; the source locale itself and unknown locales are no-ops.
        $sourceLocale = $tour->sourceLocale();
        if ($this->locale === $sourceLocale
            || ! in_array($this->locale, Tour::derivativeLocalesFor($sourceLocale), true)) {
            return;
        }

        $source = $tour->translations->firstWhere('locale', $sourceLocale);
        if (! $source) {
            return;
        }
        if ($service->sourceHash($source, $tour) !== $this->sourceHash) {
            return;
        }

        // The provider input is the APPROVED, materialized source only: a
        // proposed/under-review revision payload never reaches the provider.
        $state = $tour->translationStates->firstWhere('locale', $this->locale);
        if (! $state || $state->source_hash !== $this->sourceHash) {
            return;
        }
        // NULL never equals the current integer epoch, so a state row without a
        // desired epoch identity is likewise never eligible.
        if ((int) $state->generation_epoch !== $epoch) {
            return;
        }

        // Genuinely ready with a real derivative row for the current identity:
        // skip provider and writes. An apparent ready row with no derivative
        // falls through to recovery.
        if ($state->status === 'ready'
            && $state->translated_hash === $this->sourceHash
            && (int) $state->generation_epoch === $epoch
            && $tour->translations->firstWhere('locale', $this->locale) !== null) {
            return;
        }

        // Malformed persisted source must never reach the provider or a
        // derivative: safe-fail without touching the stored source.
        try {
            $this->validateRawSource($service, $source);
        } catch (ValidationException|\InvalidArgumentException $e) {
            $this->markFailed($service, 'translation_output_invalid');

            return;
        }

        $sourcePayload = $service->sourcePayload($source, $tour);
        $strings = $service->strings($sourcePayload);

        try {
            $translatedStrings = $this->translate($translator, $strings, $sourceLocale);
            $translated = $service->applyStrings($sourcePayload, $translatedStrings);
        } catch (PermanentTranslationException $e) {
            $this->markFailed($service, $this->permanentCode($e->getMessage()));

            return;
        } catch (ValidationException|\InvalidArgumentException $e) {
            $this->markFailed($service, 'translation_output_invalid');

            return;
        }

        $result = $service->withContentLock($this->tourId, function () use ($service, $translated, $sourceLocale, $epoch): string {
            $tour = Tour::find($this->tourId);

            if (! $tour || $tour->generationEpoch() !== $epoch) {
                return 'noop';
            }

            $source = TourTranslation::where('tour_id', $this->tourId)->where('locale', $sourceLocale)->first();
            if (! $source || $service->sourceHash($source, $source->tour) !== $this->sourceHash) {
                return 'noop';
            }

            $state = TourTranslationState::where('tour_id', $this->tourId)
                ->where('locale', $this->locale)
                ->first();
            if (! $state || $state->source_hash !== $this->sourceHash) {
                return 'noop';
            }
            if ((int) $state->generation_epoch !== $epoch) {
                return 'noop';
            }

            // First same-identity completion wins: a genuinely ready derivative
            // that now exists must stand, never be rewritten by a late job.
            if ($state->status === 'ready'
                && $state->translated_hash === $this->sourceHash
                && (int) $state->generation_epoch === $epoch
                && TourTranslation::where('tour_id', $this->tourId)->where('locale', $this->locale)->exists()) {
                return 'noop';
            }

            // The canonical hash projection reindexes day/stop lists, so raw
            // stored fields are revalidated here: a malformed day/stops object
            // that hashes like valid source must still fail safe with no
            // derivative and no source rewrite.
            try {
                $this->validateRawSource($service, $source);
            } catch (ValidationException|\InvalidArgumentException $e) {
                return 'invalid';
            }

            $translatedFields = $translated;
            // Option texts live in their own id-keyed store, never inside the
            // tour translation row: strip them before the row write and apply
            // them scoped to this tour's stable option ids.
            $translatedOptions = $translatedFields['options'] ?? null;
            unset($translatedFields['options']);

            $source->tour->translations()->updateOrCreate(
                ['locale' => $this->locale],
                $translatedFields
            );

            if (is_array($translatedOptions) && $translatedOptions !== []) {
                $this->writeOptionTranslations($translatedOptions);
            }

            $state->update([
                'status' => 'ready',
                'translated_hash' => $this->sourceHash,
                'generation_epoch' => $epoch,
                'last_error_code' => null,
            ]);

            // A public derivative state/content transition advances the content
            // version in the SAME transaction, fencing old cache fills against
            // the new canonical content (design v3 §8).
            Tour::query()->whereKey($this->tourId)->update(['content_version' => DB::raw('content_version + 1')]);

            return 'ready';
        });

        if ($result === 'invalid') {
            $this->markFailed($service, 'translation_output_invalid');

            return;
        }

        if ($result === 'ready') {
            // The translation row touches Tour before its state becomes ready.
            // Queue one post-commit refresh so search observes the approved locale.
            IndexTourAction::dispatch($this->tourId)->afterCommit();
        }
    }

    public function failed(?Throwable $exception): void
    {
        // A legacy job without identity performs no failure write at all.
        if (($epoch = $this->desiredEpoch()) === null) {
            return;
        }

        $this->markFailed(app(TourTranslationService::class), 'translation_provider_temporary');
    }

    /**
     * Persist generated option texts for this locale, keyed by stable option
     * id. Only options that still belong to this tour are written — the
     * translator never changes ids, but a directly mutated source is re-checked
     * here so no derivative row can ever be attached to a foreign option.
     * Rows are upserted per (option, locale); retirement never deletes them.
     *
     * @param  array<int|string, mixed>  $translatedOptions  option id => generated entry
     */
    private function writeOptionTranslations(array $translatedOptions): void
    {
        $validOptionIds = TourOption::query()
            ->where('tour_id', $this->tourId)
            ->whereIn('id', array_map('intval', array_keys($translatedOptions)))
            ->pluck('id');

        foreach ($translatedOptions as $optionId => $entry) {
            if (! is_array($entry) || ! is_string($entry['title'] ?? null) || trim($entry['title']) === '') {
                continue;
            }
            if (! $validOptionIds->contains((int) $optionId)) {
                continue;
            }

            TourOptionTranslation::query()->updateOrCreate(
                ['tour_option_id' => (int) $optionId, 'locale' => $this->locale],
                [
                    'title' => $entry['title'],
                    'pickup_instructions' => is_string($entry['pickup_instructions'] ?? null)
                        ? $entry['pickup_instructions']
                        : null,
                ]
            );
        }
    }

    private function translate(TourContentTranslator $translator, array $strings, string $sourceLocale): array
    {
        if ($sourceLocale === Tour::DEFAULT_SOURCE_LOCALE) {
            return $translator->translate($strings, $this->locale);
        }

        if ($translator instanceof SourceAwareTourContentTranslator) {
            return $translator->translateFrom($strings, $sourceLocale, $this->locale);
        }

        throw new PermanentTranslationException('invalid_translation_request');
    }

    /**
     * Validate the RAW stored source fields through the hardened service
     * boundary (shared list rules + strict integer checks), never a local
     * rule copy and never the canonical hash projection: array_values
     * reindexing would silently launder malformed associative day/stops
     * objects into valid lists. The stored source row is only read here.
     * Option rows are raw-validated too (id-keyed scalar values) so a
     * malformed/blanks-titled option fails safe before any provider call.
     */
    private function validateRawSource(TourTranslationService $service, TourTranslation $source): void
    {
        $raw = [];
        foreach (TourContentRules::SOURCE_FIELDS as $field) {
            $raw[$field] = $source->{$field};
        }

        $options = $service->sourceOptionsPayload($source->tour_id, $source->tour);
        if ($options !== null) {
            $raw['options'] = $options;
        }

        $service->validateTranslatablePayload($raw);
    }

    private function permanentCode(string $message): string
    {
        return in_array($message, self::PERMANENT_CODES, true)
            ? $message
            : 'translation_output_invalid';
    }

    /**
     * Guarded terminal failure under the shared Tour-first lock: writes only
     * when the fresh source hash and the desired state still match this job's
     * identity (hash AND epoch), so genuine current content, newer sources and
     * newer desired states are never demoted.
     */
    private function markFailed(TourTranslationService $service, string $code): void
    {
        if (($epoch = $this->desiredEpoch()) === null) {
            return;
        }

        $service->withContentLock($this->tourId, function () use ($service, $epoch, $code): void {
            $tour = Tour::find($this->tourId);
            if (! $tour || $tour->generationEpoch() !== $epoch) {
                return;
            }

            $source = TourTranslation::where('tour_id', $this->tourId)
                ->where('locale', $tour->sourceLocale())
                ->first();
            if (! $source || $service->sourceHash($source, $tour) !== $this->sourceHash) {
                return;
            }

            $state = TourTranslationState::where('tour_id', $this->tourId)
                ->where('locale', $this->locale)
                ->where('source_hash', $this->sourceHash)
                ->where('generation_epoch', $epoch)
                ->first();
            if ($state === null
                || ($state->status === 'failed' && $state->last_error_code === $code)
                || ($state->status === 'ready' && $state->translated_hash === $this->sourceHash
                    && TourTranslation::where('tour_id', $this->tourId)->where('locale', $this->locale)->exists())) {
                return;
            }
            $state->update(['status' => 'failed', 'last_error_code' => $code]);
            Tour::query()->whereKey($this->tourId)->update(['content_version' => DB::raw('content_version + 1')]);
        });
    }
}
