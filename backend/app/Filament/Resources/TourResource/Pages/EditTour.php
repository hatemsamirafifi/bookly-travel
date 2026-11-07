<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\TourResource;
use App\Models\Tour;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    /**
     * @var array<string, array<string, string|null>>
     */
    protected array $beforeTranslationsSnapshot = [];

    protected function getHeaderActions(): array
    {
        $dispatch = fn (bool $forceOverwriteManual): int => count(
            app(AutoTranslationService::class)->syncAndDispatch(
                $this->getRecord()->fresh('translations'),
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
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var Tour $record */
        $record = $this->getRecord()->fresh('translations');

        $this->beforeTranslationsSnapshot = app(AutoTranslationService::class)
            ->snapshotLocalizedAttributes($record);
    }

    protected function afterSave(): void
    {
        /** @var Tour $record */
        $record = $this->getRecord()->fresh('translations');
        $service = app(AutoTranslationService::class);

        $manualEdits = $service->detectManualEdits($this->beforeTranslationsSnapshot, $record);

        $service->syncAndDispatch($record, auth()->user(), $manualEdits);
    }
}
