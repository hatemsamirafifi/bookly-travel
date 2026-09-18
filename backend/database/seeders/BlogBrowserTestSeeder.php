<?php

namespace Database\Seeders;

use App\Domains\Blog\Jobs\InvalidateBlogCacheJob;
use App\Domains\Blog\Models\AuthorProfile;
use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogBrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        $cityGuides = BlogCategory::updateOrCreate(
            ['slug' => 'city-guides'],
            [
                'name' => 'City Guides',
                'description' => 'Comprehensive destination breakdowns and insider walks.',
                'is_active' => true,
                'display_order' => 1,
            ],
        );

        $foodAndWine = BlogCategory::updateOrCreate(
            ['slug' => 'food-and-wine'],
            [
                'name' => 'Food & Wine',
                'description' => 'Local food and wine recommendations.',
                'is_active' => true,
                'display_order' => 2,
            ],
        );

        $author = User::updateOrCreate(
            ['email' => 'editor@bookly.test'],
            [
                'name' => 'Elena Rossi',
                'password' => 'Password123!',
                'role' => 'admin',
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        AuthorProfile::updateOrCreate(
            ['user_id' => $author->id],
            [
                'display_name' => [
                    'en' => 'Elena Rossi',
                    'es' => 'Elena Rossi',
                    'it' => 'Elena Rossi',
                ],
                'bio' => [
                    'en' => 'Travel Writer',
                    'es' => 'Escritora de Viajes',
                    'it' => 'Scrittrice di Viaggi',
                ],
                'avatar_url' => null,
            ],
        );

        $this->upsertPost('florence-walk', $author, $cityGuides, [
            'title' => [
                'en' => 'Florence Walking Guide',
                'es' => 'Guía a Pie de Florencia',
                'it' => 'Guida a Piedi di Firenze',
            ],
            'body' => [
                'en' => '<p>Enjoy Florence.</p>',
                'es' => '<p>Disfruta de Florencia.</p>',
                'it' => '<p>Goditi Firenze.</p>',
            ],
            'excerpt' => [
                'en' => 'Best routes in Florence.',
                'es' => 'Las mejores rutas de Florencia.',
                'it' => 'I migliori percorsi a Firenze.',
            ],
            'is_featured' => true,
            'published_at' => '2026-05-12 10:00:00',
        ]);

        $this->upsertPost('hidden-gems-florence', $author, $cityGuides, [
            'title' => array_fill_keys(BlogPost::LOCALES, 'Hidden Gems in Florence'),
            'body' => array_fill_keys(BlogPost::LOCALES, '<p>Florence is full of hidden wonders.</p><h2>The Oltrarno district</h2><p>Stroll through the artisan streets.</p>'),
            'excerpt' => array_fill_keys(BlogPost::LOCALES, 'Beyond the Uffizi: secret spots in Florence.'),
            'is_featured' => false,
            'published_at' => '2026-05-10 10:00:00',
        ]);

        $this->upsertPost('top-10-gelato-spots', $author, $foodAndWine, [
            'title' => array_fill_keys(BlogPost::LOCALES, 'Top 10 Gelato Spots in Rome'),
            'body' => array_fill_keys(BlogPost::LOCALES, '<p>The creamiest gelato in Italy, tested by local food writers.</p>'),
            'excerpt' => array_fill_keys(BlogPost::LOCALES, 'The creamiest gelato in Italy tested by foodies.'),
            'is_featured' => false,
            'published_at' => '2026-05-08 10:00:00',
        ]);

        $this->upsertPost('seo-test-article', $author, $cityGuides, [
            'title' => array_fill_keys(BlogPost::LOCALES, 'SEO Test Article'),
            'body' => array_fill_keys(BlogPost::LOCALES, '<p>Article body content.</p>'),
            'excerpt' => array_fill_keys(BlogPost::LOCALES, 'Testing SEO meta tags and JSON-LD structured data.'),
            'meta_title' => array_fill_keys(BlogPost::LOCALES, 'Custom SEO Meta Title'),
            'meta_description' => array_fill_keys(BlogPost::LOCALES, 'Custom SEO description for testing.'),
            'is_featured' => false,
            'published_at' => '2026-05-06 10:00:00',
        ]);

        $this->upsertPost('archived-guide', $author, $cityGuides, [
            'title' => array_fill_keys(BlogPost::LOCALES, 'Archived Guide'),
            'body' => array_fill_keys(BlogPost::LOCALES, '<p>This guide has been archived.</p>'),
            'excerpt' => array_fill_keys(BlogPost::LOCALES, 'Archived travel guide.'),
            'status' => BlogPost::STATUS_ARCHIVED,
            'published_at' => '2026-04-01 10:00:00',
        ]);

        (new InvalidateBlogCacheJob)->handle();
    }

    /** @param array<string, mixed> $overrides */
    private function upsertPost(string $slug, User $author, BlogCategory $category, array $overrides): void
    {
        BlogPost::updateOrCreate(
            ['slug' => $slug],
            array_merge([
                'status' => BlogPost::STATUS_PUBLISHED,
                'title' => array_fill_keys(BlogPost::LOCALES, $slug),
                'body' => array_fill_keys(BlogPost::LOCALES, '<p>Travel guide.</p>'),
                'excerpt' => array_fill_keys(BlogPost::LOCALES, 'Travel guide.'),
                'meta_title' => null,
                'meta_description' => null,
                'cover_image_url' => null,
                'is_featured' => false,
                'scheduled_at' => null,
                'author_id' => $author->id,
                'blog_category_id' => $category->id,
            ], $overrides),
        );
    }
}
