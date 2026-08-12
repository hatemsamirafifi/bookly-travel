<?php

namespace App\Filament\Resources\StaticPageResource\Pages;

use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\StaticPage;
use App\Filament\Resources\StaticPageResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits + publishes a localized static page (Spec 013, US9, FR-015).
 *
 * The form exposes one field per locale for each localized JSONB column; this
 * page hydrates those fields from the record, then reassembles them into the
 * JSONB arrays and routes persistence + audit through UpdateStaticPageAction so
 * every save writes the immutable cms.* governance audit entry.
 */
class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    /**
     * Populate the per-locale form fields from the record's JSONB columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (StaticPage::LOCALES as $locale) {
            $data["title_{$locale}"] = $record->title[$locale] ?? '';
            $data["body_{$locale}"] = $record->body[$locale] ?? '';
            $data["meta_description_{$locale}"] = $record->meta_description[$locale] ?? '';
        }

        $data['slug'] = $record->slug;
        $data['status'] = $record->status;

        return $data;
    }

    /**
     * Reassemble the flat per-locale fields into JSONB arrays and persist via
     * the audited action (rather than a bare model save).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data, array $hidden = []): Model
    {
        $actor = auth()->user();
        $publish = ($data['status'] ?? null) === StaticPage::STATUS_PUBLISHED;

        $cmsData = [
            'slug' => $data['slug'] ?? $record->slug,
            'title' => $this->collectLocale($data, 'title'),
            'body' => $this->collectLocale($data, 'body'),
            'meta_description' => $this->collectLocale($data, 'meta_description'),
            'status' => $data['status'] ?? $record->status,
        ];

        return app(UpdateStaticPageAction::class)->execute($actor, $record, $cmsData, $publish);
    }

    /**
     * @return array<string, string>
     */
    private function collectLocale(array $data, string $field): array
    {
        $out = [];
        foreach (StaticPage::LOCALES as $locale) {
            $out[$locale] = $data["{$field}_{$locale}"] ?? '';
        }

        return $out;
    }
}
