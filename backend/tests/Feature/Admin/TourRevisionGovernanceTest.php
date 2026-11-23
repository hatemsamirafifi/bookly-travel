<?php

/*
 * EGM-003 administrative revision governance (design v3 §4/§5).
 *
 * Covers first-publication and re-publication revision sealing inside
 * ApproveTourAction:
 *  - Enforces immutable publication snapshot with new revision ID/number;
 *  - Establishes published_revision_id and advances content_version;
 *  - Records revision_id in the single tour.publish audit metadata;
 *  - Retains original published_at timestamp on re-publication;
 *  - Supersedes open candidates/linked requests under the locked tour;
 *  - Replays/invalid content/unauthorized actors leave no partial row;
 *  - Boundary fault during audit rolls back all publication mutations.
 */

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\ApproveTourRevisionAction;
use App\Domains\Admin\Actions\ProposeTourCorrectionAction;
use App\Domains\Admin\Actions\RejectTourRevisionAction;
use App\Domains\Admin\Actions\RequestTourChangesAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Partner\Services\TourRevisionPayloadService;
use App\Domains\Partner\Services\TourRevisionService;
use App\Domains\Partner\Services\TourService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\TourRevisionRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

function egm003SubmittedRevision(Partner $partner): array
{
    $tour = TourRevisionFixtures::tour(partner: $partner);
    $service = app(TourRevisionService::class);
    $baseline = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour->id, $partner->user_id, $baseline->id, [
        'source' => ['title' => 'Reviewed replacement title'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, $partner->user_id);

    return [$tour, $baseline, $candidate];
}

it('approves an exact published-tour revision through the authorized domain Action', function (string $locale) {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, sourceLocale: $locale);
    $service = app(TourRevisionService::class);
    $baseline = $service->captureBaseline($tour);
    $stamp = $tour->fresh()->published_at;
    $candidate = $service->createCandidate($tour->id, $this->partner->user_id, $baseline->id, [
        'source' => ['title' => 'Reviewed replacement title'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, $this->partner->user_id);
    $approved = app(ApproveTourRevisionAction::class)
        ->execute($this->admin, $tour, $candidate->id);

    expect($approved->state)->toBe('approved')
        ->and($tour->fresh()->published_revision_id)->toBe($candidate->id)
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('Reviewed replacement title')
        ->and($tour->fresh()->published_at->equalTo($stamp))->toBeTrue()
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.revision.approved')->count())->toBe(1);
})->with(['en', 'es', 'it']);

it('rejects unauthorized direct revision-service promotion without public effects', function () {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    expect(fn () => app(TourRevisionService::class)->approveRevision($tour, $candidate->id, $this->partner->user))
        ->toThrow(AuthorizationException::class);
    expect($candidate->fresh()->state)->toBe('proposed')
        ->and($tour->fresh()->published_revision_id)->toBe($baseline->id);
});

it('enforces authorization at every direct moderation-service boundary', function (string $operation) {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    $service = app(TourRevisionService::class);
    $actor = $operation === 'correction' ? $this->admin : $this->partner->user;
    expect(fn () => match ($operation) {
        'request' => $service->requestChanges($tour, $actor, 'published_review', 'Clarify this source field.', TourRevisionFixtures::refs('source.title')),
        'correction' => $service->proposeCorrection($tour, $actor, 'Exceptional source correction.', TourRevisionFixtures::refs('source.title'), ['source' => ['title' => 'Exceptional title']]),
        'reject' => $service->rejectRevision($tour, $candidate->id, $actor, 'A specific rejection reason.'),
    })->toThrow(AuthorizationException::class);
    expect($candidate->fresh()->state)->toBe('proposed')
        ->and($tour->fresh()->published_revision_id)->toBe($baseline->id)
        ->and($tour->revisionRequests()->count())->toBe(0);
})->with(['request', 'correction', 'reject']);

it('rejects malformed field-reference wire objects without silently discarding input', function (array $refs) {
    expect(fn () => TourContentRules::normalizeItineraryFieldReferences($refs))
        ->toThrow(ValidationException::class);
})->with([
    'unknown key' => [[['path' => 'source.title', 'unexpected' => 'discarded']]],
    'non-string note' => [[['path' => 'source.title', 'note' => 9]]],
    'invalid mixed entry' => [[['path' => 'source.title'], 9]],
    'too many refs' => [array_fill(0, 31, ['path' => 'source.title'])],
]);

it('rejects broad and non-resolvable review references', function (string $path) {
    $tour = TourRevisionFixtures::tour(partner: $this->partner);
    $tour->media()->create(['url' => 'https://example.test/governed.jpg', 'type' => 'gallery', 'sort_order' => 0]);
    $payloads = app(TourRevisionPayloadService::class);
    expect(fn () => $payloads->resolveFieldReferences(TourRevisionFixtures::refs($path), $payloads->captureSnapshot($tour)))
        ->toThrow(TourRevisionException::class);
})->with(['source', 'presentation', 'source.important_information.999', 'media.0.url', 'media.0.is_cover']);

it('rejects non-list field references at the direct domain resolver', function (array $refs) {
    $tour = TourRevisionFixtures::tour(partner: $this->partner);
    $payloads = app(TourRevisionPayloadService::class);
    expect(fn () => $payloads->resolveFieldReferences($refs, $payloads->captureSnapshot($tour)))
        ->toThrow(TourRevisionException::class);
})->with([
    'associative object' => [['arbitrary' => ['path' => 'source.title']]],
    'sparse list' => [[4 => ['path' => 'source.title']]],
]);

it('supports initial-review requests on never-public tours and records pending rejection', function (string $status) {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: $status);
    $request = app(RequestTourChangesAction::class)->execute($this->admin, $tour, [
        'context' => 'initial_review', 'reason' => 'Clarify the meeting point.',
        'field_refs' => TourRevisionFixtures::refs('source.meeting_point'),
    ]);
    expect($request->state)->toBe('open')
        ->and($request->base_revision_id)->toBeNull()
        ->and($tour->fresh()->published_revision_id)->toBeNull()
        ->and($tour->fresh()->status)->toBe($status === 'pending_review' ? 'rejected' : $status);
    if ($status === 'pending_review') {
        $audit = GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.reject')->firstOrFail();
        expect($audit->metadata['reason'])->toBe('Clarify the meeting point.');
    }
})->with(['draft', 'pending_review', 'rejected']);

it('refuses initial-review context for previously published tours now in draft', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner);
    $tour->update(['status' => 'draft']);
    expect(fn () => app(RequestTourChangesAction::class)->execute($this->admin, $tour, [
        'context' => 'initial_review', 'reason' => 'Wrong lifecycle context.',
        'field_refs' => TourRevisionFixtures::refs('source.title'),
    ]))->toThrow(TourRevisionException::class);
    expect($tour->revisionRequests()->count())->toBe(0);
});

