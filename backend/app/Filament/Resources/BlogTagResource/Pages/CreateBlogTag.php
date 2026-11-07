<?php

namespace App\Filament\Resources\BlogTagResource\Pages;

use App\Filament\Resources\BlogTagResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBlogTag extends CreateRecord
{
    protected static string $resource = BlogTagResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $data = BlogTagResource::mergeLocalizedNames($data);

        return BlogTagResource::getModel()::create($data);
    }
}
