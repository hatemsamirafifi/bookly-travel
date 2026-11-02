<?php

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Models\AuthorProfile;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Models\BlogTag;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Services\ContentRevisionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;
use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

if (! function_exists('cmsContentAdminWithRevisions')) {
    function cmsContentAdminWithRevisions(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => [
            'manage_revisions' => true,
            'manage_cms' => true,
            'manage_blog' => true,
            'manage_files' => true,
        ]]);

        return $admin;
    }
}

if (! function_exists('makeCmsContentPage')) {
    function makeCmsContentPage(array $overrides = [], ?array $title = null, ?array $body = null): StaticPage
    {
        return StaticPage::create(array_merge([
            'slug' => 'page-' . uniqid(),
            'status' => StaticPage::STATUS_DRAFT,
            'title' => $title ?? [
                'en' => 'Privacy Policy',
                'es' => 'Política de Privacidad',
                'it' => 'Informativa sulla Privacy',
            ],
            'body' => $body ?? [
                'en' => 'We respect your data.',
                'es' => 'Respetamos tus datos.',
                'it' => 'Rispettiamo i tuoi dati.',
            ],
            'meta_description' => ['en' => 'Privacy meta'],
        ], $overrides));
    }
}

beforeEach(function () {
    $this->cmsAdmin = cmsContentAdminWithRevisions();
    actingAs($this->cmsAdmin);
});

//
// Group 1: content revisions are recorded through the audited actions
//

it('records a version history snapshot when an admin publishes a static page', function () {
    $page = makeCmsContentPage(['slug' => 'about-us']);

    $updated = app(\App\Domains\Admin\Actions\UpdateStaticPageAction::class)->execute(
        $this->cmsAdmin,
        $page,
        ['slug' => 'about-us', 'title' => $page->title, 'body' => $page->body, 'meta_description' => $page->meta_description],
        publish: true,
    );

    $revisions = ContentRevision::query()
        ->where('revisionable_type', 'static_page')
        ->where('revisionable_id', $updated->id)
        ->orderBy('version_number')
        ->get();

    expect($revisions)->toHaveCount(1)
        ->and($revisions[0]->version_number)->toBe(1)
        ->and($revisions[0]->snapshot['title']['en'])->toBe('Privacy Policy')
        ->and($revisions[0]->snapshot['status'])->toBe(StaticPage::STATUS_PUBLISHED)
        ->and($revisions[0]->change_summary)->toContain('about-us');
});

it('records a version history snapshot when a blog post is updated', function () {
    $post = makeBlogPost(['slug' => 'rome-guide']);

    app(\App\Domains\Blog\Actions\UpdateBlogPostAction::class)->execute(
        $this->cmsAdmin,
        ['slug' => 'rome-guide', 'title' => ['en' => 'Best of Rome', 'es' => 'Lo mejor de Roma', 'it' => 'Il meglio di Roma'], 'body' => $post->body],
        $post,
    );

    $revisions = ContentRevision::query()
        ->where('revisionable_type', 'blog_post')
        ->where('revisionable_id', $post->id)
        ->orderBy('version_number')
        ->get();

    expect($revisions)->toHaveCount(1)
        ->and($revisions[0]->version_number)->toBe(1)
        ->and($revisions[0]->snapshot['title']['en'])->toBe('Best of Rome');
});

it('appends versions on each save and keeps lock_version in sync with revisions', function () {
    $page = makeCmsContentPage();

    $action = app(\App\Domains\Admin\Actions\UpdateStaticPageAction::class);
    $base = ['slug' => $page->slug, 'meta_description' => $page->meta_description, 'body' => $page->body];
    $action->execute($this->cmsAdmin, $page, array_merge($base, ['title' => [
        'en' => 'First title',
        'es' => 'Primer título',
        'it' => 'Primo titolo',
    ]]), false);
    $action->execute($this->cmsAdmin, $page->refresh(), array_merge($base, ['title' => [
        'en' => 'Second title',
        'es' => 'Segundo título',
        'it' => 'Secondo titolo',
    ]]), false);

    $versions = ContentRevision::query()
        ->where('revisionable_type', 'static_page')
        ->where('revisionable_id', $page->id)
        ->orderBy('version_number')
        ->pluck('version_number')
        ->all();

    expect($versions)->toBe([1, 2])
        ->and($page->refresh()->lock_version)->toBe(2);
});

//
// Group 2: diff payloads describe changed fields
//

it('stores a body diff on the revisions list', function () {
    $page = makeCmsContentPage();

    $service = app(ContentRevisionService::class);
    $service->recordRevision($page, $this->cmsAdmin, 'Update static page: diff-check');

    $page->refresh();
    $body = $page->body;
    $body['en'] = 'Brand new body text.';
    $page->body = $body;
    $page->save();

    $latest = $service->recordRevision($page, $this->cmsAdmin, 'Update static page: diff-check');

    $fields = collect($latest->diff)->pluck('field')->all();

    expect($fields)->toContain('body');
});

//
// Group 3: restore appends a new revision and reverts content
//

