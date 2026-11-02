<?php

namespace App\Filament\Resources\AuthorProfileResource\Pages;

use App\Filament\Resources\AuthorProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListAuthorProfiles extends ListRecords
{
    protected static string $resource = AuthorProfileResource::class;
}
