<?php

namespace App\Domains\Partner\Jobs;

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    ) {}

    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function handle(TourTranslationService $service, TourContentTranslator $translator): void
    {
        if (! in_array($this->locale, ['es', 'it'], true)) {
            return;
        }

        $tour = Tour::with(['translations', 'translationStates'])->find($this->tourId);
        $english = $tour?->translations->firstWhere('locale', 'en');
        if (! $tour || ! $english) {
            return;
        }
        if ($service->sourceHash($english) !== $this->sourceHash) {
            return;
        }

        $state = $tour->translationStates->firstWhere('locale', $this->locale);
        if (! $state || $state->source_hash !== $this->sourceHash) {
            return;
        }

        // Genuinely ready with a real derivative row: skip provider and writes.
        // An apparent ready row with no derivative falls through to recovery.
        if ($state->status === 'ready'
            && $state->translated_hash === $this->sourceHash
            && $tour->translations->firstWhere('locale', $this->locale) !== null) {
            return;
        }

        // Malformed persisted English must never reach the provider or a
        // derivative: safe-fail without touching the stored source.
        try {
            $this->validateRawSource($service, $english);
        } catch (ValidationException|\InvalidArgumentException $e) {
            $this->markFailed($service, 'translation_output_invalid');

            return;
        }

        $source = $service->sourcePayload($english);
        try {
            $translated = $service->applyStrings(
                $source,
                $translator->translate($service->strings($source), $this->locale)
            );
        } catch (PermanentTranslationException $e) {
            $this->markFailed($service, $this->permanentCode($e->getMessage()));

            return;
        } catch (ValidationException|\InvalidArgumentException $e) {
            $this->markFailed($service, 'translation_output_invalid');

            return;
        }

        $result = $service->withContentLock($this->tourId, function () use ($service, $translated): string {
            $english = TourTranslation::where('tour_id', $this->tourId)->where('locale', 'en')->first();
            if (! $english || $service->sourceHash($english) !== $this->sourceHash) {
                return 'noop';
            }

            $state = TourTranslationState::where('tour_id', $this->tourId)
                ->where('locale', $this->locale)
                ->first();
            if (! $state || $state->source_hash !== $this->sourceHash) {
                return 'noop';
            }

            // First same-hash completion wins: a genuinely ready derivative
            // that now exists must stand, never be rewritten by a late job.
            if ($state->status === 'ready'
                && $state->translated_hash === $this->sourceHash
                && TourTranslation::where('tour_id', $this->tourId)->where('locale', $this->locale)->exists()) {
                return 'noop';
            }

            // The canonical hash projection reindexes day/stop lists, so raw
            // stored fields are revalidated here: a malformed day/stops object
            // that hashes like valid source must still fail safe with no
            // derivative and no source rewrite.
            try {
                $this->validateRawSource($service, $english);
            } catch (ValidationException|\InvalidArgumentException $e) {
                return 'invalid';
            }

            $english->tour->translations()->updateOrCreate(
                ['locale' => $this->locale],
                $translated
            );
            $state->update([
                'status' => 'ready',
                'translated_hash' => $this->sourceHash,
                'last_error_code' => null,
            ]);

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
        $this->markFailed(app(TourTranslationService::class), 'translation_provider_temporary');
    }

    /**
     * Validate the RAW stored English fields through the hardened service
     * boundary (shared list rules + strict integer checks), never a local
     * rule copy and never the canonical hash projection: array_values
     * reindexing would silently launder malformed associative day/stops
     * objects into valid lists. The stored EN row is only read here.
     */
    private function validateRawSource(TourTranslationService $service, TourTranslation $english): void
    {
        $raw = [];
        foreach (TourContentRules::SOURCE_FIELDS as $field) {
            $raw[$field] = $english->{$field};
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
     * when the fresh English hash and the desired state still match this job
     * and the row is not ready, so genuine current content, newer sources,
     * and newer desired states are never demoted.
     */
    private function markFailed(TourTranslationService $service, string $code): void
    {
        $service->withContentLock($this->tourId, function () use ($service, $code): void {
            $english = TourTranslation::where('tour_id', $this->tourId)->where('locale', 'en')->first();
            if (! $english || $service->sourceHash($english) !== $this->sourceHash) {
                return;
            }

            TourTranslationState::where('tour_id', $this->tourId)
                ->where('locale', $this->locale)
                ->where('source_hash', $this->sourceHash)
                ->where('status', '!=', 'ready')
                ->update(['status' => 'failed', 'last_error_code' => $code]);
        });
    }
}
