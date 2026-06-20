<?php

use App\Domains\Partner\Models\TourDraft;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Domains\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{assertDatabaseHas, getJson, postJson};

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

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'draft-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'draft',
    ]);
});

it('creates a draft via save endpoint', function () {
    $payload = [
        'title' => 'Updated Tour Title',
        'description' => 'Updated description for the tour.',
        'destination' => 'Siena, Italy',
    ];

    $response = postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $payload,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'tour_id', 'partner_id', 'payload', 'status']);

    assertDatabaseHas('tour_drafts', [
        'tour_id' => $this->tour->id,
        'partner_id' => $this->partner->id,
    ]);

    expect($response->json('payload'))->toBeArray();
});

it('returns the most recent draft via latest endpoint', function () {
    TourDraft::create([
        'tour_id' => $this->tour->id,
        'partner_id' => $this->partner->id,
        'payload' => ['title' => 'First draft'],
        'status' => 'draft',
        'auto_saved_at' => now()->subHour(),
    ]);

    TourDraft::create([
        'tour_id' => $this->tour->id,
        'partner_id' => $this->partner->id,
        'payload' => ['title' => 'Second draft'],
        'status' => 'draft',
        'auto_saved_at' => now(),
    ]);

    $response = getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('payload.title', 'Second draft');
});

it('persists draft data correctly via auto-save', function () {
    $payload = [
        'title' => 'Auto-saved Title',
        'description' => 'Auto-saved description content.',
        'itinerary' => ['Day 1: Arrival', 'Day 2: Tour'],
    ];

    // First save
    $response1 = postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $payload,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response1->assertStatus(200);

    // Second save (upsert — same tour_id + partner_id + status=draft)
    $updatedPayload = array_merge($payload, ['title' => 'Updated Auto-saved Title']);
    $response2 = postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $updatedPayload,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response2->assertStatus(200);

    // Only one draft row should exist (upsert behavior)
    expect(TourDraft::where('tour_id', $this->tour->id)
        ->where('partner_id', $this->partner->id)
        ->count())->toBe(1);
});

it('returns 404 for non-existent draft on latest endpoint', function () {
    $response = getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(404);
});

it('returns 404 for tour belonging to another partner', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $response = getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $response->assertStatus(404);
});