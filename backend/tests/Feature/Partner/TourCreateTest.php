<?php

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('creates distinct stable nonempty links for repeated valid source titles', function (string $title) {
    $ids = [];
    $slugs = [];
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $created = postJson('/api/partner/tours', [
            'title' => $title,
            'description' => str_repeat('A guided visit through historic Florence. ', 4),
            'category' => 'wine-food',
            'destination' => 'Florence, Italy',
            'duration_value' => 3,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
        ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();
        $id = $created->json('data.id');
        $detail = getJson('/api/partner/tours/' . $id, ['Authorization' => 'Bearer ' . $this->token])->assertOk();
        $slug = $detail->json('data.slug');
        expect($slug)->toBeString()->not->toBe('');
        expect($detail->json('data.translations.0.title'))->toBe($title);
        putJson('/api/partner/tours/' . $id, ['destination' => 'Rome, Italy'], ['Authorization' => 'Bearer ' . $this->token])->assertOk();
        getJson('/api/partner/tours/' . $id, ['Authorization' => 'Bearer ' . $this->token])->assertOk()->assertJsonPath('data.slug', $slug);
        $ids[] = $id;
        $slugs[] = $slug;
    }
    expect($ids[0])->not->toBe($ids[1]);
    expect($slugs[0])->not->toBe($slugs[1]);
})->with([
    'emoji-only boundary' => [str_repeat("\u{1F30D}", 120)],
    'repeated readable title' => ['Repeated Florence source tour'],
]);

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

// ---------------------------------------------------------------------------
// Spec 019 US1 (T010): owned English source round trips, precedence,
// omission/null/[] semantics, exact boundaries, IDOR, forgery and atomicity.
// ---------------------------------------------------------------------------

function spec019SourceCreatePayload(array $overrides = []): array
{
    $base = [
        'title' => 'Morning walking tour',
        'description' => str_repeat('A wonderful tour through Florence. ', 5),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'translations' => ['en' => [
            'title' => 'Morning walking tour',
            'description' => str_repeat('A wonderful tour through Florence. ', 5),
            'highlights' => ['Old-town route', 'Local guide'],
            'inclusions' => ['Live guide', 'Entry tickets'],
            'exclusions' => ['Lunch'],
            'meeting_point' => 'In front of the metro exit',
            'cancellation_policy' => 'Cancel up to 24 hours in advance for a full refund.',
            'itinerary' => [
                ['day' => 1, 'title' => 'Old town', 'description' => null, 'stops' => [
                    ['title' => 'Main square', 'description' => null, 'duration_minutes' => 30],
                ]],
                ['day' => 2, 'title' => 'Museums', 'description' => 'Gallery day', 'stops' => []],
            ],
            'important_information' => ['Comfortable walking shoes recommended'],
        ]],
    ];

    return array_merge($base, $overrides);
}

it('round-trips all nine English source fields on create and read', function () {
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertCreated();

    $tour = Tour::findOrFail($created->json('data.id'));
    $en = $tour->translations()->where('locale', 'en')->firstOrFail();

    expect($en->title)->toBe('Morning walking tour');
    // The global TrimStrings middleware trims stored input; the payload's
    // trailing space is not preserved.
    expect($en->description)->toBe(trim(str_repeat('A wonderful tour through Florence. ', 5)));
    expect($en->highlights)->toBe(['Old-town route', 'Local guide']);
    expect($en->inclusions)->toBe(['Live guide', 'Entry tickets']);
    expect($en->exclusions)->toBe(['Lunch']);
    expect($en->meeting_point)->toBe('In front of the metro exit');
    expect($en->cancellation_policy)->toBe('Cancel up to 24 hours in advance for a full refund.');
    expect($en->itinerary)->toEqual([
        ['day' => 1, 'title' => 'Old town', 'description' => null, 'stops' => [
            ['title' => 'Main square', 'description' => null, 'duration_minutes' => 30],
        ]],
        ['day' => 2, 'title' => 'Museums', 'description' => 'Gallery day', 'stops' => []],
    ]);
    expect($en->important_information)->toBe(['Comfortable walking shoes recommended']);

    // Partner read returns the owned translations array including the EN row.
    getJson('/api/partner/tours/' . $tour->id, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()
        ->assertJsonPath('data.translations.0.locale', 'en')
        ->assertJsonPath('data.translations.0.title', 'Morning walking tour')
        ->assertJsonPath('data.translations.0.meeting_point', 'In front of the metro exit')
        ->assertJsonPath('data.translations.0.highlights', ['Old-town route', 'Local guide'])
        ->assertJsonPath('data.translations.0.important_information', ['Comfortable walking shoes recommended']);
});

it('round-trips a nested English update while preserving omitted fields', function () {
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertCreated();
    $id = $created->json('data.id');

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['meeting_point' => 'New meeting point']],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->meeting_point)->toBe('New meeting point');
    expect($en->title)->toBe('Morning walking tour');
    expect($en->highlights)->toBe(['Old-town route', 'Local guide']);
    expect($en->itinerary[0]['title'])->toBe('Old town');
});

