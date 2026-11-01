<?php

use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostDetailTransformer;
use App\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('sanitizes unsafe rich HTML when saving a blog post', function () {
    $admin = makeAdmin();

    $payload = '<h2>Heading</h2>'
        .'<p>Safe <strong>bold</strong> and <em>italic</em>.</p>'
        .'<ul><li>Item</li></ul>'
        .'<a href="https://example.com">Link</a>'
        .'<script>alert(1)</script>'
        .'<img src="https://example.com/x.png" onerror="alert(1)">'
        .'<a href="javascript:alert(1)">Click</a>'
        .'<svg onload="alert(1)"></svg>'
        .'<iframe src="https://evil.test"></iframe>';

    $post = app(UpdateBlogPostAction::class)->execute($admin, [
        'author_id' => $admin->id,
        'blog_category_id' => makeBlogCategory()->id,
        'slug' => 'sanitized-blog-post',
        'status' => BlogPost::STATUS_DRAFT,
        'title' => ['en' => 'Safe Title<script>alert(1)</script>'],
        'excerpt' => ['en' => 'Safe excerpt<script>alert(1)</script>'],
        'meta_title' => ['en' => 'Safe meta<script>alert(1)</script>'],
        'meta_description' => ['en' => 'Safe description<script>alert(1)</script>'],
        'body' => ['en' => $payload],
    ]);

    $body = $post->body['en'];

    expect($body)
        ->toContain('<h2>Heading</h2>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('href="https://example.com"')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<svg')
        ->not->toContain('<iframe');

    expect($post->title['en'])->not->toContain('<script')
        ->and($post->excerpt['en'])->not->toContain('<script')
        ->and($post->meta_title['en'])->not->toContain('<script')
        ->and($post->meta_description['en'])->not->toContain('<script');
});

it('sanitizes unsafe rich HTML when saving a static page', function () {
    $admin = makeAdmin();

    $page = StaticPage::create([
        'slug' => 'sanitized-static-page',
        'status' => StaticPage::STATUS_DRAFT,
        'title' => ['en' => 'Legacy Title'],
        'body' => ['en' => '<p>Legacy body.</p>'],
        'meta_description' => ['en' => 'Legacy meta.'],
    ]);

    $payload = '<p>Safe <strong>bold</strong>.</p>'
        .'<script>alert(1)</script>'
        .'<img src="https://example.com/x.png" onerror="alert(1)">'
        .'<a href="javascript:alert(1)">Click</a>'
        .'<svg onload="alert(1)"></svg>'
        .'<iframe src="https://evil.test"></iframe>';

    app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'slug' => $page->slug,
        'title' => ['en' => 'Legacy Title'],
        'body' => ['en' => $payload],
        'meta_description' => ['en' => 'Legacy meta.'],
    ]);

    $body = $page->fresh()->body['en'];

    expect($body)
        ->toContain('<p>Safe <strong>bold</strong>.</p>')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<svg')
        ->not->toContain('<iframe');
});

it('sanitizes a legacy unsafe body when serialized by the detail transformer', function () {
    $post = makeBlogPost([
        'slug' => 'legacy-xss-post',
        'title' => ['en' => 'Legacy XSS Post'],
        'body' => ['en' => '<p>Safe paragraph.</p><script>alert(1)</script><img src="https://example.com/x.png" onerror="alert(1)">'],
    ]);

    $data = app(BlogPostDetailTransformer::class)->transform($post->fresh(), 'en');

    expect($data['body'])
        ->toContain('<p>Safe paragraph.</p>')
        ->not->toContain('<script')
        ->not->toContain('onerror');
});

it('keeps blank Spanish and Italian fields empty when editing an English-only post', function () {
    actingAs(makeAdmin());

    $post = makeBlogPost([
        'slug' => 'english-only-post',
        'cover_image_url' => null,
        'title' => ['en' => 'English Only Title'],
        'body' => ['en' => '<p>English only body.</p>'],
        'excerpt' => ['en' => 'English only excerpt.'],
        'meta_title' => ['en' => 'English only meta title.'],
        'meta_description' => ['en' => 'English only meta description.'],
    ]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])
        ->assertFormSet([
            'title_en' => 'English Only Title',
            'body_en' => '<p>English only body.</p>',
            'title_es' => '',
            'body_es' => '',
            'title_it' => '',
            'body_it' => '',
        ])
        ->fillForm([
            'title_en' => 'Updated English Title',
            'body_en' => '<p>Updated English body.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();

    expect($post->title['en'])->toBe('Updated English Title')
        ->and($post->body['en'])->toContain('Updated English body')
        ->and($post->title)->not->toHaveKey('es')
        ->and($post->body)->not->toHaveKey('es')
        ->and($post->title)->not->toHaveKey('it')
        ->and($post->body)->not->toHaveKey('it');
});

it('falls back to English and flags a title-only Spanish translation', function () {
    $post = makeBlogPost([
        'slug' => 'partial-title-only-es',
        'title' => ['en' => 'Full English Title', 'es' => 'Solo titulo espanol'],
        'body' => ['en' => '<p>Full English body.</p>'],
        'excerpt' => ['en' => 'English excerpt.'],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=es");

    $response->assertOk()
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.title', 'Full English Title')
        ->assertJsonPath(
            'data.seo.canonical_url',
            config('app.url', 'https://bookly.com')."/es/blog/{$post->slug}"
        );

    expect($response->json('data.body'))
        ->toContain('Full English body')
        ->not->toContain('Solo titulo espanol');
});

it('falls back to English and flags a body-only Spanish translation', function () {
    $post = makeBlogPost([
        'slug' => 'partial-body-only-es',
        'title' => ['en' => 'English Body Only Title'],
        'body' => ['en' => '<p>English body only.</p>', 'es' => '<p>Cuerpo solo en espanol.</p>'],
        'excerpt' => ['en' => 'English excerpt.'],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=es");

    $response->assertOk()
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.title', 'English Body Only Title');

    expect($response->json('data.body'))
        ->toContain('English body only')
        ->not->toContain('Cuerpo solo en espanol');
});

it('serves the requested locale without a warning when title and body are both translated', function () {
    $post = makeBlogPost([
        'slug' => 'complete-spanish-post',
        'title' => ['en' => 'Complete English Title', 'es' => 'Titulo completo'],
        'body' => ['en' => '<p>English body.</p>', 'es' => '<p>Cuerpo completo.</p>'],
        'excerpt' => ['en' => 'English excerpt.', 'es' => 'Resumen completo.'],
    ]);

    $response = $this->getJson("/api/public/blog/{$post->slug}?locale=es");

    $response->assertOk()
        ->assertJsonPath('data.translation_warning', null)
        ->assertJsonPath('data.title', 'Titulo completo')
        ->assertJsonPath(
            'data.seo.canonical_url',
            config('app.url', 'https://bookly.com')."/es/blog/{$post->slug}"
        );

    expect($response->json('data.body'))->toContain('Cuerpo completo');
});