it('closes initial-review requests with the first publication decision', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'draft');
    $request = app(RequestTourChangesAction::class)->execute($this->admin, $tour, [
        'context' => 'initial_review', 'reason' => 'Confirm canonical description.',
        'field_refs' => TourRevisionFixtures::refs('source.description'),
    ]);
    app(TourRevisionService::class)->respondToRequest($tour, $request->id, $this->partner->id, $this->partner->user_id,
        'Confirmed source description.', ['generation_epoch' => $tour->fresh()->generationEpoch()]);
    app(TourService::class)->submitForReview($tour->fresh());
    app(ApproveTourAction::class)->execute($this->admin, $tour);
    expect($request->fresh()->state)->toBe('approved')
        ->and($request->fresh()->decided_by)->toBe($this->admin->id)
        ->and($request->fresh()->decision_reason)->not->toBeNull()
        ->and($request->fresh()->response_note)->toBe('Confirmed source description.');
});

it('requires a submitted candidate for correction-context responses rather than an epoch-only answer', function () {
    [$tour, $base, $candidate] = egm003SubmittedRevision($this->partner);
    $request = app(RequestTourChangesAction::class)->execute($this->admin, $tour, [
        'context' => 'correction', 'reason' => 'Clarify this canonical field.',
        'field_refs' => TourRevisionFixtures::refs('source.title'),
    ]);
    $service = app(TourRevisionService::class);
    expect(fn () => $service->respondToRequest($tour, $request->id, $this->partner->id, $this->partner->user_id,
        'An epoch is insufficient.', ['generation_epoch' => $tour->fresh()->generationEpoch()]))
        ->toThrow(TourRevisionException::class);
    expect($request->fresh()->state)->toBe('open');
    $response = $service->respondToRequest($tour, $request->id, $this->partner->id, $this->partner->user_id,
        'Review the exact submitted content.', ['revision_id' => $candidate->id]);
    expect($response->state)->toBe('answered')->and($response->revision_id)->toBe($candidate->id);
});

