<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Filament\Resources\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        $locales = ['en', 'es', 'it'];
        $title = [];
        $excerpt = [];
        $body = [];
        $metaTitle = [];
        $metaDescription = [];

        foreach ($locales as $loc) {
            if (isset($data["title_{$loc}"])) {
                $title[$loc] = $data["title_{$loc}"];
                unset($data["title_{$loc}"]);
            }
            if (isset($data["excerpt_{$loc}"])) {
                $excerpt[$loc] = $data["excerpt_{$loc}"];
                unset($data["excerpt_{$loc}"]);
            }
            if (isset($data["body_{$loc}"])) {
                $body[$loc] = $data["body_{$loc}"];
                unset($data["body_{$loc}"]);
            }
            if (isset($data["meta_title_{$loc}"])) {
                $metaTitle[$loc] = $data["meta_title_{$loc}"];
                unset($data["meta_title_{$loc}"]);
            }
            if (isset($data["meta_description_{$loc}"])) {
                $metaDescription[$loc] = $data["meta_description_{$loc}"];
                unset($data["meta_description_{$loc}"]);
            }
        }

        $data['title'] = $title;
        $data['excerpt'] = $excerpt;
        $data['body'] = $body;
        $data['meta_title'] = $metaTitle;
        $data['meta_description'] = $metaDescription;

        return app(UpdateBlogPostAction::class)->execute($actor, $data, null);
    }
}