it('prefers explicit nested English keys over top-level shorthand', function () {
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload([
        'title' => 'Shorthand title',
        'translations' => ['en' => [
            'title' => 'Nested title',
            'description' => str_repeat('Nested description wins. ', 5),
        ]],
    ]), ['Authorization' => 'Bearer ' . $this->token])->assertCreated();

    $en = Tour::findOrFail($created->json('data.id'))->translations()->where('locale', 'en')->firstOrFail();
    expect($en->title)->toBe('Nested title');

    // Shorthand fills absent nested keys.
    $created2 = postJson('/api/partner/tours', [
        'title' => 'Shorthand only title',
        'description' => str_repeat('Shorthand only description. ', 5),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], ['Authorization' => 'Bearer ' . $this->token])->assertCreated();
    $en2 = Tour::findOrFail($created2->json('data.id'))->translations()->where('locale', 'en')->firstOrFail();
    expect($en2->title)->toBe('Shorthand only title');
});

it('treats null as clear and empty array as clear while omitted keys preserve values', function () {
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertCreated();
    $id = $created->json('data.id');

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['meeting_point' => null, 'highlights' => []]],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->meeting_point)->toBeNull();
    expect($en->highlights)->toBe([]);
    // Omitted keys preserve their stored values.
    expect($en->title)->toBe('Morning walking tour');
    expect($en->inclusions)->toBe(['Live guide', 'Entry tickets']);
    expect($en->cancellation_policy)->toBe('Cancel up to 24 hours in advance for a full refund.');
});

it('rejects null titles and over-limit source fields with exact paths', function () {
    postJson('/api/partner/tours', spec019SourceCreatePayload([
        'translations' => ['en' => ['title' => null]],
    ]), ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.title']);

    postJson('/api/partner/tours', spec019SourceCreatePayload([
        'translations' => ['en' => [
            'title' => str_repeat('x', 121),
            'highlights' => array_fill(0, 31, 'ok'),
            'inclusions' => [str_repeat('x', 501)],
            'meeting_point' => str_repeat('x', 501),
            'cancellation_policy' => str_repeat('x', 2001),
            'important_information' => array_fill(0, 31, 'ok'),
        ]],
    ]), ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'translations.en.title',
            'translations.en.highlights',
            'translations.en.inclusions.0',
            'translations.en.meeting_point',
            'translations.en.cancellation_policy',
            'translations.en.important_information',
        ]);

    // Top-level create description minimum is preserved.
    postJson('/api/partner/tours', [
        'title' => 'Short description tour',
        'description' => 'Too short.',
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('rejects invalid itinerary boundaries with exact nested paths', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');

    // 31 days exceeds the 30-day maximum.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['itinerary' => array_map(
            fn ($day) => ['day' => $day, 'title' => "Day {$day}", 'stops' => []],
            range(1, 31)
        )]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.itinerary']);

    // 21 stops exceeds the 20-stop maximum.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['itinerary' => [[
            'day' => 1, 'title' => 'Crowded day',
            'stops' => array_map(fn ($i) => ['title' => "Stop {$i}"], range(1, 21)),
        ]]]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.itinerary.0.stops']);

    // Out-of-range, fractional, blank and null-stop values fail atomically.
    $before = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->itinerary;
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['itinerary' => [
            ['day' => 0, 'title' => 'Day zero', 'stops' => []],
            ['day' => 31, 'title' => 'Day thirty-one', 'stops' => []],
            ['day' => 1.5, 'title' => 'Fractional day', 'stops' => []],
            ['day' => 1, 'title' => '   ', 'stops' => []],
            ['day' => 2, 'title' => 'Bad stops', 'stops' => [
                ['title' => ''],
                ['title' => 'Timed stop', 'duration_minutes' => 0],
                ['title' => 'Long stop', 'duration_minutes' => 1441],
                ['title' => 'Fractional stop', 'duration_minutes' => 1.5],
            ]],
            ['day' => 3, 'title' => 'Null stops rejected', 'stops' => null],
        ]]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors([
            'translations.en.itinerary.0.day',
            'translations.en.itinerary.1.day',
            'translations.en.itinerary.2.day',
            'translations.en.itinerary.3.title',
            'translations.en.itinerary.4.stops.0.title',
            'translations.en.itinerary.4.stops.1.duration_minutes',
            'translations.en.itinerary.4.stops.2.duration_minutes',
            'translations.en.itinerary.4.stops.3.duration_minutes',
            'translations.en.itinerary.5.stops',
        ]);

    // No partial itinerary change was accepted.
    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->itinerary)->toEqual($before);
});

