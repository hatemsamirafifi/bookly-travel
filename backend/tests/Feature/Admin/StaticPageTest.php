<?php

use App\Domains\Admin\Actions\UpdateSettingsAction;
use App\Domains\Admin\Actions\UpdateStaticPageAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Settings\GeneralSettings;
use App\Filament\Resources\StaticPageResource;
use App\Filament\Resources\StaticPageResource\Pages\EditStaticPage;
use App\Filament\Resources\StaticPageResource\Pages\ListStaticPages;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Livewire\Livewire;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function cmsAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_cms' => true, 'manage_settings' => true]]);

    return $admin->fresh('adminPermission');
}

function makeStaticPage(array $title = ['en' => 'Privacy Policy', 'es' => 'Política', 'it' => 'Privacy'], array $body = ['en' => 'We respect your data.', 'es' => 'Respetamos', 'it' => 'Rispettiamo']): StaticPage
{
    return StaticPage::create([
        'slug' => 'privacy-' . uniqid(),
        'status' => StaticPage::STATUS_DRAFT,
        'title' => $title,
        'body' => $body,
        'meta_description' => ['en' => 'Privacy meta'],
    ]);
}

it('lets a manage_cms admin list static pages', function () {
    actingAs(cmsAdmin());
    $page = makeStaticPage();

    Livewire::test(ListStaticPages::class)
        ->assertCanSeeTableRecords([$page]);
});

it('publishes a static page and writes a cms.publish audit entry', function () {
    $admin = cmsAdmin();
    actingAs($admin);
    $page = makeStaticPage();

    $updated = app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'slug' => $page->slug,
        'title' => $page->title,
        'body' => ['en' => 'Updated body content', 'es' => '', 'it' => ''],
        'meta_description' => $page->meta_description,
    ], publish: true);

    expect($updated->status)->toBe(StaticPage::STATUS_PUBLISHED)
        ->and($updated->published_at)->not->toBeNull()
        ->and($updated->updated_by)->toBe($admin->id);

    $audit = GovernanceAuditLog::where('action', 'cms.publish')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->target_type)->toBe('static_page')
        ->and($audit->target_id)->toBe($page->id)
        ->and($audit->actor_id)->toBe($admin->id)
        ->and($audit->before_state['status'])->toBe(StaticPage::STATUS_DRAFT)
        ->and($audit->after_state['body']['en'])->toBe('Updated body content');
});

it('updates a draft page without publishing and writes a cms.update audit entry', function () {
    $admin = cmsAdmin();
    actingAs($admin);
    $page = makeStaticPage();

    $updated = app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'slug' => $page->slug,
        'title' => ['en' => 'New Title', 'es' => '', 'it' => ''],
        'body' => $page->body,
        'meta_description' => $page->meta_description,
    ]);

    expect($updated->status)->toBe(StaticPage::STATUS_DRAFT)
        ->and($updated->published_at)->toBeNull();

    $audit = GovernanceAuditLog::where('action', 'cms.update')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->after_state['title']['en'])->toBe('New Title');
});

it('renders the updated localized content for the public site', function () {
    $page = makeStaticPage();
    $admin = cmsAdmin();

    app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'slug' => $page->slug,
        'title' => $page->title,
        'body' => ['en' => 'New English body', 'es' => 'Nuevo cuerpo', 'it' => ''],
        'meta_description' => $page->meta_description,
    ]);

    $page->refresh();
    expect($page->contentFor('en')->body)->toBe('New English body')
        ->and($page->contentFor('es')->body)->toBe('Nuevo cuerpo')
        ->and($page->contentFor('it')->body)->toBe('New English body'); // falls back to en
});

it('audits a settings change with target_type=setting and the changed property', function () {
    $admin = cmsAdmin();
    actingAs($admin);

    $before = app(GeneralSettings::class)->site_name;

    app(UpdateSettingsAction::class)->execute($admin, 'general', [
        'site_name' => 'Bookly Tours',
        'maintenance_mode' => true,
    ]);

    $audit = GovernanceAuditLog::where('action', 'settings.update')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->target_type)->toBe('setting')
        ->and($audit->target_id)->toBeNull()
        ->and($audit->metadata['group'])->toBe('general')
        ->and($audit->metadata['properties']['site_name']['before'])->toBe($before)
        ->and($audit->metadata['properties']['site_name']['after'])->toBe('Bookly Tours');

    expect(app(GeneralSettings::class)->site_name)->toBe('Bookly Tours');
});

it('does not audit a no-op settings save', function () {
    $admin = cmsAdmin();
    actingAs($admin);

    $settings = app(GeneralSettings::class);
    $current = $settings->site_name;

    app(UpdateSettingsAction::class)->execute($admin, 'general', ['site_name' => $current]);

    expect(GovernanceAuditLog::where('action', 'settings.update')->count())->toBe(0);
});

it('denies static-page management to an admin lacking manage_cms', function () {
    $adminNoFlag = User::factory()->admin()->create();
    $adminNoFlag->adminPermission()->create(['flags' => []]);
    $page = makeStaticPage();

    expect($adminNoFlag->can('viewAny', StaticPage::class))->toBeFalse()
        ->and($adminNoFlag->can('update', $page))->toBeFalse();

    actingAs(cmsAdmin());
    expect(auth()->user()->can('viewAny', StaticPage::class))->toBeTrue()
        ->and(auth()->user()->can('update', $page))->toBeTrue();
});

it('persists an edited page through the Filament edit form', function () {
    $admin = cmsAdmin();
    actingAs($admin);
    $page = makeStaticPage();

    Livewire::test(EditStaticPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'status' => 'published',
            'title_en' => 'Edited Title',
            'body_en' => 'Edited body',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->status)->toBe(StaticPage::STATUS_PUBLISHED)
        ->and($page->title['en'])->toBe('Edited Title')
        ->and($page->body['en'])->toBe('Edited body')
        ->and($page->updated_by)->toBe($admin->id);

    expect(GovernanceAuditLog::where('target_type', 'static_page')->count())->toBeGreaterThan(0);
});

it('blocks an unknown settings group', function () {
    $admin = cmsAdmin();
    actingAs($admin);

    expect(fn () => app(UpdateSettingsAction::class)->execute($admin, 'unknown', ['foo' => 'bar']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('registers the static_page morph map on the audit trail', function () {
    $admin = cmsAdmin();
    actingAs($admin);
    $page = makeStaticPage();

    app(UpdateStaticPageAction::class)->execute($admin, $page, [
        'slug' => $page->slug,
        'title' => $page->title,
        'body' => $page->body,
        'meta_description' => $page->meta_description,
    ], publish: true);

    $log = GovernanceAuditLog::where('action', 'cms.publish')->first();
    // The morph map resolves static_page back to the StaticPage model instance.
    expect($log->target)->toBeInstanceOf(StaticPage::class);
});