it('commits obsolete approval reconciliation before the Action reports a conflict', function () {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    DB::table('tours')->where('id', $tour->id)->update(['status' => 'archived']);
    expect(fn () => app(ApproveTourRevisionAction::class)
        ->execute($this->admin, $tour, $candidate->id))
        ->toThrow(TourRevisionException::class);
    expect($candidate->fresh()->state)->toBe('superseded')
        ->and($tour->fresh()->published_revision_id)->toBe($baseline->id);
});

it('plans both derivatives for the approved canonical revision epoch', function () {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    app(ApproveTourRevisionAction::class)->execute($this->admin, $tour, $candidate->id);
    $fresh = $tour->fresh();
    $hash = app(TourRevisionPayloadService::class)->contentHash($fresh);
    $states = $fresh->translationStates()->orderBy('locale')->get();
    expect($states->pluck('locale')->all())->toBe(['es', 'it'])
        ->and($fresh->generationEpoch())->toBe(1);
    foreach ($states as $state) {
        expect($state->source_hash)->toBe($hash)
            ->and((int) $state->generation_epoch)->toBe($fresh->generationEpoch())
            ->and($state->status)->toBe('pending')
            ->and($state->translated_hash)->toBeNull();
    }
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
    foreach (['es', 'it'] as $locale) {
        Queue::assertPushed(GenerateTourTranslationJob::class,
            fn ($job) => $job->tourId === $tour->id && $job->locale === $locale
                && $job->sourceHash === $hash && $job->generationEpoch === $fresh->generationEpoch());
    }
});

it('preserves the canonical epoch for presentation-only revision approval', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, overrides: ['generation_epoch' => 9]);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour->id, $this->partner->user_id, $base->id, [
        'presentation' => ['location' => 'Reviewed location'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, $this->partner->user_id);
    $sourceWrites = 0;
    DB::listen(function ($query) use (&$sourceWrites) {
        if (str_starts_with(strtolower($query->sql), 'update "tour_translations"')) {
            $sourceWrites++;
        }
    });
    app(ApproveTourRevisionAction::class)->execute($this->admin, $tour, $candidate->id);
    expect($tour->fresh()->generationEpoch())->toBe(9)
        ->and($tour->fresh()->location)->toBe('Reviewed location')
        ->and($sourceWrites)->toBe(0);
});

it('clears a nullable canonical source field only after ordinary revision approval', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $candidate = $service->createCandidate($tour, $this->partner->user_id, $base->id, [
        'source' => ['meeting_point' => null],
    ]);
    $service->submitCandidate($tour, $candidate->id, $this->partner->user_id);
    expect($tour->fresh()->sourceTranslation()->meeting_point)->toBe('EGM003 authored meeting point');
    app(ApproveTourRevisionAction::class)->execute($this->admin, $tour, $candidate->id);
    expect($tour->fresh()->sourceTranslation()->meeting_point)->toBeNull();
});