it('accepts boundary-valid itineraries with canonical integer day and duration types', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['itinerary' => [
            ['day' => '1', 'title' => 'Zero stops valid', 'description' => null, 'stops' => []],
            ['day' => '30', 'title' => str_repeat('x', 160), 'description' => str_repeat('x', 2000), 'stops' => [
                ['title' => str_repeat('y', 160), 'description' => null, 'duration_minutes' => '1'],
                ['title' => 'Max duration', 'duration_minutes' => '1440'],
                ['title' => 'Null duration kept', 'duration_minutes' => null],
            ]],
            ['day' => 2, 'title' => 'Repeated day number stays valid', 'stops' => [['title' => 'Stop']]],
            ['day' => 2, 'title' => 'Second day two keeps order', 'stops' => [['title' => 'Other stop']]],
        ]]],
    ], $headers)->assertOk();

    $itinerary = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->itinerary;
    expect(array_column($itinerary, 'day'))->toBe([1, 30, 2, 2]);
    // Materialized day/duration values persist as JSON numbers, never digit strings.
    expect($itinerary[0]['day'])->toBeInt();
    expect($itinerary[1]['stops'][0]['duration_minutes'])->toBe(1);
    expect($itinerary[1]['stops'][1]['duration_minutes'])->toBe(1440);
    expect($itinerary[1]['stops'][2]['duration_minutes'])->toBeNull();
    // Reopening preserves the accepted order and values.
    getJson("/api/partner/tours/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.translations.0.itinerary.2.title', 'Repeated day number stays valid')
        ->assertJsonPath('data.translations.0.itinerary.3.title', 'Second day two keeps order');
});

it('denies cross-partner updates with 404 and leaves the tour unchanged', function () {
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertCreated();
    $id = $created->json('data.id');

    $otherUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    // Partner-record scoping must not compare tour.partner_id to the user id.
    expect($otherPartner->id)->not->toBe($this->partner->id);
    expect($otherPartner->user_id)->toBe($otherUser->id);
    $otherToken = $otherUser->createToken('test', ['partner'])->plainTextToken;

    // The test-process auth guard caches its user across HTTP-helper calls;
    // reset it so the second Bearer token resolves to the second principal
    // (in-test artifact only; production resolves per request).
    auth()->forgetGuards();

    // Principal proof: the second token acts as the second user and maps to
    // the distinct partner record on writes.
    $owned = postJson('/api/partner/tours', [
        'title' => 'Second owner tour',
        'description' => str_repeat('A second owner tour. ', 5),
        'category' => 'wine-food',
        'destination' => 'Siena, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], ['Authorization' => 'Bearer ' . $otherToken])->assertCreated();
    expect(Tour::findOrFail($owned->json('data.id'))->partner_id)->toBe($otherPartner->id);

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => 'Hijacked title']],
    ], ['Authorization' => 'Bearer ' . $otherToken])->assertNotFound();

    getJson("/api/partner/tours/{$id}", ['Authorization' => 'Bearer ' . $otherToken])->assertNotFound();

    // Cross-owner malformed payloads stay 404, never 422: scoped lookup wins.
    putJson("/api/partner/tours/{$id}", [
        'title' => ['not', 'a', 'string'],
        'translations' => ['en' => ['itinerary' => 'not-an-array', 'source_hash' => 'forged']],
    ], ['Authorization' => 'Bearer ' . $otherToken])->assertNotFound();

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->title)->toBe('Morning walking tour');
});

