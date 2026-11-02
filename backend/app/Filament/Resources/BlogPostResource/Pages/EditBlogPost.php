<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\BlogPostResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        $translateActions = $this->translationHeaderActions();

        return array_merge([
            Actions\Action::make('generate_preview')
                ->label('Preview Article')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function () {
                    /** @var BlogPost $record */
                    $record = $this->getRecord();
                    $generator = app(GeneratePreviewTokenAction::class);
                    $tokenData = $generator->execute($record->slug);
                    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                    $previewUrl = "{$frontendUrl}/en/blog/{$record->slug}/preview?token=" . rawurlencode($tokenData['token']);

                    Notification::make()
                        ->title('Preview Link Active (30 mins)')
                        ->body($previewUrl)
                        ->success()
                        ->send();
                }),
        ], $translateActions, [Actions\DeleteAction::make()]);
    }

    /**
     * Phase H: field-level translation enqueue/refresh actions.
     *
     * @return array<Actions\Action>
     */
    private function translationHeaderActions(): array
    {
        $dispatch = fn (bool $forceOverwriteManual): int => count(
            app(AutoTranslationService::class)->syncAndDispatch(
                $this->getRecord(),
                auth()->user(),
                forceOverwriteManual: $forceOverwriteManual,
            )
        );

        return [
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var BlogPost $record */
        $record = $this->getRecord();
        $locales = ['en', 'es', 'it'];

        foreach ($locales as $loc) {
            $data["title_{$loc}"] = $record->rawContentFor('title', $loc) ?? '';
            $data["excerpt_{$loc}"] = $record->rawContentFor('excerpt', $loc) ?? '';
            $data["body_{$loc}"] = $record->rawContentFor('body', $loc) ?? '';
            $data["meta_title_{$loc}"] = $record->rawContentFor('meta_title', $loc) ?? '';
            $data["meta_description_{$loc}"] = $record->rawContentFor('meta_description', $loc) ?? '';
        }

        $data['related_tours'] = $record->relatedTours()->orderBy('blog_post_tours.sort_order', 'asc')->get()->map(function ($tour) {
            return [
                'tour_id' => $tour->id,
                'sort_order' => $tour->pivot->sort_order ?? 0,
            ];
        })->toArray();

        $data['loaded_lock_version'] = (int) $record->lock_version;

        return $data;
    }

    /**
     * Persists via the audited action, guarding against concurrent edits with
     * optimistic locking: the hidden `loaded_lock_version` is compared to the
     * fresh `lock_version` before persisting, failing validation when another
     * administrator saved first.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();

        /** @var BlogPost $record */
        if (isset($data['loaded_lock_version'])
            && (int) $data['loaded_lock_version'] !== (int) $record->fresh()->lock_version) {
            throw ValidationException::withMessages([
                'lock_version' => ['This record was modified by another administrator. Please reload and try again.'],
            ]);
        }

        unset($data['loaded_lock_version']);

        $localized = $this->extractLocalizedFields($record, $data);

        $data = array_merge($data, $localized);

        return app(UpdateBlogPostAction::class)->execute($actor, $data, $record);
    }

    /**
     * Convert flat `field_locale` form keys into localized maps.
     *
     * Empty ES/IT inputs must never overwrite an existing translation (and must
     * never inherit the English text that previously leaked into the form).
     * English is always written so the editor can intentionally clear it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function extractLocalizedFields(BlogPost $record, array &$data): array
    {
        $fieldMap = [
            'title' => 'title',
            'excerpt' => 'excerpt',
            'body' => 'body',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
        ];

        $localized = [];

        foreach ($fieldMap as $field => $prefix) {
            $values = $record->{$field} ?? [];
            if (! is_array($values)) {
                $values = [];
            }

            foreach (BlogPost::LOCALES as $locale) {
                $key = "{$prefix}_{$locale}";

                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];
                unset($data[$key]);

                if ($locale === 'en') {
                    $values[$locale] = is_string($value) ? $value : '';

                    continue;
                }

                if (is_string($value) && trim($value) !== '') {
                    $values[$locale] = $value;
                }
            }

            $localized[$field] = $values;
        }

        return $localized;
    }
}
