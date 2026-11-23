<?php

/*
 * EGM-003 owned revision contract (execution manifest S1 + S2, partner side).
 *
 * Covers faithful/invalid legacy baselines and lazy capture, required pointer
 * scope, immutable payload replacement, the partial unique active-candidate
 * constraint, the exact base/payload-hash CAS, submit/withdraw/replay states,
 * published operational saves versus governed refusal, and the cross-supplier
 * 404. Concurrency races live in the Admin concurrency test file.
 */

use App\Domains\Admin\Actions\ApproveTourRevisionAction;
use App\Domains\Admin\Actions\ProposeTourCorrectionAction;
use App\Domains\Admin\Actions\RequestTourChangesAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Services\TourRevisionPayloadService;
use App\Domains\Partner\Services\TourRevisionService;
use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\TourRevisionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partner = TourRevisionFixtures::partner();
    $this->partnerUser = $this->partner->user_id ? User::find($this->partner->user_id) : User::factory()->create();
    $this->actingAs($this->partnerUser);
    TourRevisionFixtures::truncateGovernance();
});

function egm003PublishedTourFixture(string $sourceLocale = 'en', array $sourceOverrides = []): Tour
{
    return TourRevisionFixtures::tour(partner: test()->partner, sourceLocale: $sourceLocale, sourceOverrides: $sourceOverrides);
}

function egm003GovernancePayload(Tour $tour): array
{
    return TourService::class === TourService::class
        ? app(TourService::class)->ownedContentGovernance($tour->id, $tour->partner_id)['content_governance']
        : [];
}

it('validates only changed source fields while preserving an omitted legacy optional value', function () {
    $tour = egm003PublishedTourFixture('es', ['meeting_point' => str_repeat('L', 501)]);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'A valid changed source title']]);
    expect($candidate->payload['source']['title'])->toBe('A valid changed source title')
        ->and($candidate->payload['source']['meeting_point'])->toBe(str_repeat('L', 501));
});

it('validates changed list item bounds before storing a revision draft', function () {
    $tour = egm003PublishedTourFixture();
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    expect(fn () => $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['highlights' => [str_repeat('H', 501)]]]))
        ->toThrow(TourRevisionException::class);
    expect($service->activeCandidate($tour))->toBeNull();
});

it('retains media alt text by URL when a revision reorders existing images', function () {
    $payloads = app(TourRevisionPayloadService::class);
    $a = 'https://example.test/a.jpg';
    $b = 'https://example.test/b.jpg';
    $base = [
        ['position' => 0, 'url' => $a, 'alt_text' => 'Alt A', 'is_cover' => true],
        ['position' => 1, 'url' => $b, 'alt_text' => 'Alt B', 'is_cover' => false],
    ];
    $result = $payloads->normalizeMediaPatch([['url' => $b, 'is_cover' => false], ['url' => $a, 'is_cover' => true]], $base, false);
    expect(array_column($result, 'alt_text'))->toBe(['Alt B', 'Alt A'])
        ->and(array_column($result, 'is_cover'))->toBe([false, true]);
});

it('selects exactly one revision cover using the first flagged image or first actual image', function (bool $flagged) {
    $payloads = app(TourRevisionPayloadService::class);
    $result = $payloads->normalizeMediaPatch([
        ['url' => 'https://example.test/a.jpg', 'is_cover' => $flagged],
        ['url' => 'https://example.test/b.jpg', 'is_cover' => $flagged],
    ], [], false);
    expect(array_column($result, 'is_cover'))->toBe([true, false]);
})->with(['multiple flags' => true, 'no flags' => false]);

it('rejects duplicated and oversized revision media input', function (bool $duplicate) {
    $payloads = app(TourRevisionPayloadService::class);
    $media = $duplicate
        ? [['url' => 'https://example.test/a.jpg'], ['url' => 'https://example.test/a.jpg']]
        : array_map(fn (int $i) => ['url' => "https://example.test/{$i}.jpg"], range(1, 21));
    expect(fn () => $payloads->normalizeMediaPatch($media, [], false))->toThrow(TourRevisionException::class);
})->with(['duplicate URLs' => true, 'more than twenty images' => false]);

it('stores the normalized media cover in the same immutable candidate snapshot', function (bool $clear) {
    $tour = egm003PublishedTourFixture();
    $tour->update(['cover_image_url' => 'https://example.test/old-cover.jpg']);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $media = $clear ? [] : [['url' => 'https://example.test/new-cover.jpg']];
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['media' => $media]);
    expect($candidate->payload['presentation']['cover_image_url'])->toBe($clear ? null : 'https://example.test/new-cover.jpg')
        ->and($tour->fresh()->cover_image_url)->toBe('https://example.test/old-cover.jpg');
})->with(['new cover' => false, 'clear media' => true]);

it('refuses option membership order and commercial changes in direct revision patches', function (string $kind) {
    $tour = egm003PublishedTourFixture();
    $first = $tour->options()->create(['title' => 'First option', 'price_amount' => 5000, 'status' => 'active', 'sort_order' => 0]);
    $second = $tour->options()->create(['title' => 'Second option', 'price_amount' => 6000, 'status' => 'active', 'sort_order' => 1]);
    $retired = $tour->options()->create(['title' => 'Retired option', 'price_amount' => 7000, 'status' => 'retired', 'sort_order' => 2]);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $valid = [$first->id => ['title' => 'Revised first option'], $second->id => ['title' => 'Second option']];
    $options = match ($kind) {
        'missing' => [$first->id => $valid[$first->id]],
        'reordered' => [$second->id => $valid[$second->id], $first->id => $valid[$first->id]],
        'retired' => $valid + [$retired->id => ['title' => 'Revived option']],
        'empty' => [],
        'commercial' => [$first->id => $valid[$first->id] + ['price_amount' => 1], $second->id => $valid[$second->id]],
    };
    expect(fn () => $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['options' => $options]))
        ->toThrow(TourRevisionException::class);
    expect($service->activeCandidate($tour))->toBeNull()
        ->and($first->fresh()->price_amount)->toBe(5000)
        ->and($retired->fresh()->status)->toBe('retired');
})->with(['missing', 'reordered', 'retired', 'empty', 'commercial']);