it('returns 404 for unknown tours before validating malformed payloads', function () {
    putJson('/api/partner/tours/987654321', [
        'title' => ['not', 'a', 'string'],
        'translations' => ['en' => ['itinerary' => 'not-an-array', 'source_hash' => 'forged']],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertNotFound();

    expect(Tour::where('id', 987654321)->exists())->toBeFalse();
});

it('rejects authored locales and platform-owned state with 422 and no writes', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $tourCount = Tour::count();

    postJson('/api/partner/tours', spec019SourceCreatePayload([
        'translations' => ['es' => ['title' => 'Authored Spanish']],
    ]), $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.es']);
    expect(Tour::count())->toBe($tourCount);

    postJson('/api/partner/tours', array_merge(spec019SourceCreatePayload(), [
        'source_hash' => str_repeat('a', 64),
        'provider_error' => null,
    ]), $headers)->assertUnprocessable();
    expect(Tour::count())->toBe($tourCount);

    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');
    $before = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes();

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => 'Changed', 'provider_body' => null]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.provider_body']);

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['it' => ['title' => 'Authored Italian']],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.it']);

    $after = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes();
    expect($after['title'])->toBe($before['title']);
    expect($after['updated_at'])->toBe($before['updated_at']);
});

it('rejects invalid media atomically without partial content changes', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $tourCount = Tour::count();

    postJson('/api/partner/tours', array_merge(spec019SourceCreatePayload(), [
        'media' => [
            ['url' => 'https://images.example.com/a.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/a.jpg'],
        ],
    ]), $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.url', 'media.1.url']);
    expect(Tour::count())->toBe($tourCount);

    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => 'Changed with bad media']],
        'media' => [
            ['url' => 'https://images.example.com/a.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/a.jpg'],
        ],
    ], $headers)->assertUnprocessable();

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->title)->toBe('Morning walking tour');
    expect(Tour::findOrFail($id)->media()->count())->toBe(0);
});

it('rejects associative objects for source lists, days and stops with exact paths', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $tourCount = Tour::count();

    // Create: authored JSON objects are not JSON lists.
    $payload = spec019SourceCreatePayload();
    $payload['translations'] = ['en' => [
        'title' => 'Object tour',
        'description' => str_repeat('An object-shaped tour payload. ', 5),
        'highlights' => ['primary' => 'Old-town route'],
    ]];
    postJson('/api/partner/tours', $payload, $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.highlights']);
    expect(Tour::count())->toBe($tourCount);

    $payload = spec019SourceCreatePayload();
    $payload['translations'] = ['en' => [
        'title' => 'Object tour',
        'description' => str_repeat('An object-shaped tour payload. ', 5),
        'itinerary' => ['first' => ['day' => 1, 'title' => 'Day', 'stops' => []]],
    ]];
    postJson('/api/partner/tours', $payload, $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.itinerary']);
    expect(Tour::count())->toBe($tourCount);

    // Shorthand top-level lists obey the same canonical shape.
    $payload = spec019SourceCreatePayload();
    $payload['highlights'] = ['primary' => 'Old-town route'];
    unset($payload['translations']);
    postJson('/api/partner/tours', $payload, $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['highlights']);
    expect(Tour::count())->toBe($tourCount);

    // Update: object-shaped stops fail atomically; stored source is untouched.
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');
    $before = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes();

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['itinerary' => [
            ['day' => 1, 'title' => 'Old town', 'stops' => ['main' => ['title' => 'Stop']]],
        ]]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['translations.en.itinerary.0.stops']);

    putJson("/api/partner/tours/{$id}", [
        'itinerary' => ['first' => ['day' => 1, 'title' => 'Day', 'stops' => []]],
    ], $headers)->assertUnprocessable()
        ->assertJsonValidationErrors(['itinerary']);

    $after = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes();
    expect($after)->toEqual($before);

    // Empty lists and zero-stop days remain valid canonical input.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['highlights' => [], 'itinerary' => [
            ['day' => 1, 'title' => 'Old town', 'stops' => []],
        ]]],
    ], $headers)->assertOk();
    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->highlights)->toBe([]);
    expect($en->itinerary[0]['stops'])->toBe([]);
});

