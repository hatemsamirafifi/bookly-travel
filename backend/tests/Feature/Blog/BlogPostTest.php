<?php

use App\Domains\Partner\Services\TourRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

test('related tour cards withdraw ineligible owners without hiding eligible unavailable tours', function (string $locale) {
    $post = makeBlogPost(['slug' => 'owner-governed-related']);
    $visible = makeSearchableTour('published', 'eligible-unavailable-related');
    $withdrawn = makeSearchableTour('published', 'withdrawn-related');
    addTranslation($visible, 'en', 'Approved public title');
    addTranslation($withdrawn, 'en', 'Private withdrawn title');
    $post->relatedTours()->attach([
        $visible->id => ['sort_order' => 1],
        $withdrawn->id => ['sort_order' => 0],
    ]);

    $this->getJson("/api/public/blog/{$post->slug}?locale={$locale}")
        ->assertOk()->assertJsonCount(2, 'data.related_tours');
    // Raw write deliberately bypasses invalidators: every delivery must check eligibility.
    DB::table('partners')->where('id', $withdrawn->partner_id)->update(['is_active' => false]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale={$locale}")
        ->assertOk()->assertJsonCount(1, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', $visible->slug);
    expect($response->getContent())->not->toContain('withdrawn-related', 'Private withdrawn title');
})->with(['en', 'es', 'it']);

test('related tour projection preserves editorial order and approved source while a proposal waits', function () {
    $post = makeBlogPost(['slug' => 'revision-safe-related']);
    $first = TourRevisionFixtures::tour(sourceLocale: 'es');
    $second = makeSearchableTour('published', 'second-editorial-card');
    addTranslation($second, 'en', 'Second editorial title');
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($first);
    $candidate = $service->createCandidate($first->id, $first->partnerRecord->user_id, $base->id, [
        'source' => ['title' => 'Private proposed Spanish title'],
    ]);
    $service->submitCandidate($first->id, $candidate->id, $first->partnerRecord->user_id);
    $post->relatedTours()->attach([$first->id => ['sort_order' => 4], $second->id => ['sort_order' => 1]]);
    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=es")->assertOk();
    expect(array_column($response->json('data.related_tours'), 'id'))->toBe([$second->id, $first->id])
        ->and($response->json('data.related_tours.1.title'))->toBe('EGM003 authored source title')
        ->and($response->getContent())->not->toContain('Private proposed Spanish title');
});

test('related tour projection uses bounded SQL queries for two and six editorial cards', function () {
    $post = makeBlogPost(['slug' => 'bounded-related-cards']);
    $measurements = [];
    foreach ([2, 6] as $count) {
        for ($i = count($measurements) === 0 ? 0 : 2; $i < $count; $i++) {
            $tour = makeSearchableTour('published', 'bounded-related-' . $i);
            addTranslation($tour, 'en', 'Bounded related ' . $i);
            $post->relatedTours()->attach($tour->id, ['sort_order' => $i]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->getJson("/api/public/blog/{$post->slug}?locale=en")->assertOk()->assertJsonCount($count, 'data.related_tours');
            $measurements[] = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
    expect($measurements[1])->toBeLessThanOrEqual($measurements[0] + 1);
});

test('related tour projection filters withdrawn candidates before the six-card limit', function () {
    $post = makeBlogPost(['slug' => 'backfilled-related-cards']);
    $expected = [];
    for ($i = 0; $i < 8; $i++) {
        $tour = makeSearchableTour('published', 'backfilled-related-' . $i);
        addTranslation($tour, 'en', 'Related title ' . $i);
        $post->relatedTours()->attach($tour->id, ['sort_order' => $i]);
        if ($i < 2) {
            DB::table('partners')->where('id', $tour->partner_id)->update(['onboarding_status' => 'pending']);
        } else {
            $expected[] = $tour->id;
        }
    }
    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=en")->assertOk();
    expect(array_column($response->json('data.related_tours'), 'id'))->toBe($expected);
});

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