it('rolls back revision promotion and derivative plans when its audit fails', function () {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    $before = $tour->fresh();
    DB::statement("ALTER TABLE governance_audit_logs ADD CONSTRAINT egm003_test_refuse_revision CHECK (action <> 'tour.revision.approved')");
    try {
        expect(fn () => app(ApproveTourRevisionAction::class)
            ->execute($this->admin, $tour, $candidate->id))->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE governance_audit_logs DROP CONSTRAINT egm003_test_refuse_revision');
    }
    expect($tour->fresh()->published_revision_id)->toBe($baseline->id)
        ->and($tour->fresh()->sourceTranslation()->title)->toBe($before->sourceTranslation()->title)
        ->and($tour->fresh()->generationEpoch())->toBe($before->generationEpoch())
        ->and($tour->fresh()->contentVersion())->toBe($before->contentVersion())
        ->and($candidate->fresh()->state)->toBe('proposed')
        ->and($tour->translationStates()->count())->toBe(0);
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
});

it('rejects revision approval replay without duplicate audit or translation work', function () {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    $action = app(ApproveTourRevisionAction::class);
    $action->execute($this->admin, $tour, $candidate->id);
    $version = $tour->fresh()->contentVersion();
    expect(fn () => $action->execute($this->admin, $tour, $candidate->id))
        ->toThrow(TourRevisionException::class);
    expect($tour->fresh()->contentVersion())->toBe($version)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.revision.approved')->count())->toBe(1);
    Queue::assertPushed(GenerateTourTranslationJob::class, 2);
});

it('refuses revoked reviewer permission and inactive owners without promotion effects', function (string $condition) {
    [$tour, $baseline, $candidate] = egm003SubmittedRevision($this->partner);
    if ($condition === 'permission') {
        $this->admin->load('adminPermission');
        DB::table('admin_permissions')->where('user_id', $this->admin->id)->update(['flags' => '{}']);
    } else {
        DB::table('partners')->where('id', $this->partner->id)->update(['is_active' => false]);
    }
    $refused = false;
    try {
        app(ApproveTourRevisionAction::class)->execute($this->admin, $tour, $candidate->id);
    } catch (AuthorizationException|TourRevisionException $exception) {
        $refused = true;
    }
    expect($refused)->toBeTrue()
        ->and($candidate->fresh()->state)->toBe('proposed')
        ->and($tour->fresh()->published_revision_id)->toBe($baseline->id)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.revision.approved')->count())->toBe(0);
})->with(['permission', 'owner']);

beforeEach(function () {
    // Provider execution is outside this promotion boundary; its dispatch
    // identity is asserted without making external Gemini requests.
    Queue::fake([GenerateTourTranslationJob::class]);
    $this->partner = TourRevisionFixtures::partner();
    $this->admin = TourRevisionFixtures::admin('manage_tours');
    $this->actingAs($this->admin);
    TourRevisionFixtures::truncateGovernance();
    TourRevisionFixtures::clearAuditLog();
});

it('seals a new approved publication revision on first publication across locales', function (string $locale) {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'pending_review', sourceLocale: $locale);

    expect($tour->status)->toBe('pending_review')
        ->and($tour->published_at)->toBeNull()
        ->and($tour->published_revision_id)->toBeNull()
        ->and($tour->contentVersion())->toBe(0);
    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(0);

    $published = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($published->status)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and($published->published_revision_id)->not->toBeNull()
        ->and($published->contentVersion())->toBe(1);

    $revision = TourContentRevision::query()->findOrFail($published->published_revision_id);
    expect($revision->tour_id)->toBe($tour->id)
        ->and($revision->revision_number)->toBe(1)
        ->and($revision->kind)->toBe(TourContentRevision::KIND_PUBLICATION)
        ->and($revision->state)->toBe(TourContentRevision::STATE_APPROVED)
        ->and($revision->source_locale)->toBe($locale)
        ->and($revision->base_revision_id)->toBeNull()
        ->and($revision->decided_by)->toBe($this->admin->id)
        ->and($revision->decided_at)->not->toBeNull()
        ->and($revision->payload)->toBe(TourRevisionFixtures::snapshot($published))
        ->and($revision->content_hash)->toHaveLength(64)
        ->and($revision->payload_hash)->toHaveLength(64);

    $audit = GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->sole();
    expect($audit->actor_id)->toBe($this->admin->id)
        ->and($audit->metadata['revision_id'])->toBe($published->published_revision_id)
        ->and($audit->before_state)->toBe(['status' => 'pending_review'])
        ->and($audit->after_state)->toBe(['status' => 'published']);
})->with(['en', 'es', 'it']);