it('round-trips custom group sizes on create, read and reopen', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $created = postJson('/api/partner/tours', array_merge(spec019SourceCreatePayload(), [
        'group_size_min' => 2,
        'group_size_max' => 7,
    ]), $headers)->assertCreated();
    $id = $created->json('data.id');

    getJson("/api/partner/tours/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.group_size_min', 2)
        ->assertJsonPath('data.group_size_max', 7);

    // An unrelated source edit reopens with the group sizes intact.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => 'Unrelated title change']],
    ], $headers)->assertOk();

    getJson("/api/partner/tours/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.group_size_min', 2)
        ->assertJsonPath('data.group_size_max', 7)
        ->assertJsonPath('data.translations.0.title', 'Unrelated title change');
});

it('rolls back fully persisted source and media when a late write fails', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];

    $created = postJson('/api/partner/tours', array_merge(spec019SourceCreatePayload(), [
        'group_size_min' => 2,
        'group_size_max' => 7,
        'media' => [
            ['url' => 'https://images.example.com/old-cover.jpg', 'is_cover' => true],
            ['url' => 'https://images.example.com/old-second.jpg'],
        ],
    ]), $headers)->assertCreated();
    $id = $created->json('data.id');

    $beforeTour = Tour::findOrFail($id)->getAttributes();
    $beforeEn = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes();
    $beforeMedia = Tour::findOrFail($id)->media()->orderBy('sort_order')->get()->map(
        fn ($item) => $item->only(['id', 'tour_id', 'type', 'url', 'sort_order'])
    )->all();
    $beforeStates = DB::table('tour_translation_states')->where('tour_id', $id)->orderBy('locale')->get()->map(fn ($row) => (array) $row)->all();

    // Reset the queue fake so only dispatches from the failing write count.
    Queue::fake();

    // Controlled LATE persistence failure: TourService::updateTour writes in
    // basic -> media -> EN -> states order, all inside the Tour-first lock
    // transaction. The scoped EN `saved` observer below fires only AFTER the
    // changed EN row (and the replacement media) are persisted, then throws;
    // the transaction must undo all of it. TourTranslation defines no other
    // model listeners, so flushing in `finally` only removes this injection.
    $arrivals = [];
    TourTranslation::saved(function (TourTranslation $model) use ($id, &$arrivals) {
        if ($model->tour_id === $id && $model->locale === 'en') {
            $arrivals[] = $model->title;
            throw new RuntimeException('spec019-injected-late-persistence-failure');
        }
    });

    $this->withoutExceptionHandling();
    try {
        putJson("/api/partner/tours/{$id}", [
            'destination' => 'Siena, Italy',
            'group_size_min' => 4,
            'translations' => ['en' => ['title' => 'Changed late']],
            'media' => [
                ['url' => 'https://images.example.com/new-cover.jpg', 'is_cover' => true],
            ],
        ]);
        $this->fail('Expected the injected late persistence failure.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('spec019-injected-late-persistence-failure');
    } finally {
        TourTranslation::flushEventListeners();
    }

    // The injection arrived exactly once, on the changed EN save.
    expect($arrivals)->toBe(['Changed late']);

    // Everything is restored: basic fields, EN source, ordered media with
    // ids/order/cover, and derivative states.
    expect(Tour::findOrFail($id)->getAttributes())->toBe($beforeTour);
    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->getAttributes())->toBe($beforeEn);
    expect(Tour::findOrFail($id)->media()->orderBy('sort_order')->get()->map(
        fn ($item) => $item->only(['id', 'tour_id', 'type', 'url', 'sort_order'])
    )->all())->toBe($beforeMedia);
    expect(Tour::findOrFail($id)->cover_image_url)->toBe('https://images.example.com/old-cover.jpg');
    expect(DB::table('tour_translation_states')->where('tour_id', $id)->orderBy('locale')->get()->map(fn ($row) => (array) $row)->all())->toBe($beforeStates);
    // No generation dispatch survives the failed write. (The Queue fake also
    // records the pre-existing synchronous Tour::saved search-index dispatch
    // from EventServiceProvider; moving index dispatch after commit is US3
    // T042 scope, not asserted here.)
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
});

