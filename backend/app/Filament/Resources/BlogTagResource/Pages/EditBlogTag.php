<?php

namespace App\Filament\Resources\BlogTagResource\Pages;

use App\Domains\Blog\Models\BlogTag;
use App\Filament\Resources\BlogTagResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBlogTag extends EditRecord
{
    protected static string $resource = BlogTagResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (BlogTag::LOCALES as $locale) {
            $data["name_{$locale}"] = $record->rawNameFor($locale) ?? '';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogTag $record */
        $existing = is_array($record->name) ? $record->name : [];

        $data = BlogTagResource::mergeLocalizedNames($data, $existing);

        $record->update($data);

        return $record;
    }
}