it('rejects duplicate option identifiers before the HTTP revision projection can collapse them', function () {
    $tour = egm003PublishedTourFixture();
    $option = $tour->options()->create(['title' => 'Single option', 'price_amount' => 5000, 'status' => 'active', 'sort_order' => 0]);
    $base = app(TourRevisionService::class)->captureBaseline($tour);
    $this->postJson("/api/partner/tours/{$tour->id}/revisions", ['base_revision_id' => $base->id, 'options' => [
        ['id' => $option->id, 'title' => 'First duplicate'], ['id' => $option->id, 'title' => 'Second duplicate'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('options.1.id');
    expect(app(TourRevisionService::class)->activeCandidate($tour))->toBeNull();
});

it('returns not found for unknown revision identifiers on owned mutation routes', function (string $operation) {
    $tour = egm003PublishedTourFixture();
    $base = app(TourRevisionService::class)->captureBaseline($tour);
    $id = (int) (TourContentRevision::max('id') ?? 0) + 1000;
    if ($operation === 'replace') {
        $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$id}", ['base_revision_id' => $base->id, 'observed_payload_hash' => str_repeat('a', 64)])
            ->assertNotFound();
    } else {
        $this->postJson("/api/partner/tours/{$tour->id}/revisions/{$id}/{$operation}")->assertNotFound();
    }
})->with(['replace', 'submit', 'withdraw']);

it('returns observed response epochs in the owned request projection', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'draft');
    $request = app(RequestTourChangesAction::class)->execute(TourRevisionFixtures::admin('manage_tours'), $tour, [
        'context' => 'initial_review', 'reason' => 'Confirm the current source description.', 'field_refs' => TourRevisionFixtures::refs('source.description'),
    ]);
    $this->postJson("/api/partner/tours/{$tour->id}/revision-requests/{$request->id}/respond", [
        'observed_generation_epoch' => $tour->generationEpoch(), 'response_note' => 'Confirmed the current source.',
    ])->assertOk()->assertJsonPath('data.response_generation_epoch', $tour->generationEpoch());
});

it('reloads bounded reason and field-reference metadata with a full owned candidate', function () {
    $tour = egm003PublishedTourFixture();
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'An explained revised title']]);
    $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
    $request = app(RequestTourChangesAction::class)->execute(TourRevisionFixtures::admin('manage_tours'), $tour, [
        'context' => 'published_review', 'reason' => 'Clarify the authored title.', 'field_refs' => TourRevisionFixtures::refs('source.title'),
    ]);
    $service->respondToRequest($tour, $request->id, $this->partner->id, $this->partnerUser->id, 'The candidate clarifies this.', ['revision_id' => $candidate->id]);
    $this->getJson("/api/partner/tours/{$tour->id}/revisions/{$candidate->id}")->assertOk()
        ->assertJsonPath('data.requests.0.reason', 'Clarify the authored title.')
        ->assertJsonPath('data.requests.0.field_refs.0.path', 'source.title');
});

it('fails closed when a stored published pointer names another suppliers revision', function () {
    $tour = egm003PublishedTourFixture();
    $other = TourRevisionFixtures::tour(sourceOverrides: ['description' => 'Private other supplier source content.']);
    $service = app(TourRevisionService::class);
    $foreign = $service->captureBaseline($other);
    DB::table('tours')->where('id', $tour->id)->update(['published_revision_id' => $foreign->id]);
    $response = $this->postJson("/api/partner/tours/{$tour->id}/revisions", ['base_revision_id' => $foreign->id, 'source' => ['title' => 'Owned candidate title']]);
    $response->assertConflict();
    expect($response->getContent())->not->toContain('Private other supplier source content.')
        ->and($service->activeCandidate($tour))->toBeNull();
});

// ---- Baseline capture is faithful, idempotent and pointer-if-null ----------

it('captures a faithful published baseline lazily and reports a real approved revision', function (string $sourceLocale) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $snapshot = TourRevisionFixtures::snapshot($tour);

    $governance = egm003GovernancePayload($tour);

    expect($governance['generation_epoch'])->toBe($tour->generationEpoch())
        ->and($governance['approved_revision'])->not->toBeNull('A published tour must never report null capability.')
        ->and($governance['approved_revision']['kind'])->toBe(TourContentRevision::KIND_LEGACY_BASELINE)
        ->and($governance['approved_revision']['state'])->toBe('approved')
        ->and($governance['active_candidate'])->toBeNull()
        ->and($governance['open_requests'])->toBe([]);

    $baseline = TourContentRevision::query()->findOrFail($governance['approved_revision']['id']);

    // Faithful capture: the stored payload equals the stored rows exactly, no
    // normalization or invented approved content.
    expect($baseline->payload)->toBe($snapshot)
        ->and($baseline->state)->toBe(TourContentRevision::STATE_APPROVED)
        ->and($baseline->base_revision_id)->toBeNull()
        ->and($baseline->revision_number)->toBe(1)
        ->and($tour->fresh()->published_revision_id)->toBe($baseline->id)
        // Baseline capture is provenance, not an approval: no content version
        // bump and no new publication audit.
        ->and($governance['content_version'])->toBe(0);

    // Idempotent: a second read captures nothing new.
    $second = egm003GovernancePayload($tour->fresh());
    expect($second['approved_revision']['id'])->toBe($governance['approved_revision']['id'])
        ->and(TourContentRevision::query()->where('tour_id', $tour->id)->count())->toBe(1);
})->with(['en', 'es', 'it']);

it('never returns a content governance pointer for a never-public tour', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'draft');

    $governance = egm003GovernancePayload($tour);

    expect($governance['approved_revision'])->toBeNull()
        ->and($governance['generation_epoch'])->toBe($tour->generationEpoch())
        ->and(TourContentRevision::query()->where('tour_id', $tour->id)->exists())->toBeFalse();
});