it('creates a new approved revision and advances content_version on re-publication while preserving first published_at', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'pending_review');

    $first = app(ApproveTourAction::class)->execute($this->admin, $tour);
    $firstStamp = $first->published_at;
    $firstRevId = $first->published_revision_id;
    expect($first->contentVersion())->toBe(1);

    $tour->update(['status' => 'draft']);
    $tour->update(['status' => 'pending_review']);

    $this->travel(1)->hour();

    $second = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($second->status)->toBe('published')
        ->and($second->published_at->equalTo($firstStamp))->toBeTrue()
        ->and($second->published_revision_id)->not->toBe($firstRevId)
        ->and($second->contentVersion())->toBe(2);

    $secondRev = TourContentRevision::query()->findOrFail($second->published_revision_id);
    expect($secondRev->revision_number)->toBe(2)
        ->and($secondRev->kind)->toBe(TourContentRevision::KIND_PUBLICATION)
        ->and($secondRev->state)->toBe(TourContentRevision::STATE_APPROVED);

    $audits = GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->metadata['revision_id'])->toBe($firstRevId)
        ->and($audits[1]->metadata['revision_id'])->toBe($second->published_revision_id);
});

it('supersedes active candidates and open published-context requests on re-publication', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'published');
    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);

    $partnerUser = User::find($this->partner->user_id);
    $service = app(TourRevisionService::class);

    $candidate = $service->createCandidate($tour->id, (int) $partnerUser->id, $baseline->id, [
        'source' => ['title' => 'EGM003 proposed title for republish supersession'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, (int) $partnerUser->id);
    expect($candidate->fresh()->state)->toBe(TourContentRevision::STATE_PROPOSED);

    $req = app(RequestTourChangesAction::class)->execute($this->admin, $tour, [
        'context' => 'published_review',
        'reason' => 'Fix description for published tour',
        'field_refs' => TourRevisionFixtures::refs('source.description'),
    ]);
    expect($req->fresh()->state)->toBe(TourRevisionRequest::STATE_OPEN);

    $tour->update(['status' => 'draft']);
    $tour->update(['status' => 'pending_review']);

    $published = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($candidate->fresh()->state)->toBe(TourContentRevision::STATE_SUPERSEDED)
        ->and($req->fresh()->state)->toBe(TourRevisionRequest::STATE_SUPERSEDED)
        ->and($published->published_revision_id)->not->toBe($baseline->id)
        ->and($published->published_revision_id)->not->toBe($candidate->id);

    $newRev = TourContentRevision::query()->findOrFail($published->published_revision_id);
    expect($newRev->kind)->toBe(TourContentRevision::KIND_PUBLICATION)
        ->and($newRev->state)->toBe(TourContentRevision::STATE_APPROVED);
});

