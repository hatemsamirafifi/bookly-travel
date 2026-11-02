<?php

namespace App\Filament\Resources\AuthorProfileResource\Pages;

use App\Domains\Blog\Models\AuthorProfile;
use App\Filament\Resources\AuthorProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAuthorProfile extends CreateRecord
{
    protected static string $resource = AuthorProfileResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data = AuthorProfileResource::mergeLocalizedFields($data);

        return AuthorProfile::create($data);
    }
}
