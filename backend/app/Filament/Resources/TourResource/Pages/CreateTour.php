<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\TourResource;
use App\Models\Tour;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $submittedStatus = isset($this->data['status']) && is_string($this->data['status'])
            ? $this->data['status']
            : null;

        app(TourPublicationGuardService::class)->assertGeneralCreateRequiresDraft($submittedStatus);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        unset($data['published_at']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Tour $record */
        $record = $this->getRecord()->fresh('translations');

        app(AutoTranslationService::class)->syncAndDispatch($record, auth()->user());
    }
}
