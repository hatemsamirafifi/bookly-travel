<?php

namespace App\Filament\Pages;

use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Models\Category;
use App\Models\Tour;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class DemoContentManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Demo Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Demo Content Manager';

    protected static ?string $title = 'Demo Content Management';

    protected static ?string $slug = 'demo-content';

    protected static string $view = 'filament.pages.demo-content-manager';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate / Seed Demo Content')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Demo Content')
                ->modalDescription('Demo regeneration is temporarily unavailable while its publishing workflow is being reviewed.')
                ->action(function () {
                    // EGM-003 remains unresolved. Demo seeders bypass both
                    // publication approval and governed translation writes.
                    abort(403, 'Demo content generation is temporarily disabled; tours are published only through the governed approval workflow.');
                }),
            Action::make('viewSite')
                ->label('View Public Site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(config('app.frontend_url', 'http://localhost:3001') . '/en')
                ->openUrlInNewTab(),
            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    Cache::flush();
                    Notification::make()
                        ->title('Cache Cleared')
                        ->body('Application cache and API responses cleared.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3001');

        $tours = Tour::with(['translations', 'category'])->latest()->get()->map(function (Tour $tour) use ($frontendUrl) {
            $enTitle = $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug;

            return [
                'id' => $tour->id,
                'slug' => $tour->slug,
                'title' => $enTitle,
                'category' => $tour->category?->name ?? 'Uncategorized',
                'location' => $tour->location,
                'price' => Tour::formatPrice($tour->price_amount, 'EUR'),
                'status' => $tour->status,
                'cover_image_url' => $tour->cover_image_url ? (str_starts_with($tour->cover_image_url, 'http') || str_starts_with($tour->cover_image_url, '/') ? $tour->cover_image_url : '/storage/' . $tour->cover_image_url) : null,
                'edit_url' => url("/admin/tours/{$tour->id}/edit"),
                'site_url' => "{$frontendUrl}/en/tours/{$tour->slug}",
            ];
        });

        $blogPosts = BlogPost::with(['author', 'category'])->latest()->get()->map(function (BlogPost $post) use ($frontendUrl) {
            return [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->contentFor('title', 'en') ?? $post->slug,
                'category' => $post->category?->name ?? 'Uncategorized',
                'author' => $post->author?->name ?? 'Editorial',
                'status' => $post->status,
                'cover_image_url' => $post->cover_image_url ? (str_starts_with($post->cover_image_url, 'http') || str_starts_with($post->cover_image_url, '/') ? $post->cover_image_url : '/storage/' . $post->cover_image_url) : null,
                'edit_url' => url("/admin/blog-posts/{$post->id}/edit"),
                'site_url' => "{$frontendUrl}/en/blog/{$post->slug}",
            ];
        });

        $categories = Category::withCount('tours')->orderBy('display_order')->get()->map(function (Category $cat) use ($frontendUrl) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'type' => 'Tour Category',
                'items_count' => $cat->tours_count,
                'image_url' => $cat->image_url ? (str_starts_with($cat->image_url, 'http') || str_starts_with($cat->image_url, '/') ? $cat->image_url : '/storage/' . $cat->image_url) : null,
                'edit_url' => url("/admin/categories/{$cat->id}/edit"),
                'site_url' => "{$frontendUrl}/en/categories/{$cat->slug}",
            ];
        });

        $blogCategories = BlogCategory::withCount('posts')->orderBy('display_order')->get()->map(function (BlogCategory $cat) use ($frontendUrl) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'type' => 'Blog Category',
                'items_count' => $cat->posts_count,
                'image_url' => $cat->image_url ? (str_starts_with($cat->image_url, 'http') || str_starts_with($cat->image_url, '/') ? $cat->image_url : '/storage/' . $cat->image_url) : null,
                'edit_url' => url("/admin/blog-categories/{$cat->id}/edit"),
                'site_url' => "{$frontendUrl}/en/blog/category/{$cat->slug}",
            ];
        });

        return [
            'stats' => [
                'tours_count' => $tours->count(),
                'posts_count' => $blogPosts->count(),
                'categories_count' => $categories->count() + $blogCategories->count(),
                'images_count' => $tours->whereNotNull('cover_image_url')->count() + $blogPosts->whereNotNull('cover_image_url')->count() + $categories->whereNotNull('image_url')->count() + $blogCategories->whereNotNull('image_url')->count(),
            ],
            'tours' => $tours,
            'blogPosts' => $blogPosts,
            'categories' => $categories,
            'blogCategories' => $blogCategories,
        ];
    }
}
