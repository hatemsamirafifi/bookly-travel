<?php

use App\Domains\Wishlist\Models\Wishlist;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->otherTraveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => User::factory()->partner()->create()->id,
        'category_id' => $this->category->id,
        'slug' => 'wishlist-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);

    $this->token = $this->traveler->createToken('test')->plainTextToken;
});

it('lists traveler wishlist', function () {
    Wishlist::create([
        'user_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
    ]);

    $response = getJson('/api/public/traveler/wishlist', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'tour' => [
                    'id',
                    'name',
                    'slug',
                    'price',
                    'rating',
                    'review_count',
                    'location',
                    'duration',
                    'is_available',
                ],
                'added_at',
            ]],
            'meta',
        ])
        ->assertJsonCount(1, 'data');
});

it('returns empty wishlist for new traveler', function () {
    $response = getJson('/api/public/traveler/wishlist', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('adds a tour to wishlist', function () {
    $response = postJson('/api/public/traveler/wishlist', [
        'tour_id' => $this->tour->id,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'tour_id', 'added_at']]);

    expect(Wishlist::where('user_id', $this->traveler->id)->count())->toBe(1);
});

it('returns 409 when adding duplicate tour to wishlist', function () {
    Wishlist::create([
        'user_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
    ]);

    postJson('/api/public/traveler/wishlist', [
        'tour_id' => $this->tour->id,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(409);
});

it('returns 404 when adding non-existent tour to wishlist', function () {
    postJson('/api/public/traveler/wishlist', [
        'tour_id' => 999999,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(404);
});

it('removes a tour from wishlist', function () {
    Wishlist::create([
        'user_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
    ]);

    $response = deleteJson('/api/public/traveler/wishlist/' . $this->tour->id, [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(204);
    expect(Wishlist::where('user_id', $this->traveler->id)->count())->toBe(0);
});

it('returns 404 when removing non-existent wishlist item', function () {
    deleteJson('/api/public/traveler/wishlist/' . $this->tour->id, [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(404);
});

it('returns 401 for unauthenticated wishlist requests', function () {
    getJson('/api/public/traveler/wishlist')->assertStatus(401);
    postJson('/api/public/traveler/wishlist', ['tour_id' => $this->tour->id])->assertStatus(401);
    deleteJson('/api/public/traveler/wishlist/' . $this->tour->id)->assertStatus(401);
});
