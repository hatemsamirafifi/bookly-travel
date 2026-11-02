<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Translation\Services\AutoTranslationService;
use App\Filament\Resources\TourResource;
use App\Models\Tour;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    protected function afterCreate(): void
    {
        /** @var Tour $record */
        $record = $this->getRecord()->fresh('translations');

        app(AutoTranslationService::class)->syncAndDispatch($record, auth()->user());
    }
}
