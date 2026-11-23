<?php

/*
 * EGM-003 permanent regressions — general administrative content governance.
 *
 * Policy (ADR 0024, owner-approved 2026-11-14; Constitution v3.0.0 §II/§IV):
 * partners own and edit canonical tour content; administrators request changes
 * with a mandatory reason and specific field references; Gemini derivatives are
 * never manually edited; edits to a published tour create a separate reviewable
 * revision; exceptional administrative corrections use a dedicated authorized
 * and audited domain Action.
 *
 * The general Filament form therefore never authors canonical source text,
 * never authors or edits a generated derivative, and never administratively
 * corrects a governed presentation field. Read-only controls are not enough:
 * the refusal happens server-side against the raw submitted state, before any
 * relationship row is persisted.
 *
 * These regressions deliberately use ONLY pre-existing production classes and
 * legacy fixtures (Tour, TourTranslation, Partner, Category, User and the real
 * Filament pages) so they reproduce the defect against the unchanged
 * application, without depending on the new revision tables or helpers.
 */

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourRevisionService;
use App\Filament\Resources\TourResource\Pages\CreateTour;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('egm003Admin')) {
    function egm003Admin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_tours' => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('egm003Partner')) {
    function egm003Partner(): Partner
    {
        return Partner::create([
            'user_id' => User::factory()->partner()->create()->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);
    }
}

if (! function_exists('egm003PublishedTour')) {
    /**
     * A valid, publicly published tour with an authored source in the given
     * locale plus a generated derivative row in each remaining locale.
     */
    function egm003PublishedTour(string $sourceLocale = 'en'): Tour
    {
        $tour = Tour::create([
            'partner_id' => egm003Partner()->id,
            'category_id' => Category::firstOrCreate(['slug' => 'egm003-test'], ['name' => 'EGM003 Test'])->id,
            'slug' => 'egm003-' . uniqid(),
            'source_locale' => $sourceLocale,
            'location' => 'Rome, Italy',
            'location_slug' => 'rome',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'difficulty_level' => 'easy',
            'guide_languages' => ['en'],
            'price_amount' => 5000,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $tour->translations()->create([
            'locale' => $sourceLocale,
            'title' => 'EGM003 authored source title',
            'description' => 'EGM003 authored source description in the governed source locale.',
            'meeting_point' => 'EGM003 authored meeting point',
        ]);

        foreach (Tour::derivativeLocalesFor($sourceLocale) as $locale) {
            $tour->translations()->create([
                'locale' => $locale,
                'title' => "EGM003 generated {$locale} title",
                'description' => "EGM003 generated {$locale} description.",
            ]);
        }

        return $tour->fresh();
    }
}

beforeEach(function () {
    $this->admin = egm003Admin();
    actingAs($this->admin);
});

function egm003FilamentCandidate(string $locale = 'en'): array
{
    $tour = egm003PublishedTour($locale);
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $ownerId = (int) $tour->partnerRecord->user_id;
    $candidate = $service->createCandidate($tour, $ownerId, $base->id, ['source' => ['title' => 'EGM003 reviewed replacement title']]);
    $service->submitCandidate($tour, $candidate->id, $ownerId);

    return [$tour, $candidate];
}

it('compares and approves the exact source revision through real Filament', function (string $locale) {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate($locale);
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->assertActionExists('approve_revision')
        ->mountAction('approve_revision')
        ->assertSet('mountedActionsData.0.revision_id', $candidate->id)
        ->assertSee('EGM003 authored source title')
        ->assertSee('EGM003 reviewed replacement title')
        ->callMountedAction()->assertHasNoActionErrors();
    expect($candidate->fresh()->state)->toBe('approved')
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('EGM003 reviewed replacement title');
})->with(['en', 'es', 'it']);

it('refuses an obsolete Filament approval modal without approving its replacement', function () {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate();
    $component = Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])->mountAction('approve_revision');
    $service = app(TourRevisionService::class);
    $replacement = $service->replaceCandidate($tour, $candidate->id, $tour->partnerRecord->user_id,
        $candidate->base_revision_id, $candidate->payload_hash, ['source' => ['title' => 'A newer unreviewed replacement']]);
    $service->submitCandidate($tour, $replacement->id, $tour->partnerRecord->user_id);
    $component->call('$refresh')
        ->assertSet('mountedActionsData.0.revision_id', $candidate->id)
        ->assertSee('EGM003 reviewed replacement title')
        ->assertDontSee('A newer unreviewed replacement');
    $component->callMountedAction()->assertHasActionErrors(['revision_id'])
        ->assertSee('The referenced revision was replaced by a newer one.');
    expect($replacement->fresh()->state)->toBe('proposed')
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('EGM003 authored source title');
});

it('requires a rejection reason through the real Filament revision action', function () {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate();
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->callAction('reject_revision', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
    expect($candidate->fresh()->state)->toBe('proposed');
});

it('exposes initial-review requests for a pending never-public tour in Filament', function () {
    Queue::fake();
    $tour = egm003PublishedTour();
    $tour->update(['status' => 'pending_review', 'published_at' => null]);
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->assertActionVisible('request_changes')
        ->callAction('request_changes', data: ['context' => 'initial_review', 'reason' => 'Clarify the source title.', 'field_refs' => 'source.title'])
        ->assertHasNoActionErrors();
    expect($tour->fresh()->status)->toBe('rejected')
        ->and($tour->revisionRequests()->firstOrFail()->reason)->toBe('Clarify the source title.');
});

it('escapes proposed content and displays nested itinerary and presentation fields in the review modal', function () {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate();
    $service = app(TourRevisionService::class);
    $replacement = $service->replaceCandidate($tour, $candidate->id, $tour->partnerRecord->user_id,
        $candidate->base_revision_id, $candidate->payload_hash, ['source' => [
            'title' => '<script>alert("review-xss")</script>',
            'itinerary' => [['day' => 1, 'title' => 'Old town route', 'stops' => [['title' => 'Square', 'description' => 'Distinct stop description']]]],
        ]]);
    $service->submitCandidate($tour, $replacement->id, $tour->partnerRecord->user_id);
    $component = Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])->mountAction('approve_revision');
    expect($component->html())->toContain('&lt;script&gt;', 'Distinct stop description', 'presentation.category_id', 'presentation.guide_languages')
        ->not->toContain('<script>alert("review-xss")</script>');
});

