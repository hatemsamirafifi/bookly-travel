<?php

namespace App\Domains\Blog\Transformers;

use App\Domains\Blog\Models\BlogPost;
use Illuminate\Support\Str;

class BlogPostTransformer
{
    public function transform(BlogPost $post, string $locale): array
    {
        $hasRequestedLocale = $this->hasLocaleContent($post, $locale);
        $translationWarning = (! $hasRequestedLocale && $locale !== 'en') ? 'partial_translation' : null;

        $title = $post->contentFor('title', $locale) ?? '';
        $excerpt = $post->contentFor('excerpt', $locale);
        if (blank($excerpt)) {
            $body = $post->contentFor('body', $locale) ?? '';
            $excerpt = Str::limit(strip_tags($body), 160);
        }

        $authorProfile = $post->relationLoaded('author') && $post->author?->relationLoaded('authorProfile')
            ? $post->author->authorProfile
            : $post->authorProfile;

        $authorDisplayName = $authorProfile?->contentFor('display_name', $locale)
            ?? $post->author->name
            ?? 'Bookly Editorial';

        $authorAvatarUrl = $authorProfile?->avatar_url;

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'cover_image_url' => $post->cover_image_url,
            'cover_image_blur' => null,
            'published_at' => $post->published_at?->toIso8601String(),
            'is_featured' => (bool) $post->is_featured,
            'reading_time' => $post->readingTime($locale),
            'translation_warning' => $translationWarning,
            'category' => $post->category ? [
                'slug' => $post->category->slug,
                'name' => $post->category->name,
            ] : null,
            'author' => [
                'display_name' => $authorDisplayName,
                'avatar_url' => $authorAvatarUrl,
            ],
        ];
    }

    private function hasLocaleContent(BlogPost $post, string $locale): bool
    {
        $titles = $post->title ?? [];
        $bodies = $post->body ?? [];

        return ! empty($titles[$locale]) || ! empty($bodies[$locale]);
    }
}