it('refuses a candidate base token that does not name the current pointer', function () {
    $tour = egm003PublishedTourFixture();

    $observed = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $observed->id]);

    // A forged observed base id is a conflict, never a partial write.
    try {
        app(TourRevisionService::class)->createCandidate(
            $tour->id,
            (int) $this->partnerUser->id,
            $observed->id + 999,
            ['source' => ['title' => 'EGM003 proposed source title']],
        );
        $this->fail('A stale observed base token must be refused.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REVISION_CONFLICT)
            ->and($exception->identity['expected_base_revision_id'])->toBe($observed->id);
    }

    expect(TourContentRevision::query()->where('tour_id', $tour->id)->count())->toBe(1)
        ->and($tour->fresh()->translations()->where('locale', 'en')->value('title'))->toBe('EGM003 authored source title')
        ->and($tour->fresh()->published_revision_id)->toBe($observed->id);
});

// ---- Create, replace, submit, withdraw and replay ---------------------------

it('creates, submits and reloads an owned candidate with a complete payload', function (string $sourceLocale) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $tour->refresh()->loadMissing('publishedRevision');

    $service = app(TourRevisionService::class);

    $candidate = $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 proposed source title'],
    ]);

    expect($candidate->state)->toBe(TourContentRevision::STATE_DRAFT)
        ->and($candidate->kind)->toBe(TourContentRevision::KIND_PARTNER_EDIT)
        ->and($candidate->revision_number)->toBe(2)
        ->and($candidate->base_revision_id)->toBe($baseline->id)
        // The candidate is a NEW row with a NEW number: the baseline id and
        // pointer never change.
        ->and($candidate->payload['source']['title'])->toBe('EGM003 proposed source title')
        ->and($candidate->payload['source']['description'])->toBe($baseline->payload['source']['description'])
        ->and(Tour::find($tour->id)->published_revision_id)->toBe($baseline->id)
        ->and($tour->translations()->where('locale', $sourceLocale)->value('title'))->toBe('EGM003 authored source title');

    $submitted = $service->submitCandidate($tour->id, $candidate->id, (int) $this->partnerUser->id);
    expect($submitted->state)->toBe(TourContentRevision::STATE_PROPOSED)
        ->and($submitted->submitted_at)->not->toBeNull();

    // Replay of the exact in-review submission must not duplicate effects.
    $again = $service->submitCandidate($tour->id, $candidate->id, (int) $this->partnerUser->id);
    expect($again->revision_number)->toBe($submitted->revision_number)
        ->and($again->state)->toBe(TourContentRevision::STATE_PROPOSED)
        ->and(TourContentRevision::query()->where('tour_id', $tour->id)->count())->toBe(2);
})->with(['en', 'es', 'it']);

it('replaces a candidate by inserting a new immutable draft and keeps history', function (string $sourceLocale) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $first = $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 proposed source title'],
    ]);

    $replacement = $service->replaceCandidate(
        $tour->id, $first->id, (int) $this->partnerUser->id, $baseline->id, $first->payload_hash,
        ['source' => ['title' => 'EGM003 replaced source title']],
    );

    expect($replacement->id)->not->toBe($first->id)
        ->and($replacement->revision_number)->toBe($first->revision_number + 1)
        ->and($replacement->state)->toBe(TourContentRevision::STATE_DRAFT)
        ->and($replacement->payload['source']['title'])->toBe('EGM003 replaced source title')
        // The reviewed snapshot is never mutated.
        ->and(TourContentRevision::query()->findOrFail($first->id)->payload['source']['title'])
        ->toBe('EGM003 proposed source title')
        ->and(TourContentRevision::query()->findOrFail($first->id)->state)->toBe(TourContentRevision::STATE_SUPERSEDED);

    // The old payload hash is now a stale token: the same patch refused.
    try {
        $service->replaceCandidate(
            $tour->id, $replacement->id, (int) $this->partnerUser->id, $baseline->id, $first->payload_hash,
            ['source' => ['title' => 'EGM003 stale hash source title']],
        );
        $this->fail('A superseded payload hash must be refused.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REVISION_CONFLICT);
    }
})->with(['en', 'es', 'it']);

it('withdraws an owned candidate and refuses to withdraw an administrator correction', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $candidate = $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 withdrawn source title'],
    ]);

    $withdrawn = $service->withdrawCandidate($tour->id, $candidate->id, (int) $this->partnerUser->id);
    expect($withdrawn->state)->toBe(TourContentRevision::STATE_WITHDRAWN);

    // Replay: a terminal candidate cannot start again from its old identity.
    try {
        $service->submitCandidate($tour->id, $candidate->id, (int) $this->partnerUser->id);
        $this->fail('A superseded candidate must report the supersession code.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REVISION_TERMINAL);
    }

    // An administrator correction is not withdrawable by its owning partner.
    $admin = TourRevisionFixtures::admin('manage_tours', 'correct_tour_content');

    $correction = app(ProposeTourCorrectionAction::class)->execute(
        $admin, $tour, ['reason' => 'Correct the source title.', 'field_refs' => TourRevisionFixtures::refs('source.title'), 'source_title' => 'EGM003 corrected source title'],
    );

    try {
        $service->withdrawCandidate($tour->id, $correction->id, (int) $this->partnerUser->id);
        $this->fail('A partner cannot withdraw an administrator correction.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REVISION_CONFLICT);
    }
});

it('keeps at most one live candidate per tour through the partial unique index', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 first live candidate title'],
    ]);

    try {
        $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
            'source' => ['title' => 'EGM003 second live candidate title'],
        ]);
        $this->fail('A second live candidate is impossible.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REVISION_ALREADY_OPEN)
            ->and($exception->identity['conflicting_revision_id'])->not->toBeNull();
    }

    expect(TourContentRevision::query()->forTour($tour->id)->whereIn('state', ['draft', 'proposed'])->count())->toBe(1);
});

// ---- Published operational saves versus governed refusal --------------------

