<?php

use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostDetailTransformer;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use App\Filament\Resources\StaticPageResource\Pages\CreateStaticPage;
use App\Filament\Resources\StaticPageResource\Pages\EditStaticPage;
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
        . '<p>Safe <strong>bold</strong> and <em>italic</em>.</p>'
        . '<ul><li>Item</li></ul>'
        . '<a href="https://example.com">Link</a>'
        . '<script>alert(1)</script>'
        . '<img src="https://example.com/x.png" onerror="alert(1)">'
        . '<a href="javascript:alert(1)">Click</a>'
        . '<svg onload="alert(1)"></svg>'
        . '<iframe src="https://evil.test"></iframe>';

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
        . '<script>alert(1)</script>'
        . '<img src="https://example.com/x.png" onerror="alert(1)">'
        . '<a href="javascript:alert(1)">Click</a>'
        . '<svg onload="alert(1)"></svg>'
        . '<iframe src="https://evil.test"></iframe>';

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
            config('app.url', 'https://bookly.com') . "/es/blog/{$post->slug}"
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
            config('app.url', 'https://bookly.com') . "/es/blog/{$post->slug}"
        );

    expect($response->json('data.body'))->toContain('Cuerpo completo');
});

it('falls back to English and sets translation_warning when static page has ES body but missing ES title', function () {
    $page = StaticPage::create([
        'slug' => 'static-missing-es-title',
        'status' => StaticPage::STATUS_PUBLISHED,
        'title' => ['en' => 'English Static Title'],
        'body' => ['en' => '<p>English static body.</p>', 'es' => '<p>Cuerpo estático español.</p>'],
        'meta_title' => ['en' => 'English Meta Title', 'es' => 'Meta Título Español'],
        'meta_description' => ['en' => 'English Meta Description', 'es' => 'Meta Descripción Española'],
    ]);

    $response = $this->getJson("/api/public/pages/{$page->slug}?locale=es");

    $response->assertOk()
        ->assertJsonPath('data.title', 'English Static Title')
        ->assertJsonPath('data.body', '<p>English static body.</p>')
        ->assertJsonPath('data.meta_title', 'English Meta Title')
        ->assertJsonPath('data.meta_description', 'English Meta Description')
        ->assertJsonStructure(['data' => ['translation_warning']]);

    expect($response->json('data.translation_warning'))->not->toBeNull();
});

it('falls back to English and sets translation_warning when static page has ES title but missing or empty HTML ES body', function () {
    $page = StaticPage::create([
        'slug' => 'static-missing-es-body',
        'status' => StaticPage::STATUS_PUBLISHED,
        'title' => ['en' => 'English Static Title', 'es' => 'Título Estático Español'],
        'body' => ['en' => '<p>English static body.</p>', 'es' => '<p>   </p>'],
        'meta_title' => ['en' => 'English Meta Title', 'es' => 'Meta Título Español'],
        'meta_description' => ['en' => 'English Meta Description', 'es' => 'Meta Descripción Española'],
    ]);

    $response = $this->getJson("/api/public/pages/{$page->slug}?locale=es");

    $response->assertOk()
        ->assertJsonPath('data.title', 'English Static Title')
        ->assertJsonPath('data.body', '<p>English static body.</p>')
        ->assertJsonPath('data.meta_title', 'English Meta Title')
        ->assertJsonPath('data.meta_description', 'English Meta Description')
        ->assertJsonStructure(['data' => ['translation_warning']]);

    expect($response->json('data.translation_warning'))->not->toBeNull();
});

it('serves complete ES and IT static page translations without translation_warning', function () {
    $page = StaticPage::create([
        'slug' => 'static-complete-locales',
        'status' => StaticPage::STATUS_PUBLISHED,
        'title' => [
            'en' => 'About Bookly',
            'es' => 'Sobre Bookly',
            'it' => 'Chi Siamo Bookly',
        ],
        'body' => [
            'en' => '<p>English body.</p>',
            'es' => '<p>Cuerpo español.</p>',
            'it' => '<p>Corpo italiano.</p>',
        ],
    ]);

    $esResponse = $this->getJson("/api/public/pages/{$page->slug}?locale=es");
    $esResponse->assertOk()
        ->assertJsonPath('data.title', 'Sobre Bookly')
        ->assertJsonPath('data.body', '<p>Cuerpo español.</p>')
        ->assertJsonMissingPath('data.translation_warning');

    $itResponse = $this->getJson("/api/public/pages/{$page->slug}?locale=it");
    $itResponse->assertOk()
        ->assertJsonPath('data.title', 'Chi Siamo Bookly')
        ->assertJsonPath('data.body', '<p>Corpo italiano.</p>')
        ->assertJsonMissingPath('data.translation_warning');
});

