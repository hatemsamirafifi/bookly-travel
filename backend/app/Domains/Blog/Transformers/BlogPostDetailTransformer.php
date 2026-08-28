<?php

namespace App\Domains\Blog\Transformers;

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Search\Transformers\TourCardTransformer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlogPostDetailTransformer
{
    public function __construct(
        protected BlogPostTransformer $cardTransformer,
        protected TourCardTransformer $tourCardTransformer
    ) {}

    public function transform(BlogPost $post, string $locale, ?Collection $relatedPosts = null): array
    {
        $hasRequestedLocale = $this->hasLocaleContent($post, $locale);
        $translationWarning = (! $hasRequestedLocale && $locale !== 'en') ? 'partial_translation' : null;

        $title = $post->contentFor('title', $locale) ?? '';
        $body = $post->contentFor('body', $locale) ?? '';
        $excerpt = $post->contentFor('excerpt', $locale);
        if (blank($excerpt)) {
            $excerpt = Str::limit(strip_tags($body), 160);
        }

        $authorProfile = $post->relationLoaded('author') && $post->author?->relationLoaded('authorProfile')
            ? $post->author->authorProfile
            : $post->authorProfile;

        $authorDisplayName = $authorProfile?->contentFor('display_name', $locale)
            ?? $post->author->name
            ?? 'Bookly Editorial';

        $authorBio = $authorProfile?->contentFor('bio', $locale);
        $authorAvatarUrl = $authorProfile?->avatar_url;

        $seo = $this->buildSeoMetadata($post, $locale, $title, $excerpt);

        $relatedToursData = [];
        if ($post->relationLoaded('relatedTours')) {
            $relatedToursData = $post->relatedTours
                ->filter(fn ($tour) => $tour->status === 'published')
                ->take(6)
                ->map(fn ($tour) => $this->tourCardTransformer->transform($tour, $locale))
                ->values()
                ->all();
        }

        $relatedPostsData = [];
        if ($relatedPosts) {
            $relatedPostsData = $relatedPosts
                ->take(3)
                ->map(fn (BlogPost $relPost) => $this->cardTransformer->transform($relPost, $locale))
                ->values()
                ->all();
        }

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $title,
            'body' => $body,
            'excerpt' => $excerpt,
            'cover_image_url' => $post->cover_image_url,
            'cover_image_blur' => null,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'is_featured' => (bool) $post->is_featured,
            'reading_time' => $post->readingTime($locale),
            'translation_warning' => $translationWarning,
            'category' => $post->category ? [
                'slug' => $post->category->slug,
                'name' => $post->category->name,
            ] : null,
            'author' => [
                'display_name' => $authorDisplayName,
                'bio' => $authorBio,
                'avatar_url' => $authorAvatarUrl,
            ],
            'seo' => $seo,
            'related_tours' => $relatedToursData,
            'related_posts' => $relatedPostsData,
        ];
    }

    protected function buildSeoMetadata(BlogPost $post, string $locale, string $title, ?string $excerpt): array
    {
        $metaTitleCustom = $post->contentFor('meta_title', $locale);
        $metaDescCustom = $post->contentFor('meta_description', $locale);

        $metaTitle = ! blank($metaTitleCustom)
            ? $metaTitleCustom
            : ($title !== '' ? "{$title} | Bookly Travel Insights" : 'Bookly Travel Insights');

        $metaDescription = ! blank($metaDescCustom)
            ? $metaDescCustom
            : ($excerpt ?? 'Explore travel guides, tips, and insights on Bookly.');

        $baseUrl = config('app.url', 'https://bookly.com');
        $canonicalUrl = "{$baseUrl}/{$locale}/blog/{$post->slug}";

        $hreflang = [];
        $locales = config('app.supported_locales', BlogPost::LOCALES);
        foreach ($locales as $loc) {
            $hreflang[$loc] = "{$baseUrl}/{$loc}/blog/{$post->slug}";
        }

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'hreflang' => $hreflang,
        ];
    }

    private function hasLocaleContent(BlogPost $post, string $locale): bool
    {
        $titles = $post->title ?? [];
        $bodies = $post->body ?? [];

        return ! empty($titles[$locale]) || ! empty($bodies[$locale]);
    }
}