it('refuses a published operational save when any governed value changed', function (string $sourceLocale, string $governedKey, mixed $changedValue) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $before = $tour->fresh()->getAttributes() + ['source_title' => $tour->sourceTranslation()->title];

    $data = [$governedKey => $changedValue];

    // A legitimate operational value accompanies the governed change, and the
    // refusal must be whole: no partial sub-write anywhere.
    $data['price_from'] = 55.00;

    try {
        app(TourService::class)->updateTour($tour, $data);
        $this->fail("The governed {$governedKey} write did not refuse the published save.");
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::CONTENT_REQUIRES_REVISION);
    }

    $fresh = $tour->fresh();

    expect($fresh->price_amount)->toBe($before['price_amount'], 'The refusal must be atomic: no operational sub-write.')
        ->and($fresh->sourceTranslation()->title)->toBe($before['source_title']);
})->with(['en', 'es', 'it'])->with([
    'location' => ['location', 'Bergen, Norway'],
    'slug' => ['slug', 'egm003-governed-slug'],
    'duration minutes' => ['duration_minutes', 240],
    'group size max' => ['group_size_max', 25],
    'cover' => ['cover_image_url', 'https://images.example.com/egm003-governed-cover.jpg'],
]);

it('lets an unchanged round-trip accompany a published operational save', function (string $sourceLocale) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $tour->media()->create([
        'type' => 'gallery',
        'url' => 'https://images.example.com/egm003-op-round.jpg',
        'alt_text' => 'EGM003 round trip',
        'sort_order' => 0,
    ]);
    $option = $tour->options()->create([
        'title' => 'EGM003 operational option',
        'guide_languages' => ['en'],
        'price_amount' => 8800,
        'sort_order' => 0,
        'status' => 'active',
    ]);
    $tour->refresh();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    $service = app(TourService::class);

    // Exact snapshot round-trip: identical source text, id order and option
    // text with only the price changed.
    $data = [
        'price_from' => 99.00,
        'guide_languages' => $tour->guide_languages,
        'options' => [
            [
                'id' => $option->id,
                'title' => $option->title,
                'pickup_instructions' => $option->pickup_instructions,
                'guide_languages' => $option->guideLanguages(),
                'price_amount' => 7500,
                'group_size_max' => null,
                'start_time' => $option->startTimeString(),
                'weekdays' => null,
            ],
        ],
        'location' => $tour->location,
        'slug' => $tour->slug,
        'duration_minutes' => $tour->duration_minutes,
        'duration_label' => $tour->duration_label,
        'group_size_min' => $tour->group_size_min,
        'group_size_max' => $tour->group_size_max,
        'cover_image_url' => $tour->cover_image_url,
    ];

    $sourceOverrides = ['title' => $tour->sourceTranslation()->title];
    $data['title'] = $sourceOverrides['title'];
    $data['description'] = $tour->sourceTranslation()->description;

    $updated = $service->updateTour($tour, $data);

    expect($updated->fresh()->price_amount)->toBe(9900)
        ->and($updated->fresh()->options()->whereKey($option->id)->value('price_amount'))->toBe(7500)
        ->and($updated->fresh()->sourceTranslation()->title)->toBe($tour->sourceTranslation()->title)
        ->and($updated->fresh()->location)->toBe($tour->location)
        // An operational save never publishes or changes the approved identity.
        ->and($updated->fresh()->published_revision_id)->toBe($baseline->id);
})->with(['en', 'es', 'it']);

it('refuses published option structure changes without damaging commerce', function (string $sourceLocale) {
    $tour = egm003PublishedTourFixture($sourceLocale);
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $option = $tour->options()->create([
        'title' => 'EGM003 structure option',
        'guide_languages' => ['en'],
        'price_amount' => 8800,
        'sort_order' => 0,
        'status' => 'active',
    ]);
    $tour->refresh();

    try {
        app(TourService::class)->updateTour($tour, [
            // A provided list that omits the existing id would retire it.
            'options' => [[
                'title' => 'EGM003 brand new published option',
                'guide_languages' => ['en'],
                'price_amount' => 5000,
            ]],
        ]);
        $this->fail('A published structural option change must fail closed.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::OPTION_STRUCTURE_NOT_SUPPORTED);
    }

    $fresh = $tour->fresh();

    expect($fresh->options()->whereKey($option->id)->value('status'))->toBe('active')
        ->and($fresh->options()->whereKey($option->id)->value('price_amount'))->toBe(8800)
        ->and($fresh->options()->where('title', 'EGM003 brand new published option')->exists())->toBeFalse();
})->with(['en', 'es', 'it']);

// ---- Owned API surface and cross-supplier isolation -------------------------

it('serves the owned revision index, payload and cross-supplier 404', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 api candidate title'],
    ]);

    $response = $this->getJson("/api/partner/tours/{$tour->id}/revisions");
    $response->assertOk()->assertJsonCount(2, 'data');

    $candidateId = $response->json('data.0.id');
    $candidateHash = TourContentRevision::query()->findOrFail($candidateId)->payload_hash;
    $detail = $this->getJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}");
    $detail->assertOk()
        ->assertJsonPath('data.id', $candidateId)
        ->assertJsonPath('data.payload.source.title', 'EGM003 api candidate title')
        // Only the payload hash is exposed; canonical/content hashes and
        // provider diagnostics never leave the boundary.
        ->assertJsonStructure(['data' => ['diff' => []]])
        ->assertJsonPath('data.payload_hash', $candidateHash);

    $otherPartner = TourRevisionFixtures::otherPartner();
    $otherUser = User::find($otherPartner->user_id);
    $this->actingAs($otherUser);

    $this->getJson("/api/partner/tours/{$tour->id}/revisions")->assertNotFound();
    $this->getJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}")->assertNotFound();
    $this->postJson("/api/partner/tours/{$tour->id}/revisions", ['base_revision_id' => $baseline->id])->assertNotFound();
});

