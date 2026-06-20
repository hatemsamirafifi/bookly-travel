<?php

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);

    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->tourA = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'tour-list-a-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/cover-a.jpg',
    ]);

    $this->tourB = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'tour-list-b-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 2,
        'group_size_max' => 8,
        'price_amount' => 9500,
        'status' => 'draft',
    ]);
});

it('returns paginated tour list for authenticated partner', function () {
    $response = getJson('/api/partner/tours', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);

    expect($response->json('data'))->toHaveCount(2);
});

it('can filter by status', function () {
    $response = getJson('/api/partner/tours?status=published', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['slug'])->toBe($this->tourA->slug);
});

it('returns empty list for partner with no tours', function () {
    $newPartnerUser = User::factory()->partner()->create();
    $newPartner = Partner::create([
        'user_id' => $newPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $newToken = $newPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $response = getJson('/api/partner/tours', [
        'Authorization' => 'Bearer ' . $newToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('partner cannot see another partner tours', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $response = getJson('/api/partner/tours', [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
