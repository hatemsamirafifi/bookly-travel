<?php

declare(strict_types=1);

use App\Domains\Blog\Models\BlogCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('returns 200 with category details and paginated posts', function () {
    $category = makeBlogCategory(['name' => 'Italian Culture', 'slug' => 'italian-culture', 'description' => 'All about Italy']);
    makeBlogPost(['title' => ['en' => 'Museums of Florence'], 'blog_category_id' => $category->id]);

    $response = $this->getJson('/api/public/blog/category/italian-culture?locale=en');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'posts' => [
                    '*' => [
                        'id',
                        'slug',
                        'title',
                        'excerpt',
                        'cover_image',
                        'reading_time_minutes',
                        'published_at',
                        'primary_category',
                        'author',
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('data.name'))->toBe('Italian Culture');
    expect($response->json('data.posts.0.title'))->toBe('Museums of Florence');
    expect($response->json('meta.total'))->toBe(1);
});

test('returns 404 for non-existent category slug', function () {
    $response = $this->getJson('/api/public/blog/category/non-existent-category?locale=en');

    $response->assertNotFound();
});

test('inactive category is still reachable by direct URL', function () {
    $category = makeBlogCategory([
        'name' => 'Archived Topics',
        'slug' => 'archived-topics',
        'is_active' => false,
    ]);
    makeBlogPost(['title' => ['en' => 'Old Post'], 'blog_category_id' => $category->id]);

    $response = $this->getJson('/api/public/blog/category/archived-topics?locale=en');

    $response->assertOk();
    expect($response->json('data.name'))->toBe('Archived Topics');
});

test('returns 422 for invalid locale on category endpoint', function () {
    $category = makeBlogCategory(['slug' => 'tips']);

    $response = $this->getJson('/api/public/blog/category/tips?locale=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});
