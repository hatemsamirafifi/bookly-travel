<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Filament\Pages\DemoContentManager;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('fails closed before the demo action can publish through seeders', function (bool $manageTours) {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_tours' => $manageTours]]);
    $this->actingAs($admin);

    Livewire::test(DemoContentManager::class)
        ->mountAction('generate')
        ->call('callMountedAction')
        ->assertForbidden();

    expect(Tour::count())->toBe(0)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->count())->toBe(0);
})->with([true, false]);
