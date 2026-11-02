<?php

namespace App\Filament\Resources\StaticPageResource\Pages;

use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\StaticPage;
use App\Filament\Resources\StaticPageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a localized static page through the audited action.
 *
 * Every save of a StaticPage (create included) must write its immutable
 * `cms.*` governance audit entry, so creation routes through the same
 * UpdateStaticPageAction as edits with `publish: true` handled by the
 * publish flow.
 */
class CreateStaticPage extends CreateRecord
{
    protected static string $resource = StaticPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        $page = new StaticPage;
        $page->slug = $data['slug'] ?? '';
        $page->status = $data['status'] ?? StaticPage::STATUS_DRAFT;

        $publish = $page->status === StaticPage::STATUS_PUBLISHED;

        $cmsData = [
            'slug' => $page->slug,
            'status' => $page->status,
            'title' => $this->collectLocale($data, 'title'),
            'body' => $this->collectLocale($data, 'body'),
            'meta_description' => $this->collectLocale($data, 'meta_description'),
            'meta_title' => $this->collectLocale($data, 'meta_title'),
            'canonical_url' => $data['canonical_url'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ];

        return app(UpdateStaticPageAction::class)->execute($actor, $page, $cmsData, $publish);
    }

    /**
     * @param  array<string, mixed>  $data
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
