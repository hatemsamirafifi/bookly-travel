<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Filament\Resources\BlogPostResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_preview')
                ->label('Preview Article')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function () {
                    /** @var BlogPost $record */
                    $record = $this->getRecord();
                    $generator = app(GeneratePreviewTokenAction::class);
                    $tokenData = $generator->execute($record->slug);
                    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                    $previewUrl = "{$frontendUrl}/en/blog/{$record->slug}/preview?token={$tokenData['token']}";

                    Notification::make()
                        ->title('Preview Link Active (30 mins)')
                        ->body($previewUrl)
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var BlogPost $record */
        $record = $this->getRecord();
        $locales = ['en', 'es', 'it'];

        foreach ($locales as $loc) {
            $data["title_{$loc}"] = $record->contentFor('title', $loc) ?? '';
            $data["excerpt_{$loc}"] = $record->contentFor('excerpt', $loc) ?? '';
            $data["body_{$loc}"] = $record->contentFor('body', $loc) ?? '';
            $data["meta_title_{$loc}"] = $record->contentFor('meta_title', $loc) ?? '';
            $data["meta_description_{$loc}"] = $record->contentFor('meta_description', $loc) ?? '';
        }

        $data['related_tours'] = $record->relatedTours()->orderBy('blog_post_tours.sort_order', 'asc')->get()->map(function ($tour) {
            return [
                'tour_id' => $tour->id,
                'sort_order' => $tour->pivot->sort_order ?? 0,
            ];
        })->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
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

        /** @var BlogPost $record */
        return app(UpdateBlogPostAction::class)->execute($actor, $data, $record);
    }
}
