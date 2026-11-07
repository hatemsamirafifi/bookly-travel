<?php

namespace App\Filament\Resources\AuthorProfileResource\Pages;

use App\Domains\Blog\Models\AuthorProfile;
use App\Filament\Resources\AuthorProfileResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAuthorProfile extends EditRecord
{
    protected static string $resource = AuthorProfileResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (['display_name', 'bio'] as $field) {
            foreach (AuthorProfileResource::LOCALES as $locale) {
                $value = is_array($record->{$field}) ? ($record->{$field}[$locale] ?? null) : null;

                $data["{$field}_{$locale}"] = is_string($value) && trim($value) !== '' ? $value : '';
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AuthorProfile $record */
        $existing = [
            'display_name' => is_array($record->display_name) ? $record->display_name : [],
            'bio' => is_array($record->bio) ? $record->bio : [],
        ];

        $data = AuthorProfileResource::mergeLocalizedFields($data, $existing);

        $record->update($data);

        return $record;
    }
}
