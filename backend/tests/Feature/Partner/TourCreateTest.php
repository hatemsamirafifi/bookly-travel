<?php

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
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

it('persists difficulty and ordered media with the selected cover on create', function () {
    $response = postJson('/api/partner/tours', [
        'title' => 'Gallery Tour',
        'description' => str_repeat('A guided gallery visit in Florence. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'moderate',
        'media' => [
            ['url' => 'https://images.example.com/side.jpg', 'is_cover' => false],
            ['url' => 'https://images.example.com/cover.jpg', 'is_cover' => true],
        ],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();

    $tour = Tour::findOrFail($response->json('data.id'));
    expect($tour->difficulty_level)->toBe('moderate');
    expect($tour->cover_image_url)->toBe('https://images.example.com/cover.jpg');
    expect($tour->media()->orderBy('sort_order')->pluck('url')->all())->toBe([
        'https://images.example.com/side.jpg',
        'https://images.example.com/cover.jpg',
    ]);
});

it('persists the structured English itinerary from partner creation', function () {
    $itinerary = [
        ['day' => 1, 'title' => 'Arrival', 'stops' => [['title' => 'Meet the guide']]],
        ['day' => 2, 'title' => 'Explore', 'stops' => [['title' => 'Visit the gallery']]],
    ];
    $created = postJson('/api/partner/tours', [
        'title' => 'Structured Tour',
        'description' => str_repeat('A guided visit in Florence. ', 5),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 2,
        'duration_unit' => 'day',
        'difficulty_level' => 'easy',
        'itinerary' => $itinerary,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();

    $tour = Tour::findOrFail($created->json('data.id'));
    expect($tour->translations()->where('locale', 'en')->firstOrFail()->itinerary)->toEqual($itinerary);
});

it('rejects malformed structured itinerary on create and update', function () {
    $base = [
        'title' => 'Structured Tour',
        'description' => str_repeat('A guided visit in Florence. ', 5),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 2,
        'duration_unit' => 'day',
        'difficulty_level' => 'easy',
    ];
    postJson('/api/partner/tours', $base + [
        'itinerary' => [['day' => 1, 'stops' => [['description' => 'Missing stop title']]]],
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['itinerary.0.title', 'itinerary.0.stops.0.title']);

    $created = postJson('/api/partner/tours', $base, ['Authorization' => 'Bearer ' . $this->token])->assertCreated();
    putJson('/api/partner/tours/' . $created->json('data.id'), [
        'itinerary' => [['day' => 1, 'title' => 'Valid day', 'stops' => [['description' => 'Missing stop title']]]],
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['itinerary.0.stops.0.title']);
});

it('round-trips media reordering and cover changes on partner update', function () {
    $created = postJson('/api/partner/tours', [
        'title' => 'Editable Gallery Tour',
        'description' => str_repeat('A guided gallery visit in Florence. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'media' => [
            ['url' => 'https://images.example.com/a.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/b.jpg'],
        ],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();

    $id = $created->json('data.id');
    putJson("/api/partner/tours/{$id}", [
        'difficulty_level' => 'challenging',
        'media' => [
            ['url' => 'https://images.example.com/b.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/a.jpg'],
        ],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    getJson("/api/partner/tours/{$id}", ['Authorization' => 'Bearer ' . $this->token])
        ->assertOk()
        ->assertJsonPath('data.difficulty_level', 'challenging')
        ->assertJsonPath('data.cover_image_url', 'https://images.example.com/b.jpg')
        ->assertJsonPath('data.media.0.url', 'https://images.example.com/b.jpg')
        ->assertJsonPath('data.media.1.url', 'https://images.example.com/a.jpg');
});

it('rejects duplicate gallery URLs instead of silently changing ordering', function () {
    postJson('/api/partner/tours', [
        'title' => 'Invalid Gallery',
        'description' => str_repeat('A guided gallery visit in Florence. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'media' => [
            ['url' => 'https://images.example.com/a.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/a.jpg'],
        ],
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.url', 'media.1.url']);
});

it('stores guide-language codes independently of content translations', function () {
    $response = postJson('/api/partner/tours', [
        'title' => 'Guided Tuscan Tour',
        'description' => str_repeat('A guided tour of the Tuscan countryside. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'guide_languages' => ['DE', 'en', 'es'],
    ], ['Authorization' => 'Bearer ' . $this->token]);

    $response->assertCreated();
    $tour = Tour::findOrFail($response->json('data.id'));
    expect($tour->guideLanguages())->toBe(['de', 'en', 'es']);
    expect($tour->translations()->pluck('locale')->all())->toBe(['en']);
});

it('keeps the partner content read contract while exposing safe derived statuses', function () {
    $created = postJson('/api/partner/tours', [
        'title' => 'English source tour',
        'description' => str_repeat('A guided walk through Florence. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'guide_languages' => ['de', 'en', 'es'],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();

    getJson('/api/partner/tours/' . $created->json('data.id'), [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()
        ->assertJsonPath('data.guide_languages', ['de', 'en', 'es'])
        ->assertJsonPath('data.translation_statuses.es', 'pending')
        ->assertJsonPath('data.translation_statuses.it', 'pending')
        ->assertJsonMissingPath('data.source_hash')
        ->assertJsonMissingPath('data.last_error_code');
});

it('rejects language names where stable guide-language codes are required', function () {
    postJson('/api/partner/tours', [
        'title' => 'Guided Tuscan Tour',
        'description' => str_repeat('A guided tour of the Tuscan countryside. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'guide_languages' => ['German'],
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['guide_languages.0']);
});

it('updates guide metadata and English itinerary without conflating them', function () {
    $create = postJson('/api/partner/tours', [
        'title' => 'Tuscan Tour',
        'description' => str_repeat('A guided tour of the Tuscan countryside. ', 4),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], ['Authorization' => 'Bearer ' . $this->token]);
    $create->assertCreated();

    $tourId = $create->json('data.id');
    $translations = ['en' => [
        'title' => 'English title',
        'description' => 'English description',
        'itinerary' => [['day' => 1, 'title' => 'English day', 'stops' => []]],
    ]];

    putJson("/api/partner/tours/{$tourId}", [
        'guide_languages' => ['de', 'en', 'es'],
        'translations' => $translations,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $tour = Tour::findOrFail($tourId);
    expect($tour->guideLanguages())->toBe(['de', 'en', 'es']);
    foreach ($translations as $locale => $content) {
        expect($tour->translations()->where('locale', $locale)->firstOrFail()->itinerary)
            ->toEqual($content['itinerary']);
    }
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

it('returns 404 for non-partner users', function () {
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

    $response->assertStatus(404);
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
