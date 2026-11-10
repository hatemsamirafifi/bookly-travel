<?php

namespace App\Domains\Translation\Services;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Blog\Models\AuthorProfile;
use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Models\BlogTag;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Translation\Jobs\TranslateContentFieldJob;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Models\Tour;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Field-level translation orchestration (Admin 2.0 Phase H, T-H03).
 *
 * Specifies which JSONB-localized field of which model is translatable
 * (and whether it is HTML), then, on every English save through the audited
 * actions, reconciles the English source hash against the
 * `translation_field_states` bookkeeping:
 *
 * - auto translations dispatch when the English source changed or the target
 *   is missing/failed/outdated;
 * - manual translations (admin-edited ES/IT) are preserved: flagging the state
 *   needs_review when the English source changed, NEVER overwriting them,
 *   unless the caller asks for `forceOverwriteManual`.
 */
class AutoTranslationService
{
    /**
     * Translatable fields per supported model. Boolean value marks the field
     * rich-text HTML (rich content) as opposed to plain text.
     *
     * @var array<class-string<Model>, array<string, bool>>
     */
    public const TRANSLATABLE_FIELDS = [
        BlogPost::class => [
            'title' => false,
            'excerpt' => false,
            'body' => true,
            'meta_title' => false,
            'meta_description' => false,
        ],
        StaticPage::class => [
            'title' => false,
            'body' => true,
            'meta_title' => false,
            'meta_description' => false,
        ],
        BlogCategory::class => [
            'name' => false,
            'description' => false,
        ],
        BlogTag::class => [
            'name' => false,
        ],
        AuthorProfile::class => [
            'display_name' => false,
            'bio' => false,
        ],
        Tour::class => [
            'title' => false,
            'description' => true,
        ],
    ];

    private array $fieldsByClass;

    public function __construct(private readonly GovernanceAuditService $audit)
    {
        $this->fieldsByClass = self::TRANSLATABLE_FIELDS;
    }

    /** Target locales handled by the pipeline. */
    public const TARGET_LOCALES = ['es', 'it'];

    public function translatableField(string $modelClass): array
    {
        return $this->fieldsByClass[ltrim($modelClass, '\\')] ?? [];
    }

    public function isHtmlField(string $modelClass, string $fieldName): bool
    {
        return $this->translatableField($modelClass)[$fieldName] ?? false;
    }

    /** SHA-256 of the normalized value of a source or target text. */
    public function normalizeHash(string $value): string
    {
        return hash('sha256', trim($value));
    }

    /** The English (source) value of a localized field, or null when blank. */
    public function englishValue(Model $model, string $fieldName): ?string
    {
        $value = $this->localizedValue($model, $fieldName, 'en');

        return $this->isBlank($value) ? null : trim((string) $value);
    }

