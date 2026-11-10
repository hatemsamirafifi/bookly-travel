<?php

declare(strict_types=1);

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\TourMedia;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('rejects unauthenticated requests to partner hugerte media', function () {
    $this->getJson('/api/partner/hugerte/media')->assertUnauthorized();
});

it('rejects non-partner users from partner hugerte media', function () {
    $traveler = User::factory()->create();
    Sanctum::actingAs($traveler, ['traveler']);

    $this->getJson('/api/partner/hugerte/media')->assertNotFound();
});

it('only exposes media belonging to the authenticated partner', function () {
    $partnerAUser = User::factory()->create();
    $partnerA = Partner::create([
        'user_id' => $partnerAUser->id,
        'company_name' => 'Partner A Tours',
        'contact_name' => 'Alice',
        'contact_phone' => '+1234567890',
        'business_registration' => 'REG-A',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $partnerBUser = User::factory()->create();
    $partnerB = Partner::create([
        'user_id' => $partnerBUser->id,
        'company_name' => 'Partner B Tours',
        'contact_name' => 'Bob',
        'contact_phone' => '+0987654321',
        'business_registration' => 'REG-B',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Category']);

    $tourA = Tour::create([
        'partner_id' => $partnerA->id,
        'category_id' => $category->id,
        'slug' => 'tour-a',
        'location' => 'Rome',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $tourB = Tour::create([
        'partner_id' => $partnerB->id,
        'category_id' => $category->id,
        'slug' => 'tour-b',
        'location' => 'Paris',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 15,
        'price_amount' => 8000,
        'status' => 'published',
    ]);

    $mediaA = TourMedia::create([
        'tour_id' => $tourA->id,
        'type' => 'image',
        'url' => 'https://cdn.bookly.test/uploads/partner-a-image.jpg',
        'alt_text' => 'Partner A Colosseum',
        'sort_order' => 1,
    ]);

    $mediaB = TourMedia::create([
        'tour_id' => $tourB->id,
        'type' => 'image',
        'url' => 'https://cdn.bookly.test/uploads/partner-b-image.jpg',
        'alt_text' => 'Partner B Eiffel Tower',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($partnerAUser, ['partner']);

    $response = $this->getJson('/api/partner/hugerte/media');
    $response->assertOk()
        ->assertJsonCount(1, 'assets')
        ->assertJsonPath('assets.0.id', $mediaA->id)
        ->assertJsonPath('assets.0.url', 'https://cdn.bookly.test/uploads/partner-a-image.jpg')
        ->assertJsonPath('assets.0.alt', 'Partner A Colosseum');
});

it('includes cover and gallery types for the authenticated partner', function () {
    $partnerUser = User::factory()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'company_name' => 'Partner C Tours',
        'contact_name' => 'Charlie',
        'contact_phone' => '+1122334455',
        'business_registration' => 'REG-C',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['slug' => 'test-cat-c'], ['name' => 'Category C']);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'tour-c',
        'location' => 'Venice',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4500,
        'status' => 'published',
    ]);

    $coverMedia = TourMedia::create([
        'tour_id' => $tour->id,
        'type' => 'cover',
        'url' => 'https://cdn.bookly.test/uploads/gondola-cover.jpg',
        'alt_text' => 'Venice Gondola Cover',
        'sort_order' => 0,
    ]);

    $galleryMedia = TourMedia::create([
        'tour_id' => $tour->id,
        'type' => 'gallery',
        'url' => 'https://cdn.bookly.test/uploads/canal-gallery.jpg',
        'alt_text' => 'Grand Canal Gallery',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($partnerUser, ['partner']);

    $response = $this->getJson('/api/partner/hugerte/media');
    $response->assertOk()
        ->assertJsonCount(2, 'assets')
        ->assertJsonFragment(['url' => 'https://cdn.bookly.test/uploads/gondola-cover.jpg'])
        ->assertJsonFragment(['url' => 'https://cdn.bookly.test/uploads/canal-gallery.jpg']);
});