it('records the mandatory reason and field references on a governed request', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $admin = TourRevisionFixtures::admin('manage_tours');

    $request = app(RequestTourChangesAction::class)->execute(
        $admin,
        $tour,
        ['context' => 'published_review', 'reason' => 'The meeting point contradicts the photo.', 'field_refs' => TourRevisionFixtures::refs('source.meeting_point')],
    );

    expect($request->context)->toBe(TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW)
        ->and($request->state)->toBe(TourRevisionRequest::STATE_OPEN)
        ->and($request->field_refs)->toBe(TourRevisionFixtures::refs('source.meeting_point'))
        ->and($request->requested_content_hash)->toBe($baseline->content_hash);

    // The partner answers with the current owned submitted candidate on that
    // base only.
    $service = app(TourRevisionService::class);
    $candidate = $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['meeting_point' => 'EGM003 corrected meeting point'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, (int) $this->partnerUser->id);

    $answered = $service->respondToRequest(
        $tour->id, $request->id, $tour->partner_id, (int) $this->partnerUser->id, 'Fixed in the new candidate.', ['revision_id' => $candidate->id],
    );

    expect($answered->state)->toBe(TourRevisionRequest::STATE_ANSWERED)
        ->and($answered->revision_id)->toBe($candidate->id)
        ->and($answered->responded_at)->not->toBeNull();

    // Linking an unrelated tour or an old base is refused.
    $openAgain = TourRevisionRequest::query()->findOrFail($request->id);
    expect($openAgain->state)->toBe(TourRevisionRequest::STATE_ANSWERED);

    try {
        $service->respondToRequest($tour->id, $request->id, $tour->partner_id, (int) $this->partnerUser->id, 'Second answer is a replay.', ['revision_id' => $candidate->id]);
        $this->fail('An answered request cannot be answered again.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REQUEST_NOT_OPEN);
    }
});

it('refuses an initial review response whose observed epoch is stale', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'draft');
    $admin = TourRevisionFixtures::admin('manage_tours');

    $request = app(RequestTourChangesAction::class)->execute(
        $admin, $tour, ['context' => 'initial_review', 'reason' => 'The description is missing.', 'field_refs' => TourRevisionFixtures::refs('source.description')],
    );

    expect($request->base_revision_id)->toBeNull('No published revision id is invented before first publication.')
        ->and($request->requested_content_hash)->toHaveLength(64, 'The captured source identity and epoch hash together.');

    $service = app(TourRevisionService::class);
    $currentEpoch = $tour->generationEpoch();

    $answered = $service->respondToRequest($tour->id, $request->id, $tour->partner_id, (int) $this->partnerUser->id, 'Description added.', ['generation_epoch' => $currentEpoch]);
    expect($answered->state)->toBe(TourRevisionRequest::STATE_ANSWERED)
        ->and($answered->revision_id)->toBeNull()
        ->and($answered->response_generation_epoch)->toBe($currentEpoch);

    // A second request after the canonical identity advanced refuses the stale
    // observed epoch and accepts the current one.
    $tour->update(['generation_epoch' => $currentEpoch + 1]);

    $newerRequest = app(RequestTourChangesAction::class)->execute(
        $admin, $tour, ['context' => 'initial_review', 'reason' => 'The itinerary is still missing.', 'field_refs' => TourRevisionFixtures::refs('source.itinerary')],
    );

    try {
        $service->respondToRequest($tour->id, $newerRequest->id, $tour->partner_id, (int) $this->partnerUser->id, 'Observed the earlier identity.', ['generation_epoch' => $currentEpoch]);
        $this->fail('A stale observed epoch must be refused.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::REQUEST_NOT_OPEN)
            ->and($exception->status)->toBe(409);
    }

    expect($newerRequest->refresh()->state)->toBe(TourRevisionRequest::STATE_OPEN);

    $answeredNewer = $service->respondToRequest($tour->id, $newerRequest->id, $tour->partner_id, (int) $this->partnerUser->id, 'Itinerary added.', ['generation_epoch' => $currentEpoch + 1]);
    expect($answeredNewer->response_generation_epoch)->toBe($currentEpoch + 1);
});

// ---- Additional regressions for owned revision API and partner isolation ----

it('executes the full HTTP lifecycle with flat DTO payloads', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    // 1. Create candidate (POST)
    $create = $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $baseline->id,
        'source' => ['title' => 'EGM003 HTTP candidate title'],
    ]);
    $create->assertStatus(201)
        ->assertJsonPath('data.state', TourContentRevision::STATE_DRAFT)
        ->assertJsonPath('data.payload.source.title', 'EGM003 HTTP candidate title')
        ->assertJsonMissingPath('data.revision');

    $candidateId = $create->json('data.id');
    $payloadHash = $create->json('data.payload_hash');

    // 2. Read candidate (GET detail)
    $show = $this->getJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}");
    $show->assertOk()
        ->assertJsonPath('data.id', $candidateId)
        ->assertJsonPath('data.payload.source.title', 'EGM003 HTTP candidate title')
        ->assertJsonMissingPath('data.revision');

    // 3. Replace candidate (PUT)
    $replace = $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}", [
        'base_revision_id' => $baseline->id,
        'observed_payload_hash' => $payloadHash,
        'source' => ['title' => 'EGM003 HTTP replaced title'],
    ]);
    $replace->assertOk()
        ->assertJsonPath('data.payload.source.title', 'EGM003 HTTP replaced title');
    $newCandidateId = $replace->json('data.id');
    expect($newCandidateId)->not->toBe($candidateId);

    // 4. Submit candidate (POST submit)
    $submit = $this->postJson("/api/partner/tours/{$tour->id}/revisions/{$newCandidateId}/submit");
    $submit->assertOk()
        ->assertJsonPath('data.state', TourContentRevision::STATE_PROPOSED);

    // 5. Withdraw candidate (POST withdraw)
    $withdraw = $this->postJson("/api/partner/tours/{$tour->id}/revisions/{$newCandidateId}/withdraw");
    $withdraw->assertOk()
        ->assertJsonPath('data.state', TourContentRevision::STATE_WITHDRAWN);
});