it('rejects the exact reviewed candidate with a mandatory reason and correct audit before-state', function () {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate();
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->callAction('reject_revision', data: ['reason' => 'Clarify the proposed meeting instructions.'])->assertHasNoActionErrors();
    $audit = GovernanceAuditLog::where('target_id', $tour->id)->where('action', 'tour.revision.rejected')->firstOrFail();
    expect($candidate->fresh()->state)->toBe('rejected')
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('EGM003 authored source title')
        ->and($audit->before_state['revision_state'])->toBe('proposed')
        ->and($audit->metadata['reason'])->toBe('Clarify the proposed meeting instructions.');
});

it('refuses a mounted Filament approval after its actor permission is revoked', function () {
    Queue::fake();
    [$tour, $candidate] = egm003FilamentCandidate();
    $component = Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])->mountAction('approve_revision');
    DB::table('admin_permissions')->where('user_id', $this->admin->id)->update(['flags' => '{}']);
    $component->call('callMountedAction')->assertForbidden();
    expect($candidate->fresh()->state)->toBe('proposed')
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('EGM003 authored source title');
});

it('requires exceptional permission and proposes a correction for ordinary review without changing live content', function () {
    Queue::fake();
    $tour = egm003PublishedTour('it');
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])->assertActionHidden('propose_correction');
    $this->admin->adminPermission()->update(['flags' => ['manage_tours' => true, 'correct_tour_content' => true]]);
    actingAs($this->admin->fresh());
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->callAction('propose_correction', data: ['reason' => 'Exceptional factual title correction.', 'field_refs' => 'source.title', 'source_title' => 'Corrected Italian source title'])
        ->assertHasNoActionErrors();
    $candidate = app(TourRevisionService::class)->activeCandidate($tour);
    expect($candidate->kind)->toBe('admin_correction')->and($candidate->state)->toBe('proposed')
        ->and($tour->fresh()->sourceTranslation()->title)->toBe('EGM003 authored source title');
});

it('shows invalid field-reference feedback on the actual Filament change-request control', function () {
    Queue::fake();
    $tour = egm003PublishedTour();
    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->callAction('request_changes', data: ['context' => 'published_review', 'reason' => 'Clarify this field.', 'field_refs' => 'source.no_such_field'])
        ->assertHasActionErrors(['field_refs'])
        ->assertSee('The referenced field does not exist on this content.');
    expect($tour->revisionRequests()->count())->toBe(0);
});