    /**
     * Sync TranslationFieldState for each translatable field of a freshly
     * saved model, and dispatch jobs for anything that needs (re)translating.
     *
     * @param  array<string, array<string, bool>>  $manuallyEditedLocalesFields  e.g. ['es' => ['title' => true, 'body' => true]]
     * @param  bool  $forceOverwriteManual  Ignore the manual origin (admin opt-in "force re-translate")
     * @return array<string, array<string, string>> dispatched jobs grouped as [locale][field] = queued
     */
    public function syncAndDispatch(Model $model, ?User $actor = null, array $manuallyEditedLocalesFields = [], bool $forceOverwriteManual = false): array
    {
        $dispatched = [];

        $fields = $this->translatableField($model::class);

        if ($fields === []) {
            return $dispatched;
        }

        // The tour pipeline handles non-English sources and option texts as
        // one revision. The field engine continues to handle legacy tours.
        if ($model instanceof Tour && ! $model->supportsFieldTranslation()) {
            return $dispatched;
        }

        foreach (self::TARGET_LOCALES as $locale) {
            foreach ($fields as $fieldName => $isHtml) {
                $enValue = $this->localizedValue($model, $fieldName, 'en');

                if ($this->isBlank($enValue)) {
                    continue;
                }

                $enHash = $this->normalizeHash($enValue);
                $targetValue = $this->localizedValue($model, $fieldName, $locale);

                $state = TranslationFieldState::query()
                    ->where('translatable_type', $model->getMorphClass())
                    ->where('translatable_id', $model->getKey())
                    ->where('locale', $locale)
                    ->where('field_name', $fieldName)
                    ->first();

                if ($state === null) {
                    $state = new TranslationFieldState([
                        'translatable_type' => $model->getMorphClass(),
                        'translatable_id' => $model->getKey(),
                        'locale' => $locale,
                        'field_name' => $fieldName,
                        'origin' => TranslationFieldState::ORIGIN_AUTO,
                    ]);
                }

                $manuallyEdited = (bool) ($manuallyEditedLocalesFields[$locale][$fieldName] ?? false);

                if ($manuallyEdited && trim((string) $targetValue) === '') {
                    $state->status = TranslationFieldState::STATUS_MISSING;
                    $state->source_hash = $enHash;
                    $state->save();

                    continue;
                }

                if ($manuallyEdited && trim((string) $targetValue) !== '') {
                    $this->markManuallyEdited($state, $model, $actor, $enHash, $targetValue);

                    continue;
                }

                // English field unchanged, translation already delivered
                // (completed) or written by hand: leave it alone.
                if ($state->translated_source_hash === $enHash && trim((string) $targetValue) !== ''
                    && in_array($state->status, [
                        TranslationFieldState::STATUS_COMPLETED,
                        TranslationFieldState::STATUS_MANUALLY_EDITED,
                    ], true)) {
                    continue;
                }

                if ($state->origin === TranslationFieldState::ORIGIN_MANUAL && ! $forceOverwriteManual) {
                    // Never overwrite editor translations. Only flag when the
                    // English source drifted away from what they translated.
                    if ($state->translated_source_hash !== $enHash) {
                        $state->status = TranslationFieldState::STATUS_NEEDS_REVIEW;
                        $state->source_hash = $enHash;
                        $state->save();
                    }

                    continue;
                }

                if (in_array($state->status, [
                    TranslationFieldState::STATUS_TRANSLATING,
                    TranslationFieldState::STATUS_QUEUED,
                    TranslationFieldState::STATUS_FAILED,
                ], true) && $state->source_hash === $enHash && ! $forceOverwriteManual) {
                    continue;
                }

                $state->status = TranslationFieldState::STATUS_QUEUED;
                $state->source_hash = $enHash;

                if ($forceOverwriteManual) {
                    $state->origin = TranslationFieldState::ORIGIN_AUTO;
                }

                $state->save();

                TranslateContentFieldJob::dispatch(
                    $model->getMorphClass(),
                    $model->getKey(),
                    $locale,
                    $fieldName
                );

                $dispatched[$locale][$fieldName] = 'queued';
            }

            if ($model instanceof Tour) {
                $this->syncTourLocaleState($model, $locale, ! empty($dispatched[$locale]));
            }
        }

        return $dispatched;
    }

    /**
     * Snapshot translatable localized fields of a model (JSONB or relational Tour)
     * into `[$field => [$locale => $value]]` for `detectManualEdits()`.
     *
     * @return array<string, array<string, string|null>>
     */
    public function snapshotLocalizedAttributes(Model $model): array
    {
        $snapshot = [];

        foreach ($this->translatableField($model::class) as $fieldName => $isHtml) {
            $snapshot[$fieldName] = [];
            foreach (array_merge(['en'], self::TARGET_LOCALES) as $locale) {
                $snapshot[$fieldName][$locale] = $this->localizedValue($model, $fieldName, $locale);
            }
        }

        return $snapshot;
    }

    /**
     * Compare a model's previous ES/IT field values with the persisted state
     * to find fields an editor explicitly changed this save.
     *
     * @return array<string, array<string, bool>>
     */
    public function detectManualEdits(array $beforeAttributes, Model $after): array
    {
        $edits = [];

        foreach ($this->translatableField($after::class) as $fieldName => $isHtml) {
            foreach (self::TARGET_LOCALES as $locale) {
                $beforeValue = $this->localizedValueFrom($beforeAttributes, $fieldName, $locale);
                $afterValue = $this->localizedValue($after, $fieldName, $locale);

                if ($this->isBlank($afterValue)) {
                    if (! $this->isBlank($beforeValue)) {
                        $edits[$locale][$fieldName] = true;
                    }

                    continue;
                }

                if (trim((string) $beforeValue) !== trim((string) $afterValue)) {
                    $edits[$locale][$fieldName] = true;
                }
            }
        }

        return $edits;
    }

