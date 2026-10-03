<?php

// Foundation (T007): shared image-reader compatibility. Typed cover/gallery
// writers belong to later US4; this file only pins read behavior for legacy
// image plus cover/gallery rows.

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $user = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
});

function spec019MakeMediaTour(): Tour
{
    return Tour::create([
        'partner_id' => test()->partner->id,
        'category_id' => Category::where('slug', 'wine-food')->firstOrFail()->id,
        'slug' => 'media-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'draft',
    ]);
}

it('reads new cover/gallery typed rows with cover-first deterministic URLs', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'gallery', 'url' => 'https://images.example.com/b.jpg', 'sort_order' => 1],
        ['type' => 'cover', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 0],
        ['type' => 'gallery', 'url' => 'https://images.example.com/c.jpg', 'sort_order' => 2],
    ]);
    $tour->update(['cover_image_url' => 'https://images.example.com/a.jpg']);

    // New typed rows must be visible (legacy reader only accepted type=image).
    expect($tour->fresh()->allImageUrls())->toBe([
        'https://images.example.com/a.jpg',
        'https://images.example.com/b.jpg',
        'https://images.example.com/c.jpg',
    ]);
});

it('prioritizes the first usable cover-typed row even at a later sort order', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'gallery', 'url' => 'https://images.example.com/first.jpg', 'sort_order' => 0],
        ['type' => 'gallery', 'url' => 'https://images.example.com/second.jpg', 'sort_order' => 1],
        ['type' => 'cover', 'url' => 'https://images.example.com/cover.jpg', 'sort_order' => 5],
    ]);
    // Absent legacy cover: the cover-typed row still leads.
    $tour->update(['cover_image_url' => null]);

    expect($tour->fresh()->allImageUrls())->toBe([
        'https://images.example.com/cover.jpg',
        'https://images.example.com/first.jpg',
        'https://images.example.com/second.jpg',
    ]);
});

it('prefers the cover-typed row over a stale legacy cover and never prepends the stale URL', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'gallery', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 0],
        ['type' => 'cover', 'url' => 'https://images.example.com/b.jpg', 'sort_order' => 1],
    ]);
    $tour->update(['cover_image_url' => 'https://images.example.com/stale-legacy.jpg']);

    expect($tour->fresh()->allImageUrls())->toBe([
        'https://images.example.com/b.jpg',
        'https://images.example.com/a.jpg',
    ]);
});

it('reads identically through loaded and unloaded media relations', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'gallery', 'url' => 'https://images.example.com/b.jpg', 'sort_order' => 1],
        ['type' => 'cover', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 0],
    ]);
    $tour->update(['cover_image_url' => null]);

    $expected = [
        'https://images.example.com/a.jpg',
        'https://images.example.com/b.jpg',
    ];

    // Unloaded relation: lazy query path.
    expect($tour->fresh()->allImageUrls())->toBe($expected);
    // Loaded relation: eager collection path.
    expect($tour->fresh()->load('media')->allImageUrls())->toBe($expected);
});

it('deduplicates legacy repeated URLs preserving order and cover selection', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'image', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 0],
        ['type' => 'image', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 1],
        ['type' => 'image', 'url' => 'https://images.example.com/b.jpg', 'sort_order' => 2],
    ]);
    $tour->update(['cover_image_url' => 'https://images.example.com/a.jpg']);

    expect($tour->fresh()->allImageUrls())->toBe([
        'https://images.example.com/a.jpg',
        'https://images.example.com/b.jpg',
    ]);
});

it('uses the legacy cover only when no usable media exists and never prepends it to a valid gallery', function () {
    // Media-empty tour falls back to the real legacy cover.
    $empty = spec019MakeMediaTour();
    $empty->update(['cover_image_url' => 'https://images.example.com/legacy.jpg']);
    expect($empty->fresh()->allImageUrls())->toBe(['https://images.example.com/legacy.jpg']);

    // Valid gallery: a stale legacy cover must NOT be prepended as an extra photo.
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'image', 'url' => 'https://images.example.com/a.jpg', 'sort_order' => 0],
        ['type' => 'image', 'url' => 'https://images.example.com/b.jpg', 'sort_order' => 1],
    ]);
    $tour->update(['cover_image_url' => 'https://images.example.com/stale-legacy.jpg']);
    expect($tour->fresh()->allImageUrls())->toBe([
        'https://images.example.com/a.jpg',
        'https://images.example.com/b.jpg',
    ]);

    // No photos at all yields an empty gallery, never an invented URL.
    $bare = spec019MakeMediaTour();
    expect($bare->fresh()->allImageUrls())->toBe([]);
});

it('ignores non-HTTP(S) and invalid media URLs', function () {
    $tour = spec019MakeMediaTour();
    $tour->media()->createMany([
        ['type' => 'gallery', 'url' => 'ftp://images.example.com/a.jpg', 'sort_order' => 0],
        ['type' => 'gallery', 'url' => 'not-a-url', 'sort_order' => 1],
        ['type' => 'gallery', 'url' => 'https://images.example.com/ok.jpg', 'sort_order' => 2],
    ]);
    $tour->update(['cover_image_url' => null]);

    expect($tour->fresh()->allImageUrls())->toBe(['https://images.example.com/ok.jpg']);
});