// ---- General Filament edit form: canonical source authoring is forbidden ----
//
// The form renders NO translation control, so a tampered source write arrives
// as raw Livewire state (`data.translations`). `->set()` injects exactly that
// server-side payload; `fillForm()` would only exercise a missing control and
// prove nothing about the backend.

it('EGM003 refuses a general Filament write of the authored source of a published tour', function (string $locale) {
    Queue::fake();

    $tour = egm003PublishedTour($locale);
    $rows = fn (Tour $record) => $record->translations()
        ->get(['locale', 'title', 'description', 'meeting_point'])
        ->values()
        ->map(fn ($row) => ['locale' => $row->locale, 'title' => $row->title, 'description' => $row->description, 'meeting_point' => $row->meeting_point])
        ->all();
    $before = $rows($tour);

    $component = Livewire::test(EditTour::class, ['record' => $tour->id])
        ->set('data.translations', [
            'egm003-forged' => [
                'locale' => $locale,
                'title' => 'Forged administrative source title',
                'description' => 'Forged administrative source description.',
                'meeting_point' => 'Forged administrative meeting point',
            ],
        ])
        ->call('save');

    $fresh = $tour->fresh();

    expect($rows($fresh))->toBe($before, "The general Filament form rewrote the {$locale} governed content rows.")
        ->and($fresh->status)->toBe('published')
        ->and($fresh->published_at)->not->toBeNull();

    $component->assertHasErrors();
})->with(['en', 'es', 'it']);

// ---- General Filament edit form: generated derivatives are never editable ----

it('EGM003 refuses a general Filament write of a generated derivative of a published tour', function (string $locale) {
    Queue::fake();

    $tour = egm003PublishedTour('en');
    $derivative = $locale;
    $rows = fn (Tour $record) => $record->translations()
        ->get(['locale', 'title', 'description'])
        ->values()
        ->map(fn ($row) => ['locale' => $row->locale, 'title' => $row->title, 'description' => $row->description])
        ->all();
    $before = $rows($tour);

    $component = Livewire::test(EditTour::class, ['record' => $tour->id])
        ->set('data.translations', [
            'egm003-forged-derivative' => [
                'locale' => $derivative,
                'title' => 'Forged administrative derivative title',
                'description' => 'Forged administrative derivative description.',
            ],
        ])
        ->call('save');

    $fresh = $tour->fresh();

    expect($rows($fresh))->toBe($before, 'The general Filament form rewrote a generated derivative row.');

    $component->assertHasErrors();
})->with(['es', 'it']);

// ---- General Filament edit form: no additional locale row may be authored ----

it('EGM003 refuses a general Filament write that adds another translation row', function () {
    Queue::fake();

    $tour = egm003PublishedTour('en');
    $tour->translations()->where('locale', 'it')->delete();
    $before = $tour->translations()->count();

    Livewire::test(EditTour::class, ['record' => $tour->id])
        ->set('data.translations', [
            'egm003-forged-row' => [
                'locale' => 'it',
                'title' => 'Forged additional locale title',
                'description' => 'Forged additional locale description.',
                'meeting_point' => null,
                'cancellation_policy' => null,
            ],
        ])
        ->call('save')
        ->assertHasErrors();

    expect($tour->fresh()->translations()->count())->toBe($before, 'The general Filament form added a translation row.');
});

// ---- General Filament edit form: governed presentation fields are governed ---
//
// Every governed presentation field is tampered through raw state too, because
// the controls are read-only on the real page and a control's own hydration
// would mask a server-side bypass.