    private function markManuallyEdited(
        TranslationFieldState $state,
        Model $model,
        ?User $actor,
        string $enHash,
        string $targetValue,
    ): void {
        $state->origin = TranslationFieldState::ORIGIN_MANUAL;
        $state->status = TranslationFieldState::STATUS_MANUALLY_EDITED;
        $state->translated_source_hash = $enHash;
        $state->translated_hash = $this->normalizeHash($targetValue);
        $state->manually_edited_at = now();
        $state->manually_edited_by = $actor?->id;
        $state->last_error_code = null;
        $state->last_error_message = null;
        $state->save();

        $this->audit->log(
            actor: $actor,
            action: 'translation.manual_override',
            target: $model,
            before: null,
            after: [
                'locale' => $state->locale,
                'field_name' => $state->field_name,
            ],
            metadata: [
                'locale' => $state->locale,
                'field_name' => $state->field_name,
            ]
        );
    }

    private function isBlank(mixed $value): bool
    {
        return trim((string) $value) === '';
    }

    private function localizedValue(Model $model, string $field, string $locale): ?string
    {
        if ($model instanceof Tour) {
            $translations = $model->relationLoaded('translations')
                ? $model->translations
                : $model->translations()->get();
            $row = $translations->firstWhere('locale', $locale);
            $value = $row?->getAttribute($field);

            return is_string($value) ? $value : null;
        }

        $raw = $model->getAttribute($field);

        if (is_array($raw) && isset($raw[$locale]) && is_string($raw[$locale])) {
            return $raw[$locale];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function localizedValueFrom(array $attributes, string $field, string $locale): ?string
    {
        $raw = $attributes[$field] ?? null;

        if (is_array($raw) && isset($raw[$locale]) && is_string($raw[$locale])) {
            return $raw[$locale];
        }

        $byLocale = $attributes[$locale] ?? null;
        if (is_array($byLocale) && isset($byLocale[$field]) && is_string($byLocale[$field])) {
            return $byLocale[$field];
        }

        return null;
    }

    private function syncTourLocaleState(Tour $tour, string $locale, bool $hasQueuedFields): void
    {
        $enRow = $tour->translations()->where('locale', 'en')->first();
        if ($enRow === null || trim((string) $enRow->title) === '') {
            return;
        }

        $tourHash = app(TourTranslationService::class)->sourceHash($enRow, $tour);
        $targetRow = $tour->translations()->where('locale', $locale)->first();

        if ($hasQueuedFields) {
            // A synchronous queue may already have completed the jobs.
            $existing = TourTranslationState::query()
                ->where('tour_id', $tour->id)
                ->where('locale', $locale)
                ->first();

            if ($existing !== null && $existing->status === 'ready' && $existing->translated_hash === $tourHash) {
                return;
            }

            TourTranslationState::query()->updateOrCreate(
                ['tour_id' => $tour->id, 'locale' => $locale],
                [
                    'source_hash' => $tourHash,
                    'status' => $targetRow !== null ? 'stale' : 'pending',
                    'last_error_code' => null,
                ],
            );

            return;
        }

        if ($targetRow === null || trim((string) $targetRow->title) === '') {
            return;
        }

        $fields = array_keys($this->translatableField(Tour::class));
        $states = TranslationFieldState::query()
            ->where('translatable_type', $tour->getMorphClass())
            ->where('translatable_id', $tour->getKey())
            ->where('locale', $locale)
            ->get()
            ->keyBy('field_name');

        foreach ($fields as $fieldName) {
            $enText = $this->englishValue($tour, $fieldName);
            if ($enText === null) {
                continue;
            }

            $fieldState = $states->get($fieldName);
            if ($fieldState === null || ! in_array($fieldState->status, [
                TranslationFieldState::STATUS_COMPLETED,
                TranslationFieldState::STATUS_MANUALLY_EDITED,
            ], true)) {
                return;
            }
        }

        TourTranslationState::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'locale' => $locale],
            [
                'source_hash' => $tourHash,
                'translated_hash' => $tourHash,
                'status' => 'ready',
                'last_error_code' => null,
            ],
        );
    }
}