it('applies destination, duration, group and category aliases on update and read', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure']);

    $created = postJson('/api/partner/tours', array_merge(spec019SourceCreatePayload(), [
        'group_size_min' => 2,
        'group_size_max' => 7,
    ]), $headers)->assertCreated();
    $id = $created->json('data.id');

    putJson("/api/partner/tours/{$id}", [
        'destination' => 'Siena, Italy',
        'duration_value' => 2,
        'duration_unit' => 'day',
        'group_size_min' => 4,
        'group_size_max' => 8,
        'category' => 'adventure',
    ], $headers)->assertOk();

    $tour = Tour::findOrFail($id);
    expect($tour->location)->toBe('Siena, Italy');
    expect($tour->duration_minutes)->toBe(2880);
    expect($tour->group_size_min)->toBe(4);
    expect($tour->group_size_max)->toBe(8);
    expect($tour->category_id)->toBe(Category::where('slug', 'adventure')->firstOrFail()->id);

    getJson("/api/partner/tours/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.location', 'Siena, Italy')
        ->assertJsonPath('data.group_size_min', 4)
        ->assertJsonPath('data.group_size_max', 8);
});

it('preserves existing null source fields through unrelated basic saves', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];

    $payload = spec019SourceCreatePayload();
    $payload['translations'] = ['en' => [
        'title' => 'Nulls tour',
        'description' => str_repeat('A tour with null source fields. ', 5),
        'meeting_point' => null,
        'highlights' => null,
        'itinerary' => null,
    ]];
    $created = postJson('/api/partner/tours', $payload, $headers)->assertCreated();
    $id = $created->json('data.id');

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->meeting_point)->toBeNull();
    expect($en->highlights)->toBeNull();
    expect($en->itinerary)->toBeNull();

    $hash = app(TourTranslationService::class)->sourceHash($en->fresh());
    $states = DB::table('tour_translation_states')->where('tour_id', $id)->orderBy('locale')->get()->map(fn ($row) => (array) $row)->all();

    // Unrelated basic save carries no source keys at all.
    putJson("/api/partner/tours/{$id}", ['location' => 'Elsewhere, Italy'], $headers)->assertOk();

    $fresh = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($fresh->meeting_point)->toBeNull();
    expect($fresh->highlights)->toBeNull();
    expect($fresh->itinerary)->toBeNull();
    expect($fresh->title)->toBe('Nulls tour');
    expect(app(TourTranslationService::class)->sourceHash($fresh))->toBe($hash);
    expect(DB::table('tour_translation_states')->where('tour_id', $id)->orderBy('locale')->get()->map(fn ($row) => (array) $row)->all())->toEqual($states);

    getJson("/api/partner/tours/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.translations.0.meeting_point', null)
        ->assertJsonPath('data.location', 'Elsewhere, Italy');
});

it('keeps a single draft across failed submit and update retries', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');

    // Missing pricing tiers and cover image: submission refused, no state change.
    postJson("/api/partner/tours/{$id}/submit", [], $headers)->assertUnprocessable();

    // An update retry touches the SAME tour; resubmission still refused.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => 'Retried title']],
    ], $headers)->assertOk();
    postJson("/api/partner/tours/{$id}/submit", [], $headers)->assertUnprocessable();

    expect(Tour::where('partner_id', $this->partner->id)->count())->toBe(1);
    expect(Tour::findOrFail($id)->status)->toBe('draft');
    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->title)->toBe('Retried title');
});

it('refuses to clear required English on published tours', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];
    $created = postJson('/api/partner/tours', spec019SourceCreatePayload(), $headers)->assertCreated();
    $id = $created->json('data.id');
    Tour::findOrFail($id)->update(['status' => 'published']);

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['description' => null]],
    ], $headers)->assertUnprocessable();

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['title' => '   ']],
    ], $headers)->assertUnprocessable();

    $en = Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail();
    expect($en->title)->toBe('Morning walking tour');
    expect($en->description)->not->toBeNull();
});
