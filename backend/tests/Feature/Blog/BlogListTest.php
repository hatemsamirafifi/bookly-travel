<?php

declare(strict_types=1);

use App\Domains\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('returns 200 with paginated blog posts and metadata', function () {
    $category = makeBlogCategory();
    makeBlogPost(['blog_category_id' => $category->id]);
    makeBlogPost(['blog_category_id' => $category->id]);

    $response = $this->getJson('/api/public/blog?locale=en');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'cover_image_url',
                    'cover_image_blur',
                    'published_at',
                    'is_featured',
                    'reading_time',
                    'translation_warning',
                    'category' => ['slug', 'name'],
                    'author' => ['display_name', 'avatar_url'],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('meta.total'))->toBe(2);
});

test('filters posts by category slug', function () {
    $cat1 = makeBlogCategory(['name' => 'Food', 'slug' => 'food-wine']);
    $cat2 = makeBlogCategory(['name' => 'Adventures', 'slug' => 'adventures']);

    $post1 = makeBlogPost(['title' => ['en' => 'Food Tour'], 'blog_category_id' => $cat1->id]);
    $post2 = makeBlogPost(['title' => ['en' => 'Hiking Guide'], 'blog_category_id' => $cat2->id]);

    $response = $this->getJson('/api/public/blog?category=food-wine&locale=en');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.title'))->toBe('Food Tour');
});

test('includes is_featured flag on featured posts', function () {
    $category = makeBlogCategory();
    makeBlogPost(['is_featured' => true, 'blog_category_id' => $category->id]);
    makeBlogPost(['is_featured' => false, 'blog_category_id' => $category->id]);

    $response = $this->getJson('/api/public/blog?locale=en');

    $response->assertOk();
    $data = $response->json('data');
    $featured = array_filter($data, fn ($p) => $p['is_featured'] === true);
    expect(count($featured))->toBe(1);
});

test('returns empty data array when no published posts exist', function () {
    $response = $this->getJson('/api/public/blog?locale=en');

    $response->assertOk()
        ->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
                'current_page' => 1,
            ],
        ]);
});

test('returns 422 for invalid locale', function () {
    $response = $this->getJson('/api/public/blog?locale=invalid_lang');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

test('returns 422 for invalid per_page parameter', function () {
    $response = $this->getJson('/api/public/blog?locale=en&per_page=0');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
});

test('orders posts newest-first by published_at DESC', function () {
    $category = makeBlogCategory();
    $oldPost = makeBlogPost([
        'title' => ['en' => 'Old Post'],
        'published_at' => now()->subDays(5),
        'blog_category_id' => $category->id,
    ]);
    $newPost = makeBlogPost([
        'title' => ['en' => 'New Post'],
        'published_at' => now()->subHour(),
        'blog_category_id' => $category->id,
    ]);

    $response = $this->getJson('/api/public/blog?locale=en');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('New Post');
    expect($response->json('data.1.title'))->toBe('Old Post');
});

test('serves response from cache on second identical request', function () {
    $category = makeBlogCategory();
    makeBlogPost(['title' => ['en' => 'Cached Post'], 'blog_category_id' => $category->id]);

    $response1 = $this->getJson('/api/public/blog?locale=en');
    $response1->assertOk();

    // Modify in DB directly without clearing cache
    BlogPost::query()->update(['title' => json_encode(['en' => 'Modified In DB'])]);

    $response2 = $this->getJson('/api/public/blog?locale=en');
    $response2->assertOk();
    expect($response2->json('data.0.title'))->toBe('Cached Post');
});
