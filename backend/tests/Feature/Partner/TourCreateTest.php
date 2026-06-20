<?php

use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Domains\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, assertDatabaseHas, postJson};

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
});

it('creates a tour with valid data', function () {
    $response = postJson('/api/partner/tours', [
        'title' => 'Tuscan Wine Experience',
        'description' => str_repeat('A wonderful tour through the vineyards of Tuscany. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'status']]);

    assertDatabaseHas('tours', [
        'partner_id' => $this->partner->id,
        'status' => 'draft',
    ]);
});

it('returns 422 for invalid data with missing required fields', function () {
    $response = postJson('/api/partner/tours', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'description', 'category', 'destination', 'duration_value', 'duration_unit', 'difficulty_level']);
});

it('returns 401 for unauthenticated requests', function () {
    $response = postJson('/api/partner/tours', [
        'title' => 'Tour',
        'description' => str_repeat('A nice tour. ', 20),
        'category' => 'adventure',
        'destination' => 'Rome, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'moderate',
    ]);

    $response->assertStatus(401);
});

it('returns 403 for non-partner users', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    $response = postJson('/api/partner/tours', [
        'title' => 'Tour',
        'description' => str_repeat('A nice tour. ', 20),
        'category' => 'adventure',
        'destination' => 'Rome, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'moderate',
    ], [
        'Authorization' => 'Bearer ' . $travelerToken,
    ]);

    $response->assertStatus(403);
});

it('scopes tour to the authenticated partner', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $response = postJson('/api/partner/tours', [
        'title' => 'My Exclusive Tour',
        'description' => str_repeat('An exclusive tour experience. ', 10),
        'category' => 'wine-food',
        'destination' => 'Siena, Italy',
        'duration_value' => 5,
        'duration_unit' => 'hour',
        'difficulty_level' => 'challenging',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(201);

    $tour = Tour::where('partner_id', $this->partner->id)->first();
    expect($tour)->not->toBeNull();
    expect($tour->partner_id)->toBe($this->partner->id);
    expect($tour->partner_id)->not->toBe($otherPartner->id);
});