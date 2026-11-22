<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Filament\Resources\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * EGM-003 owner policy: tours are authored by partners in the partner
     * portal, and the general administration form renders no canonical-authoring
     * control. Creating a tour here is therefore refused server-side instead of
     * exposing a false functioning editor (design v3 §4), which also keeps
     * every crafted-lifecycle creation attempt refused.
     */
    protected function beforeValidate(): void
    {
        app(TourPublicationGuardService::class)->refuseGeneralTourCreation($this->data);
    }
}