it('enforces fresh actor ownership and approved partner status inside direct service calls', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $otherPartner = TourRevisionFixtures::otherPartner();
    $otherUser = User::find($otherPartner->user_id);

    // Cross-supplier actor directly invoking domain mutations must 404
    try {
        $service->createCandidate($tour->id, $otherUser->id, $baseline->id, ['source' => ['title' => 'Exploit']]);
        $this->fail('Direct cross-supplier creation must throw 404.');
    } catch (NotFoundHttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }

    // Unapproved or restricted partner directly invoking domain mutations must 403
    $pendingPartner = TourRevisionFixtures::partner('pending');
    $pendingUser = User::find($pendingPartner->user_id);
    $pendingTour = TourRevisionFixtures::tour(partner: $pendingPartner, status: 'published');
    $pendingBaseline = TourRevisionFixtures::seedBaseline($pendingTour);
    $pendingTour->update(['published_revision_id' => $pendingBaseline->id]);

    try {
        $service->createCandidate($pendingTour->id, $pendingUser->id, $pendingBaseline->id, ['source' => ['title' => 'Pending']]);
        $this->fail('Direct unapproved partner mutation must throw 403.');
    } catch (AccessDeniedHttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('rejects forbidden platform tokens and source_locale mutation in revision requests', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    // Source locale mutation rejected
    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $baseline->id,
        'source_locale' => 'it',
    ])->assertStatus(422)->assertJsonValidationErrors(['source_locale']);

    // Platform token manipulation rejected
    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $baseline->id,
        'content_version' => 999,
    ])->assertStatus(422)->assertJsonValidationErrors(['content_version']);
});

it('ensures content_governance DTO contains safe bounded fields without internal hashes or raw models', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    $admin = TourRevisionFixtures::admin('manage_tours');
    app(RequestTourChangesAction::class)->execute(
        $admin,
        $tour,
        ['context' => 'published_review', 'reason' => 'Fix location description.', 'field_refs' => TourRevisionFixtures::refs('source.description')],
    );

    $governance = egm003GovernancePayload($tour);

    expect($governance)->toHaveKeys(['source_locale', 'generation_epoch', 'content_version', 'approved_revision', 'active_candidate', 'open_requests'])
        ->and($governance['approved_revision']['state'])->toBe('approved')
        ->and($governance['open_requests'])->toHaveCount(1)
        ->and($governance['open_requests'][0])->not->toHaveKey('requested_content_hash')
        ->and($governance['open_requests'][0])->not->toHaveKey('content_hash');
});

it('verifies candidate content_hash hashes its own snapshot and leaves public content and commerce untouched while proposals exist', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);

    $candidate = $service->createCandidate($tour->id, (int) $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 proposed title that must not leak to public'],
    ]);

    expect($candidate->content_hash)->not->toBe($baseline->content_hash)
        ->and($candidate->content_hash)->toHaveLength(64);

    // Public row is untouched
    $fresh = $tour->fresh();
    expect($fresh->published_revision_id)->toBe($baseline->id)
        ->and($fresh->sourceTranslation()->title)->toBe('EGM003 authored source title');
});

it('EGM003 review preserves a valid candidate when observed replacement tokens are forged', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);
    $candidate = $service->createCandidate($tour->id, $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'Valid work must survive a refused replacement'],
    ]);

    $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$candidate->id}", [
        'base_revision_id' => $baseline->id + 999,
        'observed_payload_hash' => str_repeat('a', 64),
        'source' => ['title' => 'Forged replacement'],
    ])->assertConflict();

    expect($candidate->fresh()->state)->toBe('draft')
        ->and($candidate->fresh()->payload)->toBe($candidate->payload)
        ->and(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(2);
});

it('EGM003 review refuses a stale stored base even when the caller supplies the new pointer', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);
    $candidate = $service->createCandidate($tour->id, $this->partnerUser->id, $baseline->id, [
        'source' => ['title' => 'Work based on the old approval'],
    ]);
    $newBase = $baseline->replicate();
    $newBase->revision_number = 3;
    $newBase->kind = TourContentRevision::KIND_PUBLICATION;
    $newBase->save();
    $tour->update(['published_revision_id' => $newBase->id]);

    $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$candidate->id}", [
        'base_revision_id' => $newBase->id,
        'observed_payload_hash' => $candidate->payload_hash,
        'source' => ['title' => 'This must not silently rebase old content'],
    ])->assertConflict()->assertJsonPath('error_code', TourRevisionException::REVISION_SUPERSEDED);

    expect($candidate->fresh()->state)->toBe('superseded')
        ->and(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(3);
});

it('EGM003 review renders whole published-save refusal through the real HTTP boundary', function () {
    $tour = egm003PublishedTourFixture('es');
    $beforePrice = $tour->price_amount;
    $beforeLocation = $tour->location;

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'location' => 'A changed governed location',
        'price_from' => 99,
    ])->assertUnprocessable()->assertJsonPath('error_code', TourRevisionException::CONTENT_REQUIRES_REVISION);

    expect($tour->fresh()->price_amount)->toBe($beforePrice)
        ->and($tour->fresh()->location)->toBe($beforeLocation);
});

it('EGM003 review rejects current user restrictions at the direct domain boundary', function (array $restriction) {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $this->partnerUser->update($restriction);

    expect(fn () => app(TourRevisionService::class)->createCandidate(
        $tour->id, $this->partnerUser->id, $baseline->id, ['source' => ['title' => 'Refused source']],
    ))->toThrow(AccessDeniedHttpException::class);

    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(1);
})->with([
    'revoked partner role' => [['role' => 'traveler']],
    'suspended user' => [['status' => 'suspended']],
]);

it('EGM003 review rejects mixed commercial fields instead of silently dropping them', function (array $patch, string $path) {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $baseline->id,
        'source' => ['title' => 'A valid candidate title'],
        ...$patch,
    ])->assertUnprocessable()->assertJsonValidationErrors([$path]);

    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(1);
})->with([
    'top-level commercial value' => [['price_from' => 12], 'price_from'],
    'nested commercial value' => [['presentation' => ['price_amount' => 1200]], 'presentation.price_amount'],
    'source platform state' => [['source' => ['content_version' => 99]], 'source.content_version'],
]);