it('refuses publication when source content is invalid without partial rows or audits', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'pending_review');
    $tour->sourceTranslation()->update(['description' => '']);

    try {
        app(ApproveTourAction::class)->execute($this->admin, $tour);
        $this->fail('Expected publication to be refused for empty source description.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and($tour->fresh()->published_revision_id)->toBeNull()
        ->and($tour->fresh()->contentVersion())->toBe(0)
        ->and(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(0)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
});

it('refuses unauthorized publishers and replay attempts without extra revisions or audits', function () {
    $regularUser = User::factory()->create();
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'pending_review');

    try {
        app(ApproveTourAction::class)->execute($regularUser, $tour);
        $this->fail('Expected publication to fail for unauthorized user.');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toContain('authorized');
    }

    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(0)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);

    $published = app(ApproveTourAction::class)->execute($this->admin, $tour);
    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(1)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(1);

    try {
        app(ApproveTourAction::class)->execute($this->admin, $published);
        $this->fail('Replay must be refused.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    expect(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(1)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(1);
});

it('atomically promotes source, presentation, media, and option text while preserving option commerce', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, sourceLocale: 'es');
    $newCategory = Category::firstOrCreate(['slug' => 'egm003-four-tier'], ['name' => 'EGM003 Four Tier']);
    $tour->media()->create([
        'type' => 'cover',
        'url' => 'https://example.test/old-cover.jpg',
        'alt_text' => 'Old cover alt',
        'sort_order' => 0,
    ]);
    $tour->update(['cover_image_url' => 'https://example.test/old-cover.jpg']);

    $optA = $tour->options()->create([
        'title' => 'Old option A',
        'pickup_instructions' => 'Old pickup A',
        'guide_languages' => ['es'],
        'price_amount' => 5500,
        'group_size_max' => 8,
        'start_time' => '09:00:00',
        'weekdays' => [1, 3],
        'status' => 'active',
        'sort_order' => 0,
    ]);
    $optB = $tour->options()->create([
        'title' => 'Old option B',
        'pickup_instructions' => 'Old pickup B',
        'guide_languages' => ['es', 'en'],
        'price_amount' => 8900,
        'group_size_max' => 4,
        'start_time' => '14:30:00',
        'weekdays' => [5],
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $service = app(TourRevisionService::class);
    $baseline = $service->captureBaseline($tour->fresh());
    $candidate = $service->createCandidate($tour->id, (int) $this->partner->user_id, $baseline->id, [
        'source' => [
            'title' => 'Atomic 4-tier Spanish title',
            'highlights' => ['Panoramic terrace', 'Skip-the-line access'],
        ],
        'presentation' => [
            'category_id' => $newCategory->id,
            'location' => 'Seville, Spain',
            'difficulty_level' => 'moderate',
            'guide_languages' => ['es', 'en'],
        ],
        'media' => [
            ['url' => 'https://example.test/new-gallery.jpg', 'is_cover' => false, 'alt_text' => 'Gallery alt'],
            ['url' => 'https://example.test/new-cover.jpg', 'is_cover' => true, 'alt_text' => 'New cover alt'],
        ],
        'options' => [
            $optA->id => ['title' => 'Revised option A', 'pickup_instructions' => 'North gate A'],
            $optB->id => ['title' => 'Revised option B', 'pickup_instructions' => 'South gate B'],
        ],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, (int) $this->partner->user_id);

    $beforeVersion = $tour->fresh()->contentVersion();
    app(ApproveTourRevisionAction::class)->execute($this->admin, $tour->id, $candidate->id);

    $fresh = $tour->fresh(['translations', 'media', 'options']);
    expect($fresh->published_revision_id)->toBe($candidate->id)
        ->and($fresh->contentVersion())->toBe($beforeVersion + 1)
        ->and($fresh->sourceTranslation()?->title)->toBe('Atomic 4-tier Spanish title')
        ->and($fresh->sourceTranslation()?->highlights)->toBe(['Panoramic terrace', 'Skip-the-line access'])
        ->and($fresh->category_id)->toBe($newCategory->id)
        ->and($fresh->location)->toBe('Seville, Spain')
        ->and($fresh->difficulty_level)->toBe('moderate')
        ->and($fresh->guide_languages)->toBe(['es', 'en'])
        ->and($fresh->cover_image_url)->toBe('https://example.test/new-cover.jpg')
        ->and($fresh->media->pluck('url')->all())->toBe(['https://example.test/new-gallery.jpg', 'https://example.test/new-cover.jpg'])
        ->and($fresh->media->pluck('alt_text')->all())->toBe(['Gallery alt', 'New cover alt']);

    $freshA = $optA->fresh();
    $freshB = $optB->fresh();
    expect($freshA->title)->toBe('Revised option A')
        ->and($freshA->pickup_instructions)->toBe('North gate A')
        ->and($freshA->price_amount)->toBe(5500)
        ->and($freshA->group_size_max)->toBe(8)
        ->and($freshA->start_time->format('H:i:s'))->toBe('09:00:00')
        ->and($freshA->weekdays)->toBe([1, 3])
        ->and($freshA->sort_order)->toBe(0)
        ->and($freshB->title)->toBe('Revised option B')
        ->and($freshB->pickup_instructions)->toBe('South gate B')
        ->and($freshB->price_amount)->toBe(8900)
        ->and($freshB->group_size_max)->toBe(4)
        ->and($freshB->start_time->format('H:i:s'))->toBe('14:30:00')
        ->and($freshB->weekdays)->toBe([5])
        ->and($freshB->sort_order)->toBe(1);
});

it('dispatches search indexing and rotates catalog cache when approving gallery-only media changes', function () {
    TourRevisionFixtures::withCommittedPublicTour(function (array $ctx): void {
        /** @var Tour $tour */
        $tour = $ctx['tour'];
        /** @var Partner $partner */
        $partner = $ctx['partner'];
        $admin = TourRevisionFixtures::admin('manage_tours');

        Tour::withoutEvents(function () use ($tour): void {
            $tour->media()->create([
                'type' => 'cover',
                'url' => 'https://example.test/stable-cover.jpg',
                'alt_text' => 'Cover alt',
                'sort_order' => 0,
            ]);
            $tour->media()->create([
                'type' => 'gallery',
                'url' => 'https://example.test/gallery-one.jpg',
                'alt_text' => 'Gallery one alt',
                'sort_order' => 1,
            ]);
            $tour->update(['cover_image_url' => 'https://example.test/stable-cover.jpg']);
        });

        $service = app(TourRevisionService::class);
        $baseline = $service->captureBaseline($tour->fresh());
        $candidate = $service->createCandidate($tour->id, (int) $partner->user_id, $baseline->id, [
            'media' => [
                ['url' => 'https://example.test/stable-cover.jpg', 'is_cover' => true, 'alt_text' => 'Cover alt'],
                ['url' => 'https://example.test/gallery-two.jpg', 'is_cover' => false, 'alt_text' => 'Updated gallery alt'],
            ],
        ]);
        $service->submitCandidate($tour->id, $candidate->id, (int) $partner->user_id);

        Queue::fake([GenerateTourTranslationJob::class, IndexTourAction::class]);
        $generationBefore = app(TourPublicCacheService::class)->catalogGeneration();

        app(ApproveTourRevisionAction::class)->execute($admin, $tour->id, $candidate->id);

        Queue::assertNotPushed(GenerateTourTranslationJob::class);
        $indexJob = null;
        Queue::assertPushed(IndexTourAction::class, function (IndexTourAction $job) use (&$indexJob): bool {
            $indexJob = $job;

            return true;
        });
        expect($indexJob)->toBeInstanceOf(IndexTourAction::class);

        $engine = new class extends CollectionEngine
        {
            public array $documents = [];

            public function update($models): void
            {
                foreach ($models as $model) {
                    $this->documents[$model->getScoutKey()] = $model->toSearchableArray();
                }
            }
        };
        app(EngineManager::class)->extend('egm003_media_recording', fn () => $engine);
        config(['scout.driver' => 'egm003_media_recording', 'scout.queue' => false]);
        $indexJob->handle();

        expect(app(TourPublicCacheService::class)->catalogGeneration())->not->toBe($generationBefore)
            ->and($tour->fresh()->media()->orderBy('sort_order')->pluck('url')->all())->toBe([
                'https://example.test/stable-cover.jpg',
                'https://example.test/gallery-two.jpg',
            ])
            ->and($engine->documents[$tour->id]['image_urls'] ?? [])->toBe([
                'https://example.test/stable-cover.jpg',
                'https://example.test/gallery-two.jpg',
            ]);
    });
});

it('validates declared canonical correction fields, audits proposals, and runs ordinary approve or reject review', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, sourceLocale: 'it');
    $service = app(TourRevisionService::class);
    $baseline = $service->captureBaseline($tour);
    $this->admin->adminPermission()->update(['flags' => ['manage_tours' => true, 'correct_tour_content' => true]]);
    $admin = $this->admin->fresh('adminPermission');

    expect(fn () => $service->proposeCorrection(
        $tour,
        $admin,
        'Invalid presentation path',
        TourRevisionFixtures::refs('presentation.location'),
        ['presentation' => ['location' => 'Milan']],
    ))->toThrow(fn (TourRevisionException $e) => $e->errorCode === TourRevisionException::UNDECLARED_FIELD_CHANGE);

    expect(fn () => $service->proposeCorrection(
        $tour,
        $admin,
        'Undeclared field change',
        TourRevisionFixtures::refs('source.title'),
        ['source' => ['meeting_point' => 'Changed without declaration']],
    ))->toThrow(fn (TourRevisionException $e) => $e->errorCode === TourRevisionException::UNDECLARED_FIELD_CHANGE);

    $correction = app(ProposeTourCorrectionAction::class)->execute($admin, $tour, [
        'reason' => 'Fix factual meeting point, highlights, and itinerary.',
        'field_refs' => "source.meeting_point\nsource.highlights\nsource.itinerary",
        'source_meeting_point' => 'Piazza Navona fountain steps',
        'source_highlights' => "Central meeting spot\nLicensed guide",
        'source_itinerary' => json_encode([
            ['day' => 1, 'title' => 'Fountain walk', 'description' => null, 'stops' => [['title' => 'Navona', 'duration_minutes' => 30]]],
        ], JSON_THROW_ON_ERROR),
    ]);

    expect($correction->kind)->toBe(TourContentRevision::KIND_ADMIN_CORRECTION)
        ->and($correction->state)->toBe(TourContentRevision::STATE_PROPOSED)
        ->and($tour->fresh()->sourceTranslation()?->meeting_point)->toBe('EGM003 authored meeting point')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.correction.proposed')->count())->toBe(1);

    app(ApproveTourRevisionAction::class)->execute($admin, $tour->id, $correction->id);

    $approvedTour = $tour->fresh();
    expect($approvedTour->sourceTranslation()?->meeting_point)->toBe('Piazza Navona fountain steps')
        ->and($approvedTour->sourceTranslation()?->highlights)->toBe(['Central meeting spot', 'Licensed guide'])
        ->and($approvedTour->sourceTranslation()?->itinerary[0]['title'])->toBe('Fountain walk')
        ->and($tour->revisionRequests()->where('revision_id', $correction->id)->firstOrFail()->state)->toBe(TourRevisionRequest::STATE_APPROVED);

    $second = app(ProposeTourCorrectionAction::class)->execute($admin, $approvedTour, [
        'reason' => 'Test rejecting an exceptional correction.',
        'field_refs' => 'source.meeting_point',
        'source_meeting_point' => 'Should not be promoted',
    ]);

    app(RejectTourRevisionAction::class)->execute($admin, $approvedTour->id, $second->id, 'Superfluous change requested.');

    expect($second->fresh()->state)->toBe(TourContentRevision::STATE_REJECTED)
        ->and($approvedTour->fresh()->sourceTranslation()?->meeting_point)->toBe('Piazza Navona fountain steps')
        ->and($approvedTour->revisionRequests()->where('revision_id', $second->id)->firstOrFail()->state)->toBe(TourRevisionRequest::STATE_REJECTED)
        ->and($baseline->id)->not->toBe($correction->id);
});

it('rolls back all publication mutations when governance audit logging fails', function () {
    $tour = TourRevisionFixtures::tour(partner: $this->partner, status: 'pending_review');

    expect(DB::selectOne('select current_database() as name')->name)->toBe('bookly_test');
    DB::statement("ALTER TABLE governance_audit_logs ADD CONSTRAINT egm003_test_refuse_publish CHECK (action <> 'tour.publish')");
    try {
        expect(fn () => app(ApproveTourAction::class)->execute($this->admin, $tour))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE governance_audit_logs DROP CONSTRAINT egm003_test_refuse_publish');
    }

    $fresh = $tour->fresh();
    expect($fresh->status)->toBe('pending_review')
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->published_revision_id)->toBeNull()
        ->and($fresh->contentVersion())->toBe(0)
        ->and(TourContentRevision::where('tour_id', $tour->id)->count())->toBe(0)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
});
