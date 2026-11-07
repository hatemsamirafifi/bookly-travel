<?php

namespace App\Filament\Resources\StaticPageResource\Pages;

use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Edits + publishes a localized static page (Spec 013, US9, FR-015).
 *
 * The form exposes one field per locale for each localized JSONB column; this
 * page hydrates those fields from the record, then reassembles them into the
 * JSONB arrays and routes persistence + audit through UpdateStaticPageAction so
 * every save writes the immutable cms.* governance audit entry.
 */
class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        $dispatch = fn (bool $forceOverwriteManual): int => count(
            app(AutoTranslationService::class)->syncAndDispatch(
                $this->getRecord(),
                auth()->user(),
                forceOverwriteManual: $forceOverwriteManual,
            )
        );

        return [
            Actions\Action::make('preview_page')
                ->label('Preview Page')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function () {
                    /** @var StaticPage $record */
                    $record = $this->getRecord();
                    $tokenData = app(GeneratePreviewTokenAction::class)->execute($record->slug);
                    $previewUrl = config('app.frontend_url', 'http://localhost:3000')
                        . "/en/{$record->slug}?preview_token=" . rawurlencode($tokenData['token']);

                    Notification::make()
                        ->title('Preview Link Active (30 mins)')
                        ->body($previewUrl)
                        ->success()
                        ->send();
                }),
            Actions\Action::make('translate_missing_fields')
                ->label('Translate Missing Fields')
                ->icon('heroicon-o-language')
                ->color('gray')
                ->action(fn () => $dispatch(false)),
            Actions\Action::make('retranslate_outdated_fields')
                ->label('Retranslate Outdated Fields')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $dispatch(false)),
            Actions\Action::make('force_retranslate_all')
                ->label('Force Retranslate All')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $dispatch(true)),
        ];
    }

    /**
     * Populate the per-locale form fields from the record's JSONB columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (StaticPage::LOCALES as $locale) {
            // rawContentFor never falls back to English: a page without an ES/IT
            // translation shows the admin an empty ES/IT field, never the EN text.
            $data["title_{$locale}"] = (string) ($record->rawContentFor('title', $locale) ?? '');
            $data["body_{$locale}"] = (string) ($record->rawContentFor('body', $locale) ?? '');
            $data["meta_description_{$locale}"] = (string) ($record->rawContentFor('meta_description', $locale) ?? '');
            $data["meta_title_{$locale}"] = (string) ($record->rawContentFor('meta_title', $locale) ?? '');
        }

        $data['slug'] = $record->slug;
        $data['status'] = $record->status;
        $data['canonical_url'] = $record->canonical_url;
        $data['scheduled_at'] = $record->scheduled_at;
        $data['loaded_lock_version'] = (int) $record->lock_version;

        return $data;
    }

    /**
     * Reassemble the flat per-locale fields into JSONB arrays and persist via
     * the audited action (rather than a bare model save).
     *
     * Guards against concurrent edits with optimistic locking: the hidden
     * `loaded_lock_version` is compared to the fresh `lock_version` before
     * persisting, failing validation when another administrator saved first.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data, array $hidden = []): Model
    {
        $actor = auth()->user();
        /** @var StaticPage $record */
        if (isset($data['loaded_lock_version'])
            && (int) $data['loaded_lock_version'] !== (int) $record->fresh()->lock_version) {
            throw ValidationException::withMessages([
                'lock_version' => ['This record was modified by another administrator. Please reload and try again.'],
            ]);
        }

        unset($data['loaded_lock_version']);

        $publish = ($data['status'] ?? null) === StaticPage::STATUS_PUBLISHED;

        $cmsData = [
            'slug' => $data['slug'] ?? $record->slug,
            'title' => $this->extractLocalizedField($record, $data, 'title'),
            'body' => $this->extractLocalizedField($record, $data, 'body'),
            'meta_description' => $this->extractLocalizedField($record, $data, 'meta_description'),
            'meta_title' => $this->extractLocalizedField($record, $data, 'meta_title'),
            'canonical_url' => $data['canonical_url'] ?? $record->canonical_url,
            'scheduled_at' => $data['scheduled_at'] ?? $record->scheduled_at,
            'status' => $data['status'] ?? $record->status,
        ];

        return app(UpdateStaticPageAction::class)->execute($actor, $record, $cmsData, $publish);
    }

    /**
     * Explicitly clear all localized fields for a target non-English locale.
     */
    public function clearLocaleTranslation(string $locale): void
    {
        if (! in_array($locale, ['es', 'it'], true)) {
            return;
        }

        /** @var StaticPage $record */
        $record = $this->getRecord();
        $actor = auth()->user();

        app(UpdateStaticPageAction::class)->execute($actor, $record, [
            'title' => [$locale => null],
            'body' => [$locale => null],
            'meta_description' => [$locale => null],
            'meta_title' => [$locale => null],
        ]);
    }

    /**
     * Extract per-locale values from Filament form data while preserving existing
     * non-English translations when the ES/IT tabs are left blank.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function extractLocalizedField(StaticPage $record, array $data, string $field): array
    {
        $values = $record->{$field} ?? [];
        if (! is_array($values)) {
            $values = [];
        }

        foreach (StaticPage::LOCALES as $locale) {
            $key = "{$field}_{$locale}";

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($locale === 'en') {
                $values['en'] = is_string($value) ? $value : '';

                continue;
            }

            if (is_string($value) && trim(strip_tags($value)) !== '') {
                $values[$locale] = $value;
            } elseif (isset($values[$locale]) && trim(strip_tags((string) $values[$locale])) === '') {
                unset($values[$locale]);
            }
        }

        return $values;
    }
}