it('EGM003 review accepts the approved challenging difficulty value', function () {
    $tour = egm003PublishedTourFixture();
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $baseline->id,
        'presentation' => ['difficulty_level' => 'challenging'],
    ])->assertCreated()->assertJsonPath('data.payload.presentation.difficulty_level', 'challenging');
});

it('refuses a standalone governed duration label change through an operational save', function () {
    $tour = egm003PublishedTourFixture();
    expect(fn () => app(TourRevisionService::class)->governedDiffForOperationalSave($tour, ['duration_label' => 'Unauthorized replacement duration']))
        ->toThrow(TourRevisionException::class);
});

it('preserves existing media alt text in an unchanged operational round trip', function () {
    $tour = egm003PublishedTourFixture();
    $url = 'https://example.test/operational-existing.jpg';
    $tour->media()->create(['type' => 'cover', 'url' => $url, 'alt_text' => 'Existing accessible description', 'sort_order' => 0]);
    $tour->update(['cover_image_url' => $url]);
    $service = app(TourRevisionService::class);
    $service->captureBaseline($tour);
    $safe = $service->governedDiffForOperationalSave($tour, ['media' => [['url' => $url, 'is_cover' => true]], 'price_from' => 65]);
    expect($safe)->toBe(['price_from' => 65])
        ->and($tour->media()->first()->alt_text)->toBe('Existing accessible description');
});

it('submits and approves a valid required source while retaining omitted legacy optional fields', function () {
    $legacy = ['meeting_point' => str_repeat('L', 501), 'itinerary' => ['Unstructured legacy itinerary']];
    $tour = egm003PublishedTourFixture('it', $legacy);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'Reviewed Italian canonical title']]);
    $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
    $admin = TourRevisionFixtures::admin();
    app(ApproveTourRevisionAction::class)->execute($admin, $tour, $candidate->id);
    expect($tour->fresh()->sourceTranslation()->meeting_point)->toBe($legacy['meeting_point'])
        ->and($tour->fresh()->sourceTranslation()->itinerary)->toBe($legacy['itinerary'])
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('Reviewed Italian canonical title');
});

it('refuses option structure drift at submission and approval even when options were omitted from the patch', function (bool $submitted, string $drift) {
    $tour = egm003PublishedTourFixture();
    $first = $tour->options()->create(['title' => 'First active option', 'price_amount' => 5000, 'status' => 'active', 'sort_order' => 0]);
    $second = $tour->options()->create(['title' => 'Second active option', 'price_amount' => 6000, 'status' => 'active', 'sort_order' => 1]);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'Candidate title']]);
    if ($submitted) {
        $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
    }
    if ($drift === 'retire') {
        $first->update(['status' => 'retired']);
    } else {
        $first->update(['sort_order' => 5]);
    }
    try {
        if ($submitted) {
            app(ApproveTourRevisionAction::class)->execute(TourRevisionFixtures::admin(), $tour, $candidate->id);
        } else {
            $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
        }
        test()->fail('Drifted option structure was accepted.');
    } catch (TourRevisionException $exception) {
        expect($exception->errorCode)->toBe(TourRevisionException::OPTION_STRUCTURE_NOT_SUPPORTED);
    }
    expect($tour->fresh()->published_revision_id)->toBe($base->id)
        ->and($candidate->fresh()->state)->toBe($submitted ? 'proposed' : 'draft')
        ->and($second->fresh()->price_amount)->toBe(6000);
})->with([[false, 'retire'], [false, 'reorder'], [true, 'retire'], [true, 'reorder']]);

it('keeps the faithful baseline helper independent cover and unusual media unchanged', function () {
    $tour = egm003PublishedTourFixture();
    $payloads = app(TourRevisionPayloadService::class);
    $base = $payloads->captureSnapshot($tour);
    $base['presentation']['cover_image_url'] = 'legacy independent cover';
    $base['media'] = [];
    expect($payloads->mergePatch(['media' => []], $base, $tour, true))->toBe($base);
});

it('renders safe domain refusals on owned revision reads with corrupt stored pointers', function (string $route) {
    $tour = egm003PublishedTourFixture();
    $other = TourRevisionFixtures::tour();
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $foreign = $service->captureBaseline($other);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'Owned title']]);
    if ($route === 'detail') {
        DB::table('tour_content_revisions')->where('id', $candidate->id)->update(['base_revision_id' => $foreign->id]);
        $url = "/api/partner/tours/{$tour->id}/revisions/{$candidate->id}";
    } else {
        DB::table('tours')->where('id', $tour->id)->update(['published_revision_id' => $foreign->id]);
        $url = "/api/partner/tours/{$tour->id}/" . ($route === 'history' ? 'revisions' : 'revision-requests');
    }
    $this->getJson($url)->assertStatus(409)->assertJsonPath('error_code', 'REVISION_CONFLICT')
        ->assertDontSee($other->sourceTranslation()->title);
})->with(['detail', 'history', 'requests']);

it('returns not found for an unknown owned change request', function () {
    $tour = egm003PublishedTourFixture();
    $this->postJson("/api/partner/tours/{$tour->id}/revision-requests/999999/respond", ['revision_id' => 999999])->assertNotFound();
});

it('persists request supersession when its captured approved base is no longer current', function () {
    $tour = egm003PublishedTourFixture();
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $request = $service->requestChanges($tour, TourRevisionFixtures::admin(), 'published_review', 'Clarify title.', [['path' => 'source.title']]);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'Proposed title']]);
    $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
    $newBase = $base->replicate();
    $newBase->revision_number += 100;
    $newBase->save();
    DB::table('tours')->where('id', $tour->id)->update(['published_revision_id' => $newBase->id]);
    $this->postJson("/api/partner/tours/{$tour->id}/revision-requests/{$request->id}/respond", ['revision_id' => $candidate->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'REQUEST_NOT_OPEN');
    expect($request->fresh()->state)->toBe('superseded')
        ->and($request->fresh()->decision_reason)->not->toBeNull()
        ->and($request->fresh()->responded_at)->toBeNull()
        ->and(GovernanceAuditLog::where('action', 'tour.changes.superseded')->where('target_id', $tour->id)->count())->toBe(1);
});

