<?php

declare(strict_types=1);

use App\Domains\Blog\Jobs\InvalidateBlogCacheJob;
use App\Domains\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/*
| Validates the Spec-016 remediation (CR-009): the blog list and category
| actions now store their 300s cache entries under Cache::tags([...]) so that
| InvalidateBlogCacheJob's tag-based flush actually clears them. Before the
| fix the actions used plain (untagged) Cache::remember, so the job's tag flush
| was a no-op and the public blog surface served stale content for the full TTL
| after a post was created/updated/archived.
|
| CACHE_STORE=array in phpunit.xml.dist — Laravel's ArrayStore extends
| TaggableStore, so Cache::tags()->remember() / ->flush() behave identically to
| a tag-capable store like Redis, making the invalidation observable here.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('InvalidateBlogCacheJob flushes the tagged blog list cache so stale entries are rebuilt', function () {
    $category = makeBlogCategory();
    makeBlogPost(['title' => ['en' => 'Original Title'], 'blog_category_id' => $category->id]);

    // Prime the tagged cache.
    $first = $this->getJson('/api/public/blog?locale=en');
    $first->assertOk();
    expect($first->json('data.0.title'))->toBe('Original Title');

    // Mutate the DB without touching the cache — the cached response must stay stale.
    BlogPost::query()->update(['title' => json_encode(['en' => 'Fresh Title'])]);

    $stale = $this->getJson('/api/public/blog?locale=en');
    $stale->assertOk();
    expect($stale->json('data.0.title'))->toBe('Original Title');

    // Run the invalidation job (QUEUE_CONNECTION=sync → runs inline).
    InvalidateBlogCacheJob::dispatch();

    // After invalidation the list must rebuild from the DB and serve the fresh title.
    $fresh = $this->getJson('/api/public/blog?locale=en');
    $fresh->assertOk();
    expect($fresh->json('data.0.title'))->toBe('Fresh Title');
});

test('InvalidateBlogCacheJob flushes the tagged category cache', function () {
    $category = makeBlogCategory(['slug' => 'wine', 'name' => 'Wine']);
    makeBlogPost(['title' => ['en' => 'Wine Post'], 'blog_category_id' => $category->id]);

    $first = $this->getJson('/api/public/blog/category/wine?locale=en');
    $first->assertOk();
    expect($first->json('data.posts.0.title'))->toBe('Wine Post');

    BlogPost::query()->update(['title' => json_encode(['en' => 'Aged Wine Post'])]);

    // Still stale from the tagged cache.
    expect($this->getJson('/api/public/blog/category/wine?locale=en')->json('data.posts.0.title'))
        ->toBe('Wine Post');

    InvalidateBlogCacheJob::dispatch();

    // Cache flushed → rebuilt from DB with the updated title.
    expect($this->getJson('/api/public/blog/category/wine?locale=en')->json('data.posts.0.title'))
        ->toBe('Aged Wine Post');
});
