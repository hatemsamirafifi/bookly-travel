<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\TourDraft;
use App\Models\Category;
use App\Models\Tour;
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

// ---------------------------------------------------------------------------
// Spec 019 US1 (T011): opaque snapshot round trips, incomplete/legacy
// payloads, owner isolation and no source writes from autosave.
// ---------------------------------------------------------------------------

function spec019SortAssocKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (! array_is_list($value)) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        $value[$key] = spec019SortAssocKeys($item);
    }

    return $value;
}

it('preserves opaque payload values, types and list order through jsonb storage', function () {
    $payload = [
        'title' => 'Opaque draft title',
        'seats' => 30,
        'seats_label' => '30',
        'ratio' => 1.5,
        'flag' => true,
        'nothing' => null,
        'translations' => [
            'en' => ['title' => 'Opaque EN', 'itinerary' => [['day' => 2, 'title' => 'Kept order']]],
            'es' => ['title' => 'Opaque snapshot may hold anything'],
        ],
        'custom' => ['nested' => ['b', 'a', 1, 2, 3], 'flag' => true],
        'translation_statuses' => ['es' => 'ready'],
    ];

    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $payload,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $latest = getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk();

    // Postgres jsonb storage normalizes OBJECT key order, so both sides are
    // sorted recursively by associative key (list order is never touched)
    // and then compared strictly: toBe uses ===, proving exact values, PHP
    // types, key sets and ordered lists. Object serialization order itself
    // is not part of the contract.
    expect(spec019SortAssocKeys($latest->json('payload')))
        ->toBe(spec019SortAssocKeys($payload));
});

it('preserves padded and empty opaque strings exactly through save and latest', function () {
    $payload = [
        'title' => '  Keep spaces  ',
        'note' => '',
        'custom' => [
            'empty' => '',
            'label' => '  Padded label  ',
            'count' => '30',
            'flag' => false,
        ],
        'days' => ['  Day 1  ', ''],
    ];

    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $payload,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    $latest = getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk();

    // Same strict contract as the typed snapshot test: recursive
    // assoc-key sort, then === on values, types, key sets and list order.
    expect(spec019SortAssocKeys($latest->json('payload')))->toBe(spec019SortAssocKeys($payload));
});

it('keeps trimming canonical source input on create and update', function () {
    $headers = ['Authorization' => 'Bearer ' . $this->token];

    $created = postJson('/api/partner/tours', [
        'title' => '  Padded title  ',
        'description' => str_repeat('A padded canonical description. ', 5),
        'category' => 'wine-food',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
    ], $headers)->assertCreated();
    $id = $created->json('data.id');

    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->title)
        ->toBe('Padded title');

    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['meeting_point' => '  Padded point  ']],
    ], $headers)->assertOk();
    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->meeting_point)
        ->toBe('Padded point');

    // Empty strings still convert to null on canonical writes.
    putJson("/api/partner/tours/{$id}", [
        'translations' => ['en' => ['meeting_point' => '']],
    ], $headers)->assertOk();
    expect(Tour::findOrFail($id)->translations()->where('locale', 'en')->firstOrFail()->meeting_point)
        ->toBeNull();
});

it('round-trips incomplete and legacy string-array itinerary snapshots', function () {
    $incomplete = ['destination' => 'Siena, Italy'];
    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $incomplete,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()->assertJsonPath('payload', $incomplete);

    $legacy = ['title' => 'Legacy draft', 'itinerary' => ['Day 1: Arrival', 'Day 2: Tour']];
    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $legacy,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()->assertJsonPath('payload', $legacy);

    // The stored snapshot keeps the raw legacy strings; nothing normalizes it.
    expect(TourDraft::where('tour_id', $this->tour->id)->firstOrFail()->payload['itinerary'])
        ->toBe(['Day 1: Arrival', 'Day 2: Tour']);
});

it('isolates draft saves by owner and never writes source from autosave', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => ['title' => 'Foreign payload'],
    ], ['Authorization' => 'Bearer ' . $otherToken])->assertNotFound();

    // Reset the test-process cached guard user before switching principals.
    auth()->forgetGuards();

    $payload = ['title' => 'Autosaved title', 'description' => 'Autosaved description.'];
    $stateCount = DB::table('tour_translation_states')->where('tour_id', $this->tour->id)->count();

    Queue::fake();

    postJson('/api/partner/tours/' . $this->tour->id . '/drafts/save', [
        'payload' => $payload,
    ], ['Authorization' => 'Bearer ' . $this->token])->assertOk();

    // Autosave creates no canonical source rows, no derivative state rows
    // and dispatches no generation jobs; only the opaque snapshot persists.
    expect(Tour::findOrFail($this->tour->id)->translations()->count())->toBe(0);
    expect(DB::table('tour_translation_states')->where('tour_id', $this->tour->id)->count())->toBe($stateCount);
    Queue::assertNothingPushed();
    expect(TourDraft::where('tour_id', $this->tour->id)->count())->toBe(1);
    getJson('/api/partner/tours/' . $this->tour->id . '/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()->assertJsonPath('payload', $payload);
});
