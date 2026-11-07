<?php

declare(strict_types=1);

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\AdminE2EFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

it('rejects execution when environment is not testing', function (string $env) {
    app()['env'] = $env;
    Config::set('app.allow_admin_e2e_fixtures', true);

    expect(fn () => (new AdminE2EFixtureSeeder)->run())
        ->toThrow(
            RuntimeException::class,
            'AdminE2EFixtureSeeder is strictly prohibited from running outside the testing environment.'
        );
})->with(['production', 'staging', 'local', 'development'])
    ->after(function () {
        app()['env'] = 'testing';
    });

it('rejects execution when opt-in flag is disabled or missing in testing environment', function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', false);
    putenv('ALLOW_ADMIN_E2E_FIXTURES=false');

    expect(fn () => (new AdminE2EFixtureSeeder)->run())
        ->toThrow(
            RuntimeException::class,
            'AdminE2EFixtureSeeder requires explicit authorization via ALLOW_ADMIN_E2E_FIXTURES=true'
        );
})->after(function () {
    putenv('ALLOW_ADMIN_E2E_FIXTURES');
});

it('rejects execution when target database is not designated as disposable test database', function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', true);

    $originalDb = Config::get('database.connections.' . Config::get('database.default') . '.database');
    Config::set('database.connections.' . Config::get('database.default') . '.database', 'bookly_production');

    expect(fn () => (new AdminE2EFixtureSeeder)->run())
        ->toThrow(
            RuntimeException::class,
            'is not designated as a disposable test database'
        );

    Config::set('database.connections.' . Config::get('database.default') . '.database', $originalDb);
});

it('successfully seeds fixtures when positively authorized with designated test database', function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', true);

    (new AdminE2EFixtureSeeder)->run();

    // Verify Super Admin
    $admin = User::where('email', 'admin@bookly.test')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe('admin')
        ->and($admin->adminRoles()->where('slug', 'super_admin')->exists())->toBeTrue()
        ->and($admin->authorProfile)->not->toBeNull();

    // Verify Restricted Support Agent
    $restricted = User::where('email', 'restricted-admin@bookly.test')->first();
    expect($restricted)->not->toBeNull()
        ->and($restricted->adminRoles()->where('slug', 'support_agent')->exists())->toBeTrue();

    // Verify Moderation Targets
    expect(User::where('name', 'E2E Moderate Traveler')->count())->toBe(1)
        ->and(Partner::whereHas('profile', fn ($q) => $q->where('company_name', 'E2E Pending Coastal Partner'))->exists())->toBeTrue()
        ->and(Partner::whereHas('profile', fn ($q) => $q->where('company_name', 'E2E Approved Amalfi Partner'))->exists())->toBeTrue();

    // Verify Tours, Booking, and Content
    expect(Tour::where('slug', 'e2e-pending-amalfi-tour')->exists())->toBeTrue()
        ->and(Tour::where('slug', 'e2e-published-capri-tour')->exists())->toBeTrue()
        ->and(Booking::where('reference', 'BK-E2E901')->exists())->toBeTrue()
        ->and(BlogPost::where('slug', 'e2e-sanitized-guide')->exists())->toBeTrue()
        ->and(BlogPost::where('slug', 'e2e-fallback-post')->exists())->toBeTrue();
});

it('is completely idempotent when executed multiple times', function () {
    app()['env'] = 'testing';
    Config::set('app.allow_admin_e2e_fixtures', true);

    $seeder = new AdminE2EFixtureSeeder;
    $seeder->run();
    $seeder->run(); // Second execution

    expect(User::where('email', 'admin@bookly.test')->count())->toBe(1)
        ->and(User::where('email', 'restricted-admin@bookly.test')->count())->toBe(1)
        ->and(User::where('name', 'E2E Moderate Traveler')->count())->toBe(1)
        ->and(Tour::where('slug', 'e2e-pending-amalfi-tour')->count())->toBe(1)
        ->and(Booking::where('reference', 'BK-E2E901')->count())->toBe(1);
});
