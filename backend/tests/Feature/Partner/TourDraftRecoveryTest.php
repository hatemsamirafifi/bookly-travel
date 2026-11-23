<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\TourDraft;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'draft-recovery-cat'], ['name' => 'Draft Category']);

    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    // Second partner for isolation checks
    $this->otherUser = User::factory()->partner()->create();
    $this->otherPartner = Partner::create([
        'user_id' => $this->otherUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $this->otherToken = $this->otherUser->createToken('test2', ['partner'])->plainTextToken;
});

it('creates and recovers a general draft before tour materialization with unselected language', function () {
    $creationId = (string) Str::uuid();

    $response = postJson('/api/partner/tours/drafts', [
        'creation_id' => $creationId,
        'source_locale' => null, // Explicitly unselected
        'current_step' => 'language',
        'payload' => [
            'highlights' => ['Great wine', 'Local guide'],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.creation_id', $creationId)
        ->assertJsonPath('data.source_locale', null)
        ->assertJsonPath('data.current_step', 'language');

    $concurrencyToken = $response->json('data.concurrency_token');
    expect($concurrencyToken)->toBeString()->not->toBeEmpty();

    $draftId = $response->json('data.id');

    // Resume the draft
    $resumeResponse = postJson("/api/partner/tours/drafts/{$draftId}/resume", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $resumeResponse->assertStatus(200)
        ->assertJsonPath('data.creation_id', $creationId)
        ->assertJsonPath('data.source_locale', null)
        ->assertJsonPath('data.current_step', 'language')
        ->assertJsonPath('data.payload.highlights.0', 'Great wine');
});

it('detects multi-tab stale write conflict using optimistic concurrency token', function () {
    $creationId = (string) Str::uuid();

    // Tab 1 creates draft
    $createResponse = postJson('/api/partner/tours/drafts', [
        'creation_id' => $creationId,
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Initial Title'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $draftId = $createResponse->json('data.id');
    $tokenTab1 = $createResponse->json('data.concurrency_token');

    // Tab 2 loads the draft (tokenTab2 == tokenTab1)
    $tokenTab2 = $tokenTab1;

    // Tab 1 updates the draft with new title
    $tab1Update = putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'es',
        'current_step' => 'details',
        'concurrency_token' => $tokenTab1,
        'payload' => ['title' => 'Updated by Tab 1'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $tab1Update->assertStatus(200);
    $newTokenFromTab1 = $tab1Update->json('data.concurrency_token');
    expect($newTokenFromTab1)->not->toBe($tokenTab1);

    // Tab 2 now attempts to update using stale token (tokenTab2)
    $tab2Update = putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'es',
        'current_step' => 'details',
        'concurrency_token' => $tokenTab2,
        'payload' => ['title' => 'Stale Overwrite by Tab 2'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    // Must be rejected with 409 Conflict
    $tab2Update->assertStatus(409)
        ->assertJsonPath('error', 'STALE_DRAFT_CONFLICT')
        ->assertJsonPath('server_draft.payload.title', 'Updated by Tab 1');

    // Confirm database still contains Tab 1's title
    $draftInDb = TourDraft::findOrFail($draftId);
    expect($draftInDb->payload['title'])->toBe('Updated by Tab 1');
});

it('enforces partner isolation on drafts', function () {
    $draft = TourDraft::create([
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'payload' => ['title' => 'Secret Partner 1 Draft'],
    ]);

    // Other partner attempts to view draft
    $response = getJson("/api/partner/tours/drafts/{$draft->id}", [
        'Authorization' => 'Bearer ' . $this->otherToken,
    ]);
    $response->assertStatus(404);

    // Other partner attempts to resume draft
    $resume = postJson("/api/partner/tours/drafts/{$draft->id}/resume", [], [
        'Authorization' => 'Bearer ' . $this->otherToken,
    ]);
    $resume->assertStatus(404);
});

it('enforces first-save idempotency on tour creation (D2)', function () {
    $creationId = (string) Str::uuid();

    $tourPayload = [
        'creation_id' => $creationId,
        'category' => 'draft-recovery-cat',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Florence Wine Tasting Experience',
        'description' => str_repeat('A wonderful tour in Florence visiting hidden cellars. ', 5),
        'options' => [
            [
                'title' => 'Morning Slot',
                'pickup_instructions' => 'Meet at Piazza del Duomo',
                'guide_languages' => ['en'],
                'price_amount' => 5000,
                'capacity' => 12,
                'start_time' => '10:00:00',
            ],
        ],
    ];

    // First POST: creates the tour
    $firstResponse = postJson('/api/partner/tours', $tourPayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $firstResponse->assertStatus(201);
    $tourId = $firstResponse->json('data.id');
    expect($tourId)->toBeGreaterThan(0);

    $initialTourCount = Tour::where('partner_id', $this->partner->id)->count();
    $initialOptionCount = $this->partner->tours()->find($tourId)->options()->count();
    expect($initialOptionCount)->toBe(1);

    // Second POST: retry with identical payload and same creation_id
    $retryResponse = postJson('/api/partner/tours', $tourPayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    // Must return the existing tour without duplicate rows
    $retryResponse->assertStatus(200);
    expect($retryResponse->json('data.id'))->toBe($tourId);

    expect(Tour::where('partner_id', $this->partner->id)->count())->toBe($initialTourCount)
        ->and($this->partner->tours()->find($tourId)->options()->count())->toBe(1);

    // Third POST: replay same creation_id with materially different destination/category
    $divergentPayload = $tourPayload;
    $divergentPayload['destination'] = 'Venice, Italy';

    $conflictResponse = postJson('/api/partner/tours', $divergentPayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $conflictResponse->assertStatus(409);
});

it('rejects cross-partner tour_id injection on general drafts with 404', function () {
    $otherTour = Tour::create([
        'partner_id' => $this->otherPartner->id,
        'category_id' => $this->category->id,
        'slug' => 'other-partner-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Milan, Italy',
        'duration_minutes' => 90,
        'duration_label' => '90 minutes',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'draft',
    ]);

    $response = postJson('/api/partner/tours/drafts', [
        'creation_id' => (string) Str::uuid(),
        'tour_id' => $otherTour->id,
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Cross-owner draft injection'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(404);
});

it('rejects saving a general draft against a published tour under EGM-003/D6', function () {
    $publishedTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'published-owned-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 4500,
        'status' => 'published',
    ]);

    $response = postJson('/api/partner/tours/drafts', [
        'creation_id' => (string) Str::uuid(),
        'tour_id' => $publishedTour->id,
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Attempted direct draft on published tour'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tour_id']);
});

it('rejects updating an existing draft when concurrency token is omitted', function () {
    $creationId = (string) Str::uuid();

    $createResponse = postJson('/api/partner/tours/drafts', [
        'creation_id' => $creationId,
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Initial Title'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $createResponse->assertStatus(200);
    $draftId = $createResponse->json('data.id');

    $updateResponse = putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Silent Overwrite Without Token'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $updateResponse->assertStatus(409)
        ->assertJsonPath('error', 'STALE_DRAFT_CONFLICT')
        ->assertJsonPath('server_draft.payload.title', 'Initial Title');

    $draftInDb = TourDraft::findOrFail($draftId);
    expect($draftInDb->payload['title'])->toBe('Initial Title');
});

it('rejects updating an existing draft whose tour became published even when tour_id is omitted', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'draft-turned-published-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 4500,
        'status' => 'draft',
    ]);

    $createResponse = postJson('/api/partner/tours/drafts', [
        'creation_id' => (string) Str::uuid(),
        'tour_id' => $tour->id,
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Before publication'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $createResponse->assertStatus(200);
    $draftId = $createResponse->json('data.id');
    $token = $createResponse->json('data.concurrency_token');

    $tour->update(['status' => 'published']);

    $updateResponse = putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'en',
        'current_step' => 'details',
        'concurrency_token' => $token,
        'payload' => ['title' => 'Direct draft overwrite after publish'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $updateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['tour_id']);
});

it('rejects malformed non-UUID Client-Creation-Id header with 422 and detects title drift on creation_id reuse', function () {
    $badResponse = postJson('/api/partner/tours', [
        'category' => 'draft-recovery-cat',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Malformed Header Tour',
        'description' => str_repeat('Valid description text for creation. ', 5),
    ], [
        'Authorization' => 'Bearer ' . $this->token,
        'Client-Creation-Id' => 'not-a-uuid',
    ]);

    $badResponse->assertStatus(422)
        ->assertJsonValidationErrors(['creation_id']);

    $creationId = (string) Str::uuid();
    $basePayload = [
        'creation_id' => $creationId,
        'category' => 'draft-recovery-cat',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Florence Original Title',
        'description' => str_repeat('Valid description text for creation. ', 5),
    ];

    postJson('/api/partner/tours', $basePayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);

    $titleDrift = $basePayload;
    $titleDrift['title'] = 'Florence Different Tour Same City';

    postJson('/api/partner/tours', $titleDrift, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(409);
});

it('persists immutable creation_payload_hash independently of mutable draft JSON payload', function () {
    $creationId = (string) Str::uuid();
    $payload = [
        'creation_id' => $creationId,
        'category' => 'draft-recovery-cat',
        'destination' => 'Naples, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Naples Underground Walk',
        'description' => str_repeat('Dedicated creation_payload_hash column test description. ', 4),
    ];

    $created = postJson('/api/partner/tours', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);
    $created->assertStatus(201);
    $tourId = $created->json('data.id');

    $draft = TourDraft::where('partner_id', $this->partner->id)
        ->where('creation_id', $creationId)
        ->firstOrFail();

    expect($draft->creation_payload_hash)->toBeString()->toHaveLength(64);

    // Even if `_fingerprint` in JSON payload were stripped, creation_payload_hash enforces replay & 409.
    $rawPayload = $draft->payload;
    unset($rawPayload['_fingerprint']);
    $draft->update(['payload' => $rawPayload]);

    $replay = postJson('/api/partner/tours', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
    ]);
    $replay->assertStatus(200)->assertJsonPath('data.id', $tourId);

    $modified = $payload;
    $modified['title'] = 'Different Title Same Creation ID';
    postJson('/api/partner/tours', $modified, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(409)
        ->assertJsonPath('error', 'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD');
});

it('fails closed with 409 if creation_id already points to a materialized tour without a fingerprint (B1)', function () {
    $tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'existing-tour-b1-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 4500,
        'status' => 'draft',
    ]);

    $creationId = (string) Str::uuid();
    postJson('/api/partner/tours/drafts', [
        'creation_id' => $creationId,
        'tour_id' => $tour->id,
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Linked general draft without fingerprint'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $beforeCount = Tour::where('partner_id', $this->partner->id)->count();

    postJson('/api/partner/tours', [
        'creation_id' => $creationId,
        'category' => 'draft-recovery-cat',
        'destination' => 'Milan, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Unrelated Tour Reuse Attempt',
        'description' => str_repeat('Should fail closed with 409 rather than replaying existing tour. ', 3),
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(409)
        ->assertJsonPath('error', 'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD');

    expect(Tour::where('partner_id', $this->partner->id)->count())->toBe($beforeCount);
});

it('keeps creation_id immutable once assigned on an existing draft', function () {
    $originalCreationId = (string) Str::uuid();
    $otherCreationId = (string) Str::uuid();

    $created = postJson('/api/partner/tours/drafts', [
        'creation_id' => $originalCreationId,
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Borrador original'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $draftId = $created->json('data.id');
    $token = $created->json('data.concurrency_token');

    putJson("/api/partner/tours/drafts/{$draftId}", [
        'creation_id' => $otherCreationId,
        'source_locale' => 'es',
        'current_step' => 'details',
        'concurrency_token' => $token,
        'payload' => ['title' => 'Attempted creation_id mutation'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['creation_id']);

    expect(TourDraft::findOrFail($draftId)->creation_id)->toBe($originalCreationId);
});

it('supports valid Client-Creation-Id header idempotency replay/conflict and rejects mismatched body vs header UUIDs', function () {
    $headerUuid = (string) Str::uuid();
    $bodyUuid = (string) Str::uuid();

    $payload = [
        'category' => 'draft-recovery-cat',
        'destination' => 'Venice, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Venice Lagoon Cruise',
        'description' => str_repeat('Header-based first-save idempotency replay and conflict test. ', 3),
    ];

    postJson('/api/partner/tours', array_merge($payload, ['creation_id' => $bodyUuid]), [
        'Authorization' => 'Bearer ' . $this->token,
        'Client-Creation-Id' => $headerUuid,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['creation_id']);

    $created = postJson('/api/partner/tours', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
        'Client-Creation-Id' => $headerUuid,
    ])->assertStatus(201);

    $tourId = $created->json('data.id');

    $replay = postJson('/api/partner/tours', $payload, [
        'Authorization' => 'Bearer ' . $this->token,
        'Client-Creation-Id' => $headerUuid,
    ])->assertStatus(200);
    expect($replay->json('data.id'))->toBe($tourId);

    $divergent = $payload;
    $divergent['title'] = 'Different Venice Tour';
    postJson('/api/partner/tours', $divergent, [
        'Authorization' => 'Bearer ' . $this->token,
        'Client-Creation-Id' => $headerUuid,
    ])->assertStatus(409)
        ->assertJsonPath('error', 'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD');
});

it('excludes legacy null creation_id drafts, discarded drafts, and submitted tours from latest general draft', function () {
    $tourDraftOnly = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'legacy-null-creation-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 90,
        'duration_label' => '90 minutes',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 3000,
        'status' => 'draft',
    ]);

    TourDraft::create([
        'partner_id' => $this->partner->id,
        'tour_id' => $tourDraftOnly->id,
        'creation_id' => null,
        'status' => 'draft',
        'payload' => ['title' => 'Legacy null creation draft'],
        'auto_saved_at' => now(),
    ]);

    getJson('/api/partner/tours/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(404);

    $uuid1 = (string) Str::uuid();
    $saved1 = postJson('/api/partner/tours/drafts', [
        'creation_id' => $uuid1,
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Active General Draft'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);
    $draftId1 = $saved1->json('data.id');

    getJson('/api/partner/tours/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200)
        ->assertJsonPath('data.id', $draftId1);

    $uuid2 = (string) Str::uuid();
    $created = postJson('/api/partner/tours', [
        'creation_id' => $uuid2,
        'category' => 'draft-recovery-cat',
        'destination' => 'Florence, Italy',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Materialized Submitted Draft',
        'description' => str_repeat('Submitted tour should transition its linked draft out of active recovery. ', 3),
        'cover_image_url' => 'https://cdn.example.com/submitted.jpg',
        'pricing_tiers' => [
            ['name' => 'Adult', 'price' => 40, 'min_participants' => 1, 'max_participants' => 10],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);
    $submittedTourId = $created->json('data.id');

    postJson("/api/partner/tours/{$submittedTourId}/submit", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $submittedDraft = TourDraft::where('tour_id', $submittedTourId)->firstOrFail();
    expect($submittedDraft->status)->toBe('submitted');

    $this->deleteJson("/api/partner/tours/drafts/{$draftId1}", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    expect(TourDraft::findOrFail($draftId1)->status)->toBe('discarded');
    getJson('/api/partner/tours/drafts/latest', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(404);
});

it('enforces partner isolation and preserves creation_payload_hash on draft discard', function () {
    $creationId = (string) Str::uuid();
    postJson('/api/partner/tours', [
        'creation_id' => $creationId,
        'category' => 'draft-recovery-cat',
        'destination' => 'Rome, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Discard Preserves Idempotency Tour',
        'description' => str_repeat('Discarding a materialized draft must keep D2 hash intact. ', 3),
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);

    $draft = TourDraft::where('partner_id', $this->partner->id)
        ->where('creation_id', $creationId)
        ->firstOrFail();

    auth()->forgetGuards();
    $this->deleteJson("/api/partner/tours/drafts/{$draft->id}", [], [
        'Authorization' => 'Bearer ' . $this->otherToken,
    ])->assertStatus(404);

    auth()->forgetGuards();
    $this->deleteJson("/api/partner/tours/drafts/{$draft->id}", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $reloaded = TourDraft::findOrFail($draft->id);
    expect($reloaded->status)->toBe('discarded')
        ->and($reloaded->creation_payload_hash)->toBeString()->toHaveLength(64);
});

it('enforces source_locale immutability on authored drafts while allowing unauthored step-1 switches', function () {
    $creationId = (string) Str::uuid();

    $step1 = postJson('/api/partner/tours/drafts', [
        'creation_id' => $creationId,
        'source_locale' => 'en',
        'current_step' => 'language',
        'payload' => ['title' => ''],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $draftId = $step1->json('data.id');
    $token = $step1->json('data.concurrency_token');

    $switched = putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'es',
        'current_step' => 'details',
        'payload' => ['title' => 'Paseo por Toledo'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
        'If-Match' => "\"{$token}\"",
    ])->assertStatus(200);

    expect($switched->json('data.source_locale'))->toBe('es');
    $token2 = $switched->json('data.concurrency_token');

    putJson("/api/partner/tours/drafts/{$draftId}", [
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Attempted Language Flip'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
        'If-Match' => $token2,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['source_locale']);

    expect(TourDraft::findOrFail($draftId)->source_locale)->toBe('es');
});

it('returns draft sync metadata on createTour and preserves wizard payload, step, and option server_ids on resume', function () {
    // Path A: Existing wizard draft on step 'pricing' materialized via POST /tours preserves step & wizard payload and enriches server_id.
    $uuidExisting = (string) Str::uuid();
    $wizardDraft = postJson('/api/partner/tours/drafts', [
        'creation_id' => $uuidExisting,
        'source_locale' => 'es',
        'current_step' => 'pricing',
        'payload' => [
            'source_locale' => 'es',
            'title' => 'Tour con Paso Preservado',
            'languages' => ['es', 'en'],
            'options' => [
                [
                    'id' => 'local-opt-1',
                    'title' => 'Grupo Reducido',
                    'guide_languages' => ['es'],
                    'price' => '55',
                    'capacity' => '8',
                    'start_time' => '09:30',
                    'weekdays' => [1, 3, 5],
                    'pickup_instructions' => '',
                ],
            ],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    $preDraftId = $wizardDraft->json('data.id');
    $preToken = $wizardDraft->json('data.concurrency_token');

    $createdA = postJson('/api/partner/tours', [
        'creation_id' => $uuidExisting,
        'category' => 'draft-recovery-cat',
        'destination' => 'Granada, Spain',
        'duration_value' => 3,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'es',
        'title' => 'Tour con Paso Preservado',
        'description' => str_repeat('Preserves wizard payload and enriches server option ids cleanly. ', 3),
        'guide_languages' => ['es', 'en'],
        'options' => [
            [
                'title' => 'Grupo Reducido',
                'guide_languages' => ['es'],
                'price_amount' => 5500,
                'group_size_max' => 8,
                'start_time' => '09:30',
                'weekdays' => [1, 3, 5],
                'pickup_instructions' => null,
            ],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);

    $tourIdA = $createdA->json('data.id');
    $optionIdA = $createdA->json('data.options.0.id');
    $draftMetaA = $createdA->json('draft');
    expect($draftMetaA['id'])->toBe($preDraftId)
        ->and($draftMetaA['tour_id'])->toBe($tourIdA)
        ->and($draftMetaA['concurrency_token'])->toBeString()->not->toBe($preToken)
        ->and($draftMetaA['current_step'])->toBe('pricing');

    // Follow-up Save Progress using rotated token succeeds (200), no false 409.
    putJson("/api/partner/tours/drafts/{$preDraftId}", [
        'tour_id' => $tourIdA,
        'current_step' => 'availability',
        'payload' => ['title' => 'Updated After Save Draft'],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
        'If-Match' => $draftMetaA['concurrency_token'],
    ])->assertStatus(200);

    $resumedA = postJson("/api/partner/tours/drafts/{$preDraftId}/resume", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);
    // Step was updated cleanly after create; before update, check Path B too.
    expect($resumedA->json('data.tour_id'))->toBe($tourIdA);

    // Path B: Direct POST /tours without prior /drafts creates a wizard-compatible payload with server_id & languages.
    $uuidDirect = (string) Str::uuid();
    $createdB = postJson('/api/partner/tours', [
        'creation_id' => $uuidDirect,
        'category' => 'draft-recovery-cat',
        'destination' => 'Seville, Spain',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'es',
        'title' => 'Direct Materialized Draft Tour',
        'description' => str_repeat('Direct create draft builds lossless wizard payload for cross-session resume. ', 3),
        'guide_languages' => ['es', 'fr'],
        'highlights' => ['Alcazar entry'],
        'options' => [
            [
                'title' => 'Opción Directa',
                'guide_languages' => ['es'],
                'price_amount' => 4550,
                'group_size_max' => 10,
                'start_time' => '10:00',
                'weekdays' => [2, 4],
            ],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);

    $draftIdB = $createdB->json('draft.id');
    $optionIdB = $createdB->json('data.options.0.id');
    expect($draftIdB)->toBeInt()->and($createdB->json('draft.concurrency_token'))->toBeString();

    $resumedB = postJson("/api/partner/tours/drafts/{$draftIdB}/resume", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    expect($resumedB->json('data.payload.languages'))->toBe(['es', 'fr'])
        ->and($resumedB->json('data.payload.options.0.server_id'))->toBe($optionIdB)
        ->and($resumedB->json('data.payload.options.0.price'))->toBe('45.5')
        ->and($resumedB->json('data.payload.options.0.capacity'))->toBe('10')
        ->and($resumedB->json('data.payload.highlights'))->toBe(['Alcazar entry']);
});

it('enforces draft status and linked tour lifecycle across direct draftId endpoints', function () {
    // 1. Discarded unlinked draft cannot be shown, resumed, updated, or resurrected via creation_id.
    $uuidDiscarded = (string) Str::uuid();
    $saved = postJson('/api/partner/tours/drafts', [
        'creation_id' => $uuidDiscarded,
        'source_locale' => 'en',
        'current_step' => 'details',
        'payload' => ['title' => 'Soon Discarded'],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(200);
    $discardedId = $saved->json('data.id');
    $discardedToken = $saved->json('data.concurrency_token');

    $this->deleteJson("/api/partner/tours/drafts/{$discardedId}", [], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200);

    getJson("/api/partner/tours/drafts/{$discardedId}", ['Authorization' => 'Bearer ' . $this->token])->assertStatus(404);
    postJson("/api/partner/tours/drafts/{$discardedId}/resume", [], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(404);
    putJson("/api/partner/tours/drafts/{$discardedId}", [
        'payload' => ['title' => 'Mutate Discarded'],
    ], ['Authorization' => 'Bearer ' . $this->token, 'If-Match' => $discardedToken])->assertStatus(422);

    postJson('/api/partner/tours', [
        'creation_id' => $uuidDiscarded,
        'category' => 'draft-recovery-cat',
        'destination' => 'Rome, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Resurrect Discarded Creation ID',
        'description' => str_repeat('Must never resurrect a discarded creation_id. ', 3),
    ], ['Authorization' => 'Bearer ' . $this->token])
        ->assertStatus(409)
        ->assertJsonPath('error', 'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD');

    // 2. Submitted / pending_review / archived linked drafts cannot be shown, resumed, updated, or discarded.
    $uuidSub = (string) Str::uuid();
    $created = postJson('/api/partner/tours', [
        'creation_id' => $uuidSub,
        'category' => 'draft-recovery-cat',
        'destination' => 'Milan, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'Lifecycle Guard Tour',
        'description' => str_repeat('Submitted and archived tours cannot be mutated through general drafts. ', 3),
        'cover_image_url' => 'https://cdn.example.com/lifecycle.jpg',
        'pricing_tiers' => [['name' => 'Adult', 'price' => 30, 'min_participants' => 1, 'max_participants' => 10]],
    ], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(201);

    $subTourId = $created->json('data.id');
    $subDraftId = $created->json('draft.id');
    $subToken = $created->json('draft.concurrency_token');

    // Also create second owned tour and verify tour_id rebinding on draft is blocked with 422.
    $otherOwned = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'other-owned-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'price_amount' => 3000,
        'status' => 'draft',
    ]);
    putJson("/api/partner/tours/drafts/{$subDraftId}", [
        'tour_id' => $otherOwned->id,
        'payload' => ['title' => 'Rebind tour'],
    ], ['Authorization' => 'Bearer ' . $this->token, 'If-Match' => $subToken])->assertStatus(422)
        ->assertJsonValidationErrors(['tour_id']);

    postJson("/api/partner/tours/{$subTourId}/submit", [], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(200);

    getJson("/api/partner/tours/drafts/{$subDraftId}", ['Authorization' => 'Bearer ' . $this->token])->assertStatus(404);
    postJson("/api/partner/tours/drafts/{$subDraftId}/resume", [], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(404);
    putJson("/api/partner/tours/drafts/{$subDraftId}", [
        'payload' => ['title' => 'Mutate Submitted'],
    ], ['Authorization' => 'Bearer ' . $this->token, 'If-Match' => $subToken])->assertStatus(422);
    $this->deleteJson("/api/partner/tours/drafts/{$subDraftId}", [], ['Authorization' => 'Bearer ' . $this->token])->assertStatus(422);

    // 3. Pending review or archived tour with a legacy/active draft record stays blocked.
    $archivedTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'archived-guard-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Turin, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'price_amount' => 3000,
        'status' => 'archived',
    ]);
    $archivedDraft = TourDraft::create([
        'partner_id' => $this->partner->id,
        'tour_id' => $archivedTour->id,
        'creation_id' => (string) Str::uuid(),
        'status' => 'draft',
        'concurrency_token' => 'arch-tok-1',
        'payload' => ['title' => 'Archived Linked Draft'],
        'auto_saved_at' => now(),
    ]);
    getJson("/api/partner/tours/drafts/{$archivedDraft->id}", ['Authorization' => 'Bearer ' . $this->token])->assertStatus(404);
    putJson("/api/partner/tours/drafts/{$archivedDraft->id}", [
        'payload' => ['title' => 'Mutate Archived Tour Draft'],
    ], ['Authorization' => 'Bearer ' . $this->token, 'If-Match' => 'arch-tok-1'])->assertStatus(422);
});

it('normalizes creation_id UUID case and detects fingerprint divergence on highlights and media', function () {
    $lowerUuid = strtolower((string) Str::uuid());
    $upperUuid = strtoupper($lowerUuid);
    $basePayload = [
        'creation_id' => $upperUuid,
        'category' => 'draft-recovery-cat',
        'destination' => 'Bologna, Italy',
        'duration_value' => 2,
        'duration_unit' => 'hour',
        'difficulty_level' => 'easy',
        'source_locale' => 'en',
        'title' => 'UUID Case & Extended Hash Tour',
        'description' => str_repeat('Ensures uppercase and lowercase creation_id compare identically and highlights participate in D2 fingerprint. ', 2),
        'highlights' => ['Porticoes'],
        'cover_image_url' => 'https://cdn.example.com/bologna-1.jpg',
    ];

    $created = postJson('/api/partner/tours', $basePayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(201);
    $tourId = $created->json('data.id');

    $replayPayload = $basePayload;
    $replayPayload['creation_id'] = $lowerUuid;
    postJson('/api/partner/tours', $replayPayload, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(200)->assertJsonPath('data.id', $tourId);

    $divergentHighlights = $replayPayload;
    $divergentHighlights['highlights'] = ['Two Towers'];
    postJson('/api/partner/tours', $divergentHighlights, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(409)
        ->assertJsonPath('error', 'CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD');
});
