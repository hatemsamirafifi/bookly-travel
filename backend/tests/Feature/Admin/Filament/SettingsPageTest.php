<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Settings\GeneralSettings;
use App\Filament\Pages\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Livewire\Livewire;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function settingsAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_settings' => true]]);

    return $admin->fresh('adminPermission');
}

it('renders the platform settings page for a manage_settings admin', function () {
    actingAs(settingsAdmin());

    Livewire::test(Settings::class)
        ->assertSuccessful()
        ->assertSeeHtml('site_name');
});

it('saves general settings through the Filament form and audits the change', function () {
    $admin = settingsAdmin();
    actingAs($admin);

    Livewire::test(Settings::class)
        ->fillForm([
            'general' => ['site_name' => 'Bookly Tours'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->site_name)->toBe('Bookly Tours');

    $audit = GovernanceAuditLog::where('action', 'settings.update')->get()
        ->first(fn ($log) => ($log->metadata['group'] ?? null) === 'general');
    expect($audit)->not->toBeNull()
        ->and($audit->target_type)->toBe('setting')
        ->and($audit->metadata['properties']['site_name']['after'])->toBe('Bookly Tours');
});

it('denies the settings page to an admin lacking manage_settings', function () {
    $adminNoFlag = User::factory()->admin()->create();
    $adminNoFlag->adminPermission()->create(['flags' => []]);

    actingAs($adminNoFlag);
    expect(Settings::canAccess())->toBeFalse();

    actingAs(settingsAdmin());
    expect(Settings::canAccess())->toBeTrue();
});