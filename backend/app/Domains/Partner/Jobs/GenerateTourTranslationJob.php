<?php

namespace App\Domains\Partner\Jobs;

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
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
        $tour = Tour::with('translations')->find($this->tourId);
        $english = $tour?->translations->firstWhere('locale', 'en');
        if (! $english || $service->sourceHash($english) !== $this->sourceHash) {
            return;
        }

        $source = $service->sourcePayload($english);
        try {
            $translated = $service->applyStrings(
                $source,
                $translator->translate($service->strings($source), $this->locale)
            );
        } catch (PermanentTranslationException|ValidationException|\InvalidArgumentException $e) {
            $this->markFailed($e instanceof PermanentTranslationException ? $e->getMessage() : 'translation_output_invalid');

            return;
        }

        $becameReady = DB::transaction(function () use ($service, $translated): bool {
            $state = TourTranslationState::where('tour_id', $this->tourId)
                ->where('locale', $this->locale)
                ->lockForUpdate()
                ->first();
            $english = Tour::find($this->tourId)?->translations()->where('locale', 'en')->first();
            if (! $state || ! $english || $state->source_hash !== $this->sourceHash
                || $service->sourceHash($english) !== $this->sourceHash) {
                return false;
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

            return true;
        });

        if ($becameReady) {
            // The translation row touches Tour before its state becomes ready.
            // Queue one post-commit refresh so search observes the approved locale.
            IndexTourAction::dispatch($this->tourId)->afterCommit();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('translation_provider_temporary');
    }

    private function markFailed(string $code): void
    {
        TourTranslationState::where('tour_id', $this->tourId)
            ->where('locale', $this->locale)
            ->where('source_hash', $this->sourceHash)
            ->where('status', '!=', 'ready')
            ->update(['status' => 'failed', 'last_error_code' => $code]);
    }
}