it('EGM003 refuses a general Filament write of governed presentation fields', function () {
    Queue::fake();

    $tour = egm003PublishedTour('en');
    $otherCategory = Category::firstOrCreate(['slug' => 'egm003-other'], ['name' => 'EGM003 Other']);
    $before = [
        'slug' => $tour->slug,
        'location' => $tour->location,
        'location_slug' => $tour->location_slug,
        'duration_minutes' => $tour->duration_minutes,
        'duration_label' => $tour->duration_label,
        'group_size_min' => $tour->group_size_min,
        'group_size_max' => $tour->group_size_max,
        'cover_image_url' => $tour->cover_image_url,
        'category_id' => $tour->category_id,
    ];

    $component = Livewire::test(EditTour::class, ['record' => $tour->id])
        ->set('data.slug', 'egm003-forged-slug')
        ->set('data.category_id', $otherCategory->id)
        ->set('data.location', 'Forged location')
        ->set('data.location_slug', 'forged-location')
        ->set('data.duration_minutes', 999)
        ->set('data.duration_label', 'Forged duration')
        ->set('data.group_size_min', 99)
        ->set('data.group_size_max', 199)
        ->set('data.cover_image_url', 'https://images.example.com/egm003-forged-cover.jpg')
        ->call('save');

    $fresh = $tour->fresh();

    expect($fresh->slug)->toBe($before['slug'], 'The general Filament form rewrote the governed slug.')
        ->and($fresh->location)->toBe($before['location'])
        ->and($fresh->location_slug)->toBe($before['location_slug'])
        ->and($fresh->duration_minutes)->toBe($before['duration_minutes'])
        ->and($fresh->duration_label)->toBe($before['duration_label'])
        ->and($fresh->group_size_min)->toBe($before['group_size_min'])
        ->and($fresh->group_size_max)->toBe($before['group_size_max'])
        ->and($fresh->cover_image_url)->toBe($before['cover_image_url'])
        ->and($fresh->category_id)->toBe($before['category_id']);

    $component->assertHasErrors();
});

// ---- General Filament create form: no canonical authoring ------------------

it('EGM003 refuses general Filament creation of a tour', function () {
    Queue::fake();

    $slug = 'egm003-create-' . uniqid();

    Livewire::test(CreateTour::class)
        ->fillForm(function (array $state) use ($slug) {
            $items = $state['translations'] ?? [];
            $itemKey = is_array($items) && $items !== [] ? array_key_first($items) : 0;

            return [
                'slug' => $slug,
                'category_id' => Category::firstOrCreate(['slug' => 'egm003-test'], ['name' => 'EGM003 Test'])->id,
                'partner_id' => egm003Partner()->id,
                'status' => 'draft',
                'location' => 'Rome, Italy',
                'price_amount' => 5000,
                'translations' => [
                    $itemKey => [
                        'locale' => 'en',
                        'title' => 'EGM003 forged create title',
                        'description' => 'EGM003 forged create description.',
                    ],
                ],
            ];
        })
        ->call('create')
        ->assertHasFormErrors();

    expect(Tour::where('slug', $slug)->exists())
        ->toBeFalse('The general Filament create form persisted a tour.');
});

it('displays linked review requests, partner response notes, and change_summary inside Filament comparison modals', function () {
    Queue::fake();
    $tour = egm003PublishedTour('en');
    $service = app(TourRevisionService::class);
    $base = $service->captureBaseline($tour);
    $ownerId = (int) $tour->partnerRecord->user_id;

    $req = $service->requestChanges(
        $tour,
        $this->admin,
        'published_review',
        'Please specify the gate number in the meeting point.',
        [['path' => 'source.meeting_point', 'note' => 'Include landmark']],
    );

    $candidate = $service->createCandidate($tour, $ownerId, $base->id, [
        'source' => ['meeting_point' => 'Gate 3 by the north arch'],
    ], 'Updated meeting point gate');
    $service->submitCandidate($tour, $candidate->id, $ownerId);
    $service->respondToRequest(
        $tour,
        $req->id,
        $tour->partner_id,
        $ownerId,
        'Clarified entrance gate in source.',
        ['revision_id' => $candidate->id],
    );

    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->mountAction('approve_revision')
        ->assertSee('Updated meeting point gate')
        ->assertSee('Please specify the gate number in the meeting point.')
        ->assertSee('source.meeting_point')
        ->assertSee('Clarified entrance gate in source.');
});

it('hides propose_correction when the published tour owner is publicly ineligible', function () {
    Queue::fake();
    $tour = egm003PublishedTour('en');
    $this->admin->adminPermission()->update(['flags' => ['manage_tours' => true, 'correct_tour_content' => true]]);
    actingAs($this->admin->fresh('adminPermission'));

    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->assertActionVisible('propose_correction');

    $tour->partnerRecord()->update(['is_active' => false]);

    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->assertActionHidden('propose_correction');
});
