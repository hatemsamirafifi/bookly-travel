<?php

use App\Domains\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('detail 200 for published post with valid locale', function () {
    $post = makeBlogPost([
        'slug' => 'test-published-post',
        'title' => ['en' => 'English Post Title', 'es' => 'Título en Español'],
        'body' => ['en' => '<p>English body content here.</p>'],
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=en");

    $response->assertStatus(200)
        ->assertJsonPath('data.slug', 'test-published-post')
        ->assertJsonPath('data.title', 'English Post Title')
        ->assertJsonPath('data.seo.canonical_url', config('app.url', 'https://bookly.com') . "/en/blog/{$post->slug}")
        ->assertJsonStructure([
            'data' => [
                'id',
                'slug',
                'title',
                'body',
                'excerpt',
                'cover_image_url',
                'published_at',
                'updated_at',
                'is_featured',
                'reading_time',
                'translation_warning',
                'category',
                'author',
                'seo' => ['meta_title', 'meta_description', 'canonical_url', 'hreflang'],
                'related_tours',
                'related_posts',
            ],
        ]);
});

test('detail 404 for draft post', function () {
    $post = makeBlogPost([
        'slug' => 'draft-post',
        'status' => 'draft',
    ]);

    $this->getJson("/api/public/blog/{$post->slug}?locale=en")
        ->assertStatus(404);
});

test('detail 404 for scheduled-future post', function () {
    $post = makeBlogPost([
        'slug' => 'future-post',
        'status' => 'published',
        'scheduled_at' => now()->addDay(),
    ]);

    $this->getJson("/api/public/blog/{$post->slug}?locale=en")
        ->assertStatus(404);
});

test('detail 404 for non-existent post', function () {
    $this->getJson('/api/public/blog/non-existent-slug?locale=en')
        ->assertStatus(404);
});

test('detail 410 for archived post that was previously published', function () {
    $post = makeBlogPost([
        'slug' => 'archived-published-post',
        'status' => 'archived',
        'published_at' => now()->subMonth(),
    ]);

    $this->getJson("/api/public/blog/{$post->slug}?locale=en")
        ->assertStatus(410);
});

test('detail 404 for archived post that was never published', function () {
    $post = makeBlogPost([
        'slug' => 'archived-never-published-post',
        'status' => 'archived',
        'published_at' => null,
    ]);

    $this->getJson("/api/public/blog/{$post->slug}?locale=en")
        ->assertStatus(404);
});

test('detail 422 for invalid locale', function () {
    $post = makeBlogPost(['slug' => 'valid-post']);

    $this->getJson("/api/public/blog/{$post->slug}?locale=fr")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

test('detail returns translation_warning when requested locale content is missing', function () {
    $post = makeBlogPost([
        'slug' => 'en-only-post',
        'title' => ['en' => 'Only English Title'],
        'body' => ['en' => '<p>Only English Body</p>'],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=es");

    $response->assertStatus(200)
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.title', 'Only English Title');
});

test('detail includes only published related tours', function () {
    $post = makeBlogPost(['slug' => 'tours-post']);
    $publishedTour = makeSearchableTour('published', 'published-tour');
    $draftTour = makeSearchableTour('draft', 'draft-tour');

    addTranslation($publishedTour, 'en', 'Published Tour Title');
    addTranslation($draftTour, 'en', 'Draft Tour Title');

    $post->relatedTours()->attach([
        $publishedTour->id => ['sort_order' => 0],
        $draftTour->id => ['sort_order' => 1],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=en");

    $response->assertStatus(200);
    $relatedTours = $response->json('data.related_tours');
    expect($relatedTours)->toHaveCount(1)
        ->and($relatedTours[0]['slug'])->toBe('published-tour');
});

test('detail calculates reading_time correctly', function () {
    $body = '<p>' . str_repeat('word ', 400) . '</p>';
    $post = makeBlogPost([
        'slug' => 'long-post',
        'body' => ['en' => $body],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=en");

    $response->assertStatus(200)
        ->assertJsonPath('data.reading_time', 2);
});
