<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\TourResource;
use App\Models\Tour;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    // Relationship writes happen before the main update in Filament's save flow.
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array<string, array<string, string|null>>
     */
    protected array $beforeTranslationsSnapshot = [];

    /** Lock before getState() can persist any relationship changes. */
    protected function beforeValidate(): void
    {
        $locked = app(TourPublicationGuardService::class)->lockTourForMutation(
            $this->getRecord(), $this->data['partner_id'] ?? null,
        );
        Gate::forUser(User::find(auth()->id()))->authorize('update', $locked);
        $this->record = $locked;
        $this->form->model($locked);

        $submittedStatus = isset($this->data['status']) && is_string($this->data['status'])
            ? $this->data['status']
            : null;

        app(TourPublicationGuardService::class)
            ->assertGeneralSaveKeepsCurrentStatus($submittedStatus, $locked);
    }

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

        // Temporary EGM-003 guard. Validate before translation dispatches so
        // destructive edits roll back with the rest of the form transaction.
        app(TourPublicationGuardService::class)
            ->assertPublishedTourEditIntegrity($this->getRecord()->fresh(['translations', 'partnerRecord']));

        $manualEdits = $service->detectManualEdits($this->beforeTranslationsSnapshot, $record);

        $service->syncAndDispatch($record, auth()->user(), $manualEdits);
    }
}
