<?php

namespace App\Filament\Resources\ContentRevisionResource\Pages;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Services\ContentRevisionService;
use App\Filament\Resources\ContentRevisionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentRevision extends ViewRecord
{
    protected static string $resource = ContentRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('restore')
                ->label('Restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->action(function (ContentRevision $record): void {
                    app(ContentRevisionService::class)->restore($record, auth()->user());
                }),
        ];
    }
}
