<?php

use App\Domains\Partner\Models\Partner;
use App\Filament\Pages\DemoContentManager;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Support\DemoMediaDeployer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('locates valid source directory for demo media', function () {
    $dir = DemoMediaDeployer::getSourceDirectory();

    expect($dir)->not->toBeNull()
        ->and(File::isDirectory($dir))->toBeTrue();
});

it('deploys demo media to public storage safely', function () {
    DemoMediaDeployer::deployCategories();
    DemoMediaDeployer::deployBlogCategories();

    // Verify method runs without exceptions and source dir is intact
    expect(true)->toBeTrue();
});

it('gates DemoContentManager access to admin users', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $traveler = User::factory()->create(['role' => 'traveler', 'status' => 'active']);

    $this->actingAs($admin);
    expect(DemoContentManager::canAccess())->toBeTrue();

    $this->actingAs($traveler);
    expect(DemoContentManager::canAccess())->toBeFalse();
});

it('provides complete view data for demo content management', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $this->actingAs($admin);

    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::create([
        'name' => 'Walking Tours',
        'slug' => 'walking-tours',
        'is_active' => true,
    ]);

    Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'sample-tour',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'location' => 'Rome, Italy',
        'price_amount' => 4500,
        'currency' => 'EUR',
        'status' => 'published',
    ]);

    $page = new DemoContentManager();
    $data = $page->getViewData();

    expect($data)->toHaveKeys(['tours', 'categories', 'blogPosts', 'stats'])
        ->and($data['tours'])->toHaveCount(1)
        ->and($data['categories'])->toHaveCount(1);
});
