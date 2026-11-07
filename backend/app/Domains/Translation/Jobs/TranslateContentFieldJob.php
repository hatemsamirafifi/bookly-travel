<?php

namespace App\Domains\Translation\Jobs;

use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Translation\Contracts\ContentTranslatorContract;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Domains\Translation\Services\TranslationQualityValidator;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single-field translation worker (Admin 2.0 Phase H, T-H04).
 *
 * Translates exactly one (model, locale, field) from its English source,
 * validates the output, writes the translated value into the model's JSONB
 * column without touching sibling fields, and marks the TranslationFieldState
 * row completed (or failed) with provenance hashes. Genuinely external
 * failures retry (3 attempts, backoff 10/30/90s); validator rejections are
 * permanent failures recorded in the state row for the health dashboard.
 */
class TranslateContentFieldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly string $translatableType,
        public readonly int $translatableId,
        public readonly string $locale,
        public readonly string $fieldName,
    ) {}

    public function handle(
        ContentTranslatorContract $translator,
        TranslationQualityValidator $validator,
        AutoTranslationService $service,
    ): void {
        $state = TranslationFieldState::query()
            ->where('translatable_type', $this->translatableType)
            ->where('translatable_id', $this->translatableId)
            ->where('locale', $this->locale)
            ->where('field_name', $this->fieldName)
            ->first();

        if ($state === null
            || in_array($state->status, [
                TranslationFieldState::STATUS_NEEDS_REVIEW,
                TranslationFieldState::STATUS_MANUALLY_EDITED,
            ], true)) {
            return;
        }

        $state->status = TranslationFieldState::STATUS_TRANSLATING;
        $state->attempt_count = $state->attempt_count + 1;
        $state->save();

        $model = $this->resolveModel();

        if ($model === null) {
            $this->failState($state, 'MODEL_NOT_FOUND', "Translatable record {$this->translatableType}:{$this->translatableId} no longer exists");

            return;
        }

        $sourceText = $service->englishValue($model, $this->fieldName);

        if ($sourceText === null) {
            $this->failState($state, 'MISSING_EN_SOURCE', "English source for field [{$this->fieldName}] is empty");

            return;
        }

        $isHtml = $service->isHtmlField($model::class, $this->fieldName);

        $translation = $translator->translateField($sourceText, $this->locale, $this->fieldName, $isHtml);

        $judgement = $validator->validate(
            $sourceText,
            $translation['translated_text'],
            $this->locale,
            $this->fieldName,
            $isHtml,
        );

        if (! $judgement['valid']) {
            $this->failState($state, $judgement['error_code'] ?? 'VALIDATION_FAILED', $judgement['error_message'] ?? 'Translation failed validation');

            return;
        }

        $needsReview = $judgement['status'] === TranslationFieldState::STATUS_NEEDS_REVIEW;

        DB::transaction(function () use ($model, $state, $sourceText, $translation, $judgement, $needsReview, $service) {
            $this->writeTranslatedField($model, $judgement['sanitized_text']);

            $state->status = $needsReview
                ? TranslationFieldState::STATUS_NEEDS_REVIEW
                : TranslationFieldState::STATUS_COMPLETED;
            $state->source_hash = $service->normalizeHash($sourceText);
            $state->translated_source_hash = $service->normalizeHash($sourceText);
            $state->translated_hash = $service->normalizeHash($judgement['sanitized_text']);
            $state->provider = $translation['provider'];
            $state->model = $translation['model'];
            $state->last_error_code = $needsReview ? $judgement['error_code'] : null;
            $state->last_error_message = $needsReview ? $judgement['error_message'] : null;
            $state->last_translated_at = now();
            $state->save();

            if ($model instanceof Tour) {
                $this->syncTourTranslationState($model, $service);
            }
        });
    }

    private function resolveModel(): ?Model
    {
        $morphType = $this->translatableType;
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = $class::query()->find($this->translatableId);

        return $model;
    }

    private function writeTranslatedField(Model $model, string $translated): void
    {
        if ($model instanceof Tour) {
            $enRow = TourTranslation::query()
                ->where('tour_id', $model->id)
                ->where('locale', 'en')
                ->first();

            $row = TourTranslation::query()
                ->where('tour_id', $model->id)
                ->where('locale', $this->locale)
                ->first();

            $cleanTitle = Str::limit(trim(strip_tags($translated)), 120, '');

            if ($row === null) {
                $row = new TourTranslation([
                    'tour_id' => $model->id,
                    'locale' => $this->locale,
                    'title' => $this->fieldName === 'title'
                        ? $cleanTitle
                        : (string) ($enRow->title ?? $model->slug),
                    'description' => $this->fieldName === 'description'
                        ? $translated
                        : $enRow?->description,
                    'highlights' => $enRow?->highlights,
                    'inclusions' => $enRow?->inclusions,
                    'exclusions' => $enRow?->exclusions,
                    'meeting_point' => $enRow?->meeting_point,
                    'cancellation_policy' => $enRow?->cancellation_policy,
                    'itinerary' => $enRow?->itinerary,
                    'important_information' => $enRow?->important_information,
                ]);
            } else {
                $row->setAttribute(
                    $this->fieldName,
                    $this->fieldName === 'title' ? $cleanTitle : $translated,
                );
            }

            $row->save();
            $model->unsetRelation('translations');

            return;
        }

        $current = $model->getAttribute($this->fieldName);

        $payload = is_array($current) ? $current : [];
        $payload[$this->locale] = $translated;

        $model->setAttribute($this->fieldName, $payload);
        $model->save();
    }

    private function syncTourTranslationState(Tour $tour, AutoTranslationService $service): void
    {
        $enRow = TourTranslation::query()
            ->where('tour_id', $tour->id)
            ->where('locale', 'en')
            ->first();

        if ($enRow === null || trim((string) $enRow->title) === '') {
            return;
        }

        $fields = array_keys($service->translatableField(Tour::class));
        $states = TranslationFieldState::query()
            ->where('translatable_type', $tour->getMorphClass())
            ->where('translatable_id', $tour->getKey())
            ->where('locale', $this->locale)
            ->get()
            ->keyBy('field_name');

        foreach ($fields as $fieldName) {
            $enText = $service->englishValue($tour, $fieldName);
            if ($enText === null) {
                continue;
            }

            $fieldState = $states->get($fieldName);
            if ($fieldState === null || ! in_array($fieldState->status, [
                TranslationFieldState::STATUS_COMPLETED,
                TranslationFieldState::STATUS_MANUALLY_EDITED,
                TranslationFieldState::STATUS_NEEDS_REVIEW,
            ], true)) {
                return;
            }
        }

        $tourHash = app(TourTranslationService::class)->sourceHash($enRow);

        TourTranslationState::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'locale' => $this->locale],
            [
                'source_hash' => $tourHash,
                'translated_hash' => $tourHash,
                'status' => 'ready',
                'last_error_code' => null,
            ],
        );
    }

    private function failState(TranslationFieldState $state, string $code, string $message): void
    {
        $state->status = TranslationFieldState::STATUS_FAILED;
        $state->last_error_code = $code;
        $state->last_error_message = $message;
        $state->save();

        if ($this->translatableType === 'tour' || $this->translatableType === Tour::class) {
            $enRow = TourTranslation::query()
                ->where('tour_id', $this->translatableId)
                ->where('locale', 'en')
                ->first();

            if ($enRow !== null) {
                TourTranslationState::query()->updateOrCreate(
                    ['tour_id' => $this->translatableId, 'locale' => $this->locale],
                    [
                        'source_hash' => app(TourTranslationService::class)->sourceHash($enRow),
                        'status' => 'failed',
                        'last_error_code' => $code,
                    ],
                );
            }
        }
    }
}