it('sanitizes legacy unsanitized HTML at read time on the public static page API', function () {
    $page = StaticPage::create([
        'slug' => 'static-legacy-xss',
        'status' => StaticPage::STATUS_PUBLISHED,
        'title' => ['en' => 'Legacy Static Page'],
        'body' => ['en' => '<p>Safe static content.</p><script>alert(1)</script><img src="https://example.com/a.png" onerror="alert(1)"><iframe src="https://evil.test"></iframe>'],
    ]);

    $response = $this->getJson("/api/public/pages/{$page->slug}?locale=en");

    $response->assertOk();
    expect($response->json('data.body'))
        ->toContain('<p>Safe static content.</p>')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('<iframe');
});

it('creates an English-only static page without populating ES or IT placeholders', function () {
    actingAs(makeAdmin());

    Livewire::test(CreateStaticPage::class)
        ->fillForm([
            'slug' => 'created-english-only-static',
            'status' => StaticPage::STATUS_DRAFT,
            'title_en' => 'Created English Static Title',
            'body_en' => '<p>Created English static body.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = StaticPage::query()->where('slug', 'created-english-only-static')->firstOrFail();

    expect($page->title['en'])->toBe('Created English Static Title')
        ->and($page->body['en'])->toContain('Created English static body.')
        ->and($page->title)->not->toHaveKey('es')
        ->and($page->title)->not->toHaveKey('it')
        ->and($page->body)->not->toHaveKey('es')
        ->and($page->body)->not->toHaveKey('it');
});

it('leaves ES and IT empty when editing an English-only static page through EditStaticPage', function () {
    actingAs(makeAdmin());

    $page = StaticPage::create([
        'slug' => 'edit-english-only-static',
        'status' => StaticPage::STATUS_DRAFT,
        'title' => ['en' => 'Original English Static Title'],
        'body' => ['en' => '<p>Original English static body.</p>'],
        'meta_title' => ['en' => 'Original Meta Title'],
        'meta_description' => ['en' => 'Original Meta Description'],
    ]);

    Livewire::test(EditStaticPage::class, ['record' => $page->getRouteKey()])
        ->assertFormSet([
            'title_en' => 'Original English Static Title',
            'body_en' => '<p>Original English static body.</p>',
            'title_es' => '',
            'body_es' => '',
            'title_it' => '',
            'body_it' => '',
        ])
        ->fillForm([
            'title_en' => 'Updated English Static Title',
            'body_en' => '<p>Updated English static body.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();

    expect($page->title['en'])->toBe('Updated English Static Title')
        ->and($page->body['en'])->toContain('Updated English static body.')
        ->and($page->title)->not->toHaveKey('es')
        ->and($page->title)->not->toHaveKey('it')
        ->and($page->body)->not->toHaveKey('es')
        ->and($page->body)->not->toHaveKey('it');
});

it('preserves existing ES and IT translations when editing English fields on EditStaticPage', function () {
    actingAs(makeAdmin());

    $page = StaticPage::create([
        'slug' => 'edit-preserve-es-it-static',
        'status' => StaticPage::STATUS_DRAFT,
        'title' => [
            'en' => 'Original English Title',
            'es' => 'Título Español Existente',
            'it' => 'Titolo Italiano Esistente',
        ],
        'body' => [
            'en' => '<p>Original English body.</p>',
            'es' => '<p>Cuerpo español existente.</p>',
            'it' => '<p>Corpo italiano esistente.</p>',
        ],
    ]);

    Livewire::test(EditStaticPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title_en' => 'Updated English Title Only',
            'body_en' => '<p>Updated English body only.</p>',
            'title_es' => '',
            'body_es' => '',
            'title_it' => '',
            'body_it' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();

    expect($page->title['en'])->toBe('Updated English Title Only')
        ->and($page->title['es'])->toBe('Título Español Existente')
        ->and($page->title['it'])->toBe('Titolo Italiano Esistente')
        ->and($page->body['es'])->toContain('Cuerpo español existente.')
        ->and($page->body['it'])->toContain('Corpo italiano esistente.');
});

it('preserves omitted locales when calling UpdateStaticPageAction with partial locale arrays and supports explicit clearing', function () {
    $admin = makeAdmin();
    actingAs($admin);

    $page = StaticPage::create([
        'slug' => 'partial-action-preserve-static',
        'status' => StaticPage::STATUS_DRAFT,
        'title' => [
            'en' => 'English Action Title',
            'es' => 'Título Acción Español',
            'it' => 'Titolo Azione Italiano',
        ],
        'body' => [
            'en' => '<p>English action body.</p>',
            'es' => '<p>Cuerpo acción español.</p>',
            'it' => '<p>Corpo azione italiano.</p>',
        ],
    ]);

    app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'title' => ['en' => 'Updated English Action Title'],
        'body' => ['en' => '<p>Updated English action body.</p>'],
    ]);

    $page->refresh();

    expect($page->title['en'])->toBe('Updated English Action Title')
        ->and($page->title['es'])->toBe('Título Acción Español')
        ->and($page->title['it'])->toBe('Titolo Azione Italiano')
        ->and($page->body['es'])->toContain('Cuerpo acción español.')
        ->and($page->body['it'])->toContain('Corpo azione italiano.');

    // Explicit clearing via EditStaticPage::clearLocaleTranslation('es')
    Livewire::test(EditStaticPage::class, ['record' => $page->getRouteKey()])
        ->call('clearLocaleTranslation', 'es');

    $page->refresh();

    expect($page->title)->not->toHaveKey('es')
        ->and($page->body)->not->toHaveKey('es')
        ->and($page->title['it'])->toBe('Titolo Azione Italiano');
});

it('falls back to English on blog detail and list when TranslationFieldState is outdated, failed, needs_review, or has stale source hash', function (string $ineligibleStatus, bool $staleHash) {
    $enTitle = 'Fresh English Blog Title';
    $enBody = '<p>Fresh English blog body content.</p>';
    $enTitleHash = hash('sha256', trim($enTitle));
    $enBodyHash = hash('sha256', trim($enBody));

    $post = makeBlogPost([
        'slug' => 'blog-state-' . $ineligibleStatus . ($staleHash ? '-stale' : ''),
        'title' => ['en' => $enTitle, 'es' => 'Título español desactualizado'],
        'body' => ['en' => $enBody, 'es' => '<p>Cuerpo español desactualizado.</p>'],
        'excerpt' => ['en' => 'Fresh English excerpt.', 'es' => 'Resumen español.'],
    ]);

    TranslationFieldState::create([
        'translatable_type' => $post->getMorphClass(),
        'translatable_id' => $post->id,
        'locale' => 'es',
        'field_name' => 'title',
        'status' => TranslationFieldState::STATUS_COMPLETED,
        'origin' => TranslationFieldState::ORIGIN_AUTO,
        'source_hash' => $enTitleHash,
        'translated_source_hash' => $enTitleHash,
    ]);

    TranslationFieldState::create([
        'translatable_type' => $post->getMorphClass(),
        'translatable_id' => $post->id,
        'locale' => 'es',
        'field_name' => 'body',
        'status' => $ineligibleStatus,
        'origin' => TranslationFieldState::ORIGIN_AUTO,
        'source_hash' => $enBodyHash,
        'translated_source_hash' => $staleHash ? hash('sha256', 'old-english-body') : $enBodyHash,
    ]);

    $detailResponse = $this->getJson("/api/public/blog/{$post->slug}?locale=es");
    $detailResponse->assertOk()
        ->assertJsonPath('data.title', $enTitle)
        ->assertJsonPath('data.translation_warning', 'partial_translation');
    expect($detailResponse->json('data.body'))->toContain('Fresh English blog body content.');

    $listResponse = $this->getJson('/api/public/blog?locale=es');
    $listResponse->assertOk();
    $card = collect($listResponse->json('data'))->firstWhere('slug', $post->slug);
    expect($card)->not->toBeNull()
        ->and($card['title'])->toBe($enTitle)
        ->and($card['translation_warning'])->toBe('partial_translation');
})->with([
    'outdated status' => [TranslationFieldState::STATUS_OUTDATED, false],
    'failed status' => [TranslationFieldState::STATUS_FAILED, false],
    'needs_review status' => [TranslationFieldState::STATUS_NEEDS_REVIEW, false],
    'completed with stale source hash' => [TranslationFieldState::STATUS_COMPLETED, true],
]);

it('renders requested locale without warning on blog detail and list when TranslationFieldState is completed or manually_edited with matching source hash', function (string $eligibleStatus) {
    $enTitle = 'Matching English Blog Title';
    $enBody = '<p>Matching English blog body.</p>';
    $enTitleHash = hash('sha256', trim($enTitle));
    $enBodyHash = hash('sha256', trim($enBody));

    $post = makeBlogPost([
        'slug' => 'blog-eligible-' . $eligibleStatus,
        'title' => ['en' => $enTitle, 'es' => 'Título Español Elegible'],
        'body' => ['en' => $enBody, 'es' => '<p>Cuerpo español elegible.</p>'],
        'excerpt' => ['en' => 'English excerpt.', 'es' => 'Resumen español elegible.'],
    ]);

    foreach (['title' => $enTitleHash, 'body' => $enBodyHash] as $field => $hash) {
        TranslationFieldState::create([
            'translatable_type' => $post->getMorphClass(),
            'translatable_id' => $post->id,
            'locale' => 'es',
            'field_name' => $field,
            'status' => $eligibleStatus,
            'origin' => $eligibleStatus === TranslationFieldState::STATUS_MANUALLY_EDITED
                ? TranslationFieldState::ORIGIN_MANUAL
                : TranslationFieldState::ORIGIN_AUTO,
            'source_hash' => $hash,
            'translated_source_hash' => $hash,
        ]);
    }

    $detailResponse = $this->getJson("/api/public/blog/{$post->slug}?locale=es");
    $detailResponse->assertOk()
        ->assertJsonPath('data.title', 'Título Español Elegible')
        ->assertJsonPath('data.translation_warning', null);
    expect($detailResponse->json('data.body'))->toContain('Cuerpo español elegible.');

    $listResponse = $this->getJson('/api/public/blog?locale=es');
    $listResponse->assertOk();
    $card = collect($listResponse->json('data'))->firstWhere('slug', $post->slug);
    expect($card)->not->toBeNull()
        ->and($card['title'])->toBe('Título Español Elegible')
        ->and($card['translation_warning'])->toBeNull();
})->with([
    'completed status' => [TranslationFieldState::STATUS_COMPLETED],
    'manually_edited status' => [TranslationFieldState::STATUS_MANUALLY_EDITED],
]);

it('enforces TranslationFieldState eligibility on public static pages across outdated, failed, needs_review, completed, and manually_edited states', function () {
    $enTitle = 'Static English Source Title';
    $enBody = '<p>Static English source body.</p>';
    $enTitleHash = hash('sha256', trim($enTitle));
    $enBodyHash = hash('sha256', trim($enBody));

    $page = StaticPage::create([
        'slug' => 'static-field-state-eligibility',
        'status' => StaticPage::STATUS_PUBLISHED,
        'title' => ['en' => $enTitle, 'es' => 'Título Estático Español'],
        'body' => ['en' => $enBody, 'es' => '<p>Cuerpo estático español.</p>'],
    ]);

    $titleState = TranslationFieldState::create([
        'translatable_type' => $page->getMorphClass(),
        'translatable_id' => $page->id,
        'locale' => 'es',
        'field_name' => 'title',
        'status' => TranslationFieldState::STATUS_COMPLETED,
        'origin' => TranslationFieldState::ORIGIN_AUTO,
        'source_hash' => $enTitleHash,
        'translated_source_hash' => $enTitleHash,
    ]);

    $bodyState = TranslationFieldState::create([
        'translatable_type' => $page->getMorphClass(),
        'translatable_id' => $page->id,
        'locale' => 'es',
        'field_name' => 'body',
        'status' => TranslationFieldState::STATUS_OUTDATED,
        'origin' => TranslationFieldState::ORIGIN_AUTO,
        'source_hash' => $enBodyHash,
        'translated_source_hash' => hash('sha256', 'old-english-static-body'),
    ]);

    foreach ([
        TranslationFieldState::STATUS_OUTDATED,
        TranslationFieldState::STATUS_FAILED,
        TranslationFieldState::STATUS_NEEDS_REVIEW,
    ] as $badStatus) {
        $bodyState->update([
            'status' => $badStatus,
            'translated_source_hash' => $enBodyHash,
        ]);

        $res = $this->getJson("/api/public/pages/{$page->slug}?locale=es");
        $res->assertOk()
            ->assertJsonPath('data.title', $enTitle)
            ->assertJsonStructure(['data' => ['translation_warning']]);
    }

    foreach ([
        TranslationFieldState::STATUS_COMPLETED,
        TranslationFieldState::STATUS_MANUALLY_EDITED,
    ] as $goodStatus) {
        $titleState->update(['status' => $goodStatus, 'translated_source_hash' => $enTitleHash]);
        $bodyState->update(['status' => $goodStatus, 'translated_source_hash' => $enBodyHash]);

        $res = $this->getJson("/api/public/pages/{$page->slug}?locale=es");
        $res->assertOk()
            ->assertJsonPath('data.title', 'Título Estático Español')
            ->assertJsonMissingPath('data.translation_warning');
    }
});
