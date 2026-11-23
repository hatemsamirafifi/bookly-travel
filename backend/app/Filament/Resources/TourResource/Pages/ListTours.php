<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    /**
     * EGM-003: tour creation is a partner operation, so the administration list
     * offers no create action. Review and governance actions live on the records
     * themselves.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