it('restores an earlier snapshot and appends version 3', function () {
    $page = makeCmsContentPage();

    $service = app(ContentRevisionService::class);
    $first = $service->recordRevision($page, $this->cmsAdmin, 'Publish static page: restore-test');

    $page->refresh();
    $body = $page->body;
    $body['en'] = 'Replaced body.';
    $page->body = $body;
    $page->save();
    $service = app(ContentRevisionService::class);
    $service->recordRevision($page, $this->cmsAdmin, 'Update static page: restore-test');

    $service->restore($first, $this->cmsAdmin);

    $page->refresh();

    $versions = ContentRevision::query()
        ->where('revisionable_type', 'static_page')
        ->where('revisionable_id', $page->id)
        ->orderBy('version_number')
        ->get();

    expect($page->body['en'])->toBe('We respect your data.')
        ->and($versions)->toHaveCount(3)
        ->and($versions->last()->version_number)->toBe(3)
        ->and($versions->last()->change_summary)->toContain('Restored revision #1');

    $audit = DB::table('governance_audit_logs')
        ->where('action', 'cms.revision_restore')
        ->first();
    expect($audit)->not->toBeNull();
});

//
// Group 4: public pages API endpoint
//

it('serves a published static page with locale fallback and warning', function () {
    $page = makeCmsContentPage(
        ['status' => StaticPage::STATUS_PUBLISHED],
        title: ['en' => 'Charter Services'],
        body: ['en' => '<p>Full body.</p>'],
    );

    $response = $this->get('/api/public/pages/' . $page->slug . '?locale=es');

    $response->assertStatus(200)
        ->assertJsonPath('data.slug', $page->slug)
        ->assertJsonPath('data.title', 'Charter Services')
        ->assertJsonPath('data.body', '<p>Full body.</p>')
        ->assertJsonPath('data.meta_description', 'Privacy meta')
        ->assertJsonStructure(['data' => ['translation_warning']]);
});

it('serves exact ES content without a fallback warning when translations exist', function () {
    $page = makeCmsContentPage(
        ['status' => StaticPage::STATUS_PUBLISHED],
        title: ['en' => 'Charter Services', 'es' => 'Servicios de Chárter'],
        body: ['en' => '<p>Full body.</p>', 'es' => '<p>Cuerpo completo.</p>'],
    );

    $response = $this->get('/api/public/pages/' . $page->slug . '?locale=es');

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Servicios de Chárter')
        ->assertJsonPath('data.body', '<p>Cuerpo completo.</p>')
        ->assertJsonPath('data.locale', 'es');
});

it('returns 404 for unpublished pages and 422 for unsupported locales', function () {
    $draft = makeCmsContentPage(['status' => StaticPage::STATUS_DRAFT]);
    $published = makeCmsContentPage(['status' => StaticPage::STATUS_PUBLISHED]);

    $this->get('/api/public/pages/' . $draft->slug)->assertStatus(404);
    $this->get('/api/public/pages/' . $published->slug . '?locale=de')->assertStatus(422);
});

//
// Group 5: scheduled publishing command
//

it('publishes due scheduled content and is idempotent', function () {
    $past = now()->subMinutes(30);

    $duePage = makeCmsContentPage(['slug' => 'due-page']);
    $duePage->update(['scheduled_at' => $past]);

    makeCmsContentPage(['slug' => 'future-page', 'scheduled_at' => now()->addDay()]);

    $duePost = makeBlogPost(['slug' => 'due-post']);
    $duePost->update(['scheduled_at' => $past, 'published_at' => null]);

    expect(BlogPost::query()->where('slug', 'due-post')->whereNull('published_at')->exists())->toBeTrue();

    artisan('cms:publish-scheduled')->assertExitCode(0);

    $duePage->refresh();
    $duePost->refresh();

    expect($duePage->status)->toBe(StaticPage::STATUS_PUBLISHED)
        ->and((string) $duePage->published_at)->toBe((string) $past)
        ->and($duePost->status)->toBe(BlogPost::STATUS_PUBLISHED)
        ->and((string) $duePost->published_at)->toBe((string) $past)
        ->and(StaticPage::query()->where('slug', 'future-page')->value('status'))->toBe(StaticPage::STATUS_DRAFT);

    artisan('cms:publish-scheduled')->assertExitCode(0);

    expect((string) $duePost->refresh()->published_at)->toBe((string) $past);

    expect(DB::table('governance_audit_logs')->where('action', 'cms.scheduled_publish')->count())->toBe(1);
});

//
// Group 6: BlogTag + AuthorProfile JSONB localization
//

it('stores blog tags with per-locale names', function () {
    actingAs($this->cmsAdmin);

    BlogTag::create([
        'slug' => 'sailing-' . uniqid(),
        'name' => ['en' => 'Sailing', 'es' => 'Navegación'],
    ]);

    $tag = BlogTag::query()->where('name->en', 'Sailing')->firstOrFail();

    expect($tag->contentFor('en'))->toBe('Sailing')
        ->and($tag->contentFor('es'))->toBe('Navegación')
        ->and($tag->contentFor('it'))->toBe('Sailing');
});

it('api-serves blog tag names through the form merge helper', function () {
    actingAs($this->cmsAdmin);

    $merged = \App\Filament\Resources\BlogTagResource::mergeLocalizedNames([
        'name_en' => 'Sailing',
        'name_es' => 'Navegación',
        'name_it' => null,
    ]);

    expect($merged['name'])->toMatchArray(['en' => 'Sailing', 'es' => 'Navegación']);
});

it('creates author profiles with localized fields', function () {
    actingAs($this->cmsAdmin);

    $profile = AuthorProfile::create([
        'user_id' => $this->cmsAdmin->id,
        'display_name' => ['en' => 'Ada Marlow'],
        'bio' => ['en' => 'Travel writer.', 'it' => 'Scrittrice di viaggi.'],
    ]);

    expect($profile->contentFor('display_name', 'it'))->toBe('Ada Marlow')
        ->and($profile->contentFor('bio', 'it'))->toBe('Scrittrice di viaggi.')
        ->and($profile->contentFor('bio', 'es'))->toBe('Travel writer.');
});