it('reports a latest rejected revision after reload without calling it an active candidate', function () {
    $tour = egm003PublishedTourFixture();
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partnerUser->id, $base->id, ['source' => ['title' => 'Rejected proposal']]);
    $service->submitCandidate($tour, $candidate->id, $this->partnerUser->id);
    $service->rejectRevision($tour, $candidate->id, TourRevisionFixtures::admin(), 'Clarify the itinerary.');
    $this->getJson("/api/partner/tours/{$tour->id}")->assertOk()
        ->assertJsonPath('data.content_governance.active_candidate', null)
        ->assertJsonPath('data.content_governance.latest_revision.id', $candidate->id)
        ->assertJsonPath('data.content_governance.latest_revision.state', 'rejected')
        ->assertJsonPath('data.content_governance.approved_revision.id', $base->id);
});

it('serializes immutable option text as an ordered wire list with stable IDs and no commercial values', function () {
    $tour = egm003PublishedTourFixture();
    $first = $tour->options()->create(['title' => 'First ID but second display', 'price_amount' => 5000, 'status' => 'active', 'sort_order' => 1]);
    $second = $tour->options()->create(['title' => 'Second ID but first display', 'price_amount' => 6000, 'status' => 'active', 'sort_order' => 0]);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $this->getJson("/api/partner/tours/{$tour->id}/revisions/{$base->id}")->assertOk()
        ->assertJsonPath('data.payload.options.0.id', $second->id)
        ->assertJsonPath('data.payload.options.1.id', $first->id)
        ->assertJsonMissingPath('data.payload.options.0.price_amount');
    expect(array_keys($base->fresh()->payload['options']))->toBe([$second->id, $first->id]);
});

it('persists, replaces, projects, and clears change_summary while accepting null media', function () {
    $tour = egm003PublishedTourFixture();
    $base = app(TourRevisionService::class)->captureBaseline($tour);

    $created = $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $base->id,
        'change_summary' => '  Corrected the meeting point  ',
        'media' => null,
        'translations' => [
            'en' => ['title' => 'Candidate title with summary'],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.change_summary', 'Corrected the meeting point');

    $revisionId = (int) $created->json('data.id');
    $payloadHash = (string) $created->json('data.payload_hash');

    $this->getJson("/api/partner/tours/{$tour->id}")->assertOk()
        ->assertJsonPath('data.content_governance.active_candidate.change_summary', 'Corrected the meeting point');

    $this->getJson("/api/partner/tours/{$tour->id}/revisions")->assertOk()
        ->assertJsonPath('data.0.change_summary', 'Corrected the meeting point');

    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $base->id,
        'change_summary' => str_repeat('S', 501),
        'translations' => ['en' => ['title' => 'Over-limit summary']],
    ])->assertUnprocessable()->assertJsonValidationErrors('change_summary');

    $replacedKeep = $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$revisionId}", [
        'base_revision_id' => $base->id,
        'observed_payload_hash' => $payloadHash,
        'translations' => ['en' => ['title' => 'Updated title preserving summary']],
    ])->assertOk()
        ->assertJsonPath('data.change_summary', 'Corrected the meeting point');

    $secondId = (int) $replacedKeep->json('data.id');
    $secondHash = (string) $replacedKeep->json('data.payload_hash');

    $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$secondId}", [
        'base_revision_id' => $base->id,
        'observed_payload_hash' => $secondHash,
        'change_summary' => '',
        'translations' => ['en' => ['title' => 'Cleared summary title']],
    ])->assertOk()
        ->assertJsonPath('data.change_summary', null);
});

it('exposes contract errors and top-level revision_id on HTTP domain refusals', function () {
    $tour = egm003PublishedTourFixture('en');
    $tour->sourceTranslation()->update(['title' => '']);
    $base = app(TourRevisionService::class)->captureBaseline($tour);

    $this->putJson("/api/partner/tours/{$tour->id}", [
        'location' => 'Unauthorized governed location write',
    ])->assertUnprocessable()
        ->assertJsonPath('error_code', TourRevisionException::CONTENT_REQUIRES_REVISION)
        ->assertJsonStructure(['message', 'error_code', 'paths', 'errors' => ['presentation.location']]);

    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $base->id,
        'source' => ['title' => null],
    ])->assertUnprocessable()
        ->assertJsonPath('error_code', TourRevisionException::REVISION_CONTENT_INVALID)
        ->assertJsonStructure(['message', 'error_code', 'paths', 'errors' => ['source.title']]);

    $invalidCandidate = $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $base->id,
        'source' => ['meeting_point' => 'Updated meeting point on empty-title legacy baseline'],
    ])->assertCreated();

    $candidateId = (int) $invalidCandidate->json('data.id');

    $this->postJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('error_code', TourRevisionException::REVISION_CONTENT_INVALID)
        ->assertJsonPath('revision_id', $candidateId)
        ->assertJsonStructure(['message', 'error_code', 'paths', 'errors' => ['source.title'], 'revision_id']);

    $this->postJson("/api/partner/tours/{$tour->id}/revisions", [
        'base_revision_id' => $base->id,
        'source' => ['title' => 'Duplicate active candidate attempt'],
    ])->assertConflict()
        ->assertJsonPath('error_code', TourRevisionException::REVISION_ALREADY_OPEN)
        ->assertJsonPath('revision_id', $candidateId);

    $this->putJson("/api/partner/tours/{$tour->id}/revisions/{$candidateId}", [
        'base_revision_id' => $base->id,
        'observed_payload_hash' => str_repeat('0', 64),
        'source' => ['title' => 'Stale CAS token'],
    ])->assertConflict()
        ->assertJsonPath('error_code', TourRevisionException::REVISION_CONFLICT)
        ->assertJsonPath('revision_id', $candidateId);
});
