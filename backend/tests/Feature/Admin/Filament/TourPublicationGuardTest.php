<?php

/*
 * EGM-002 permanent regressions — the tour publication gate.
 *
 * Policy (Constitution v3.0.0 §II/§IV): publication happens ONLY through
 * ApproveTourAction. The general Filament create/edit form may not change
 * lifecycle state; attempted writes are refused against the freshly locked
 * persisted row (no silent sanitizing, no partial saves — main data and
 * translation rows roll back together) and unauthorized actors can never
 * publish through the domain action.
 */

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\TourStatus;
use App\Filament\Resources\TourResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('egm002AdminWithToursFlag')) {
    function egm002AdminWithToursFlag(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_tours' => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('egm002Partner')) {
    function egm002Partner(string $onboardingStatus = 'approved'): Partner
    {
        $partnerUser = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => $onboardingStatus,
            'is_active' => ($onboardingStatus === 'approved'),
        ]);
    }
}

if (! function_exists('egm002Tour')) {
    function egm002Tour(
        Partner $partner,
        string $status,
        string $sourceLocale = 'en',
        bool $blankSourceDescription = false,
    ): Tour {
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'egm002-test'], ['name' => 'EGM002 Test'])->id,
            'slug' => 'egm002-' . uniqid(),
            'source_locale' => $sourceLocale,
            'location' => 'Rome, Italy',
            'location_slug' => 'rome',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => $status,
        ]);

        $tour->translations()->create([
            'locale' => $sourceLocale,
            'title' => 'EGM002 source title',
            'description' => $blankSourceDescription ? '' : 'EGM002 source description that would pass every publication gate.',
        ]);

        return $tour->fresh();
    }
}

beforeEach(function () {
    $this->admin = egm002AdminWithToursFlag();
    actingAs($this->admin);
});

// ---- General Filament create form: drafts only ------------------------------

it('EGM002 refuses any crafted lifecycle status on the Filament create form', function (string $case) {
    Queue::fake();

    $unapprovedPartner = egm002Partner('pending');
    $approvedPartner = egm002Partner();
    $slug = 'egm002-create-' . uniqid();

    Livewire::test(TourResource\Pages\CreateTour::class)
        // Fill through the real repeater item key so the refusal is the only
        // reason nothing was persisted (never a validation artifact).
        ->fillForm(function (array $rawState) use ($case, $unapprovedPartner, $approvedPartner, $slug): array {
            $items = $rawState['translations'] ?? [];
            $itemKey = is_array($items) && $items !== [] ? array_key_first($items) : null;

            return [
                'slug' => $slug,
                'category_id' => Category::firstOrCreate(['slug' => 'egm002-test'], ['name' => 'EGM002 Test'])->id,
                'partner_id' => ($case === 'unapproved partner' ? $unapprovedPartner : $approvedPartner)->id,
                'status' => TourStatus::Published->value,
                'location' => 'Rome, Italy',
                'price_amount' => 5000,
                'translations' => [
                    $itemKey ?? 0 => [
                        'locale' => 'en',
                        'title' => 'EGM002 crafted create title',
                        'description' => $case === 'blank source description' ? '' : 'EGM002 crafted create description text.',
                    ],
                ],
            ];
        })
        ->call('create')
        ->assertHasFormErrors();

    expect(Tour::where('slug', $slug)->exists())
        ->toBeFalse("The general Filament create form persisted a '{$case}' tour despite the refused lifecycle write (EGM-002).")
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->count())->toBe(0)
        ->and(Tour::where('status', TourStatus::Published->value)->exists())->toBeFalse('The general create form must never publish directly.');
})->with([
    'unapproved partner' => ['unapproved partner'],
    'blank source description' => ['blank source description'],
    'valid source and approved partner' => ['valid source and approved partner'],
]);

// ---- General Filament edit form: lifecycle changes refused ------------------

it('EGM002 refuses any crafted published status on the Filament edit form without partial changes', function (string $case) {
    Queue::fake();

    $partner = egm002Partner($case === 'unapproved partner' ? 'pending' : 'approved');

    $tour = match ($case) {
        // The exact Goldfish probe: an `es`-source draft saved as published.
        'es-source draft' => egm002Tour($partner, 'draft', 'es'),
        'es-source draft blank description' => egm002Tour($partner, 'draft', 'es', blankSourceDescription: true),
        'unapproved partner' => egm002Tour($partner, 'pending_review'),
        'rejected pre-state' => egm002Tour($partner, 'rejected'),
        'archived pre-state' => egm002Tour($partner, 'archived'),
    };

    $beforeStatus = $tour->status;
    $beforeTranslationCount = $tour->translations()->count();

    Livewire::test(TourResource\Pages\EditTour::class, ['record' => $tour->getRouteKey()])
        ->fillForm(['status' => TourStatus::Published->value])
        ->call('save')
        ->assertHasFormErrors();

    $fresh = $tour->fresh();

    expect($fresh->status)->toBe($beforeStatus, "The Filament edit form wrote the crafted published status '{$case}' instead of refusing the lifecycle write.")
        ->and($fresh->published_at)->toBeNull('An ungated publication lacks the guarded published_at stamp contract.')
        ->and($fresh->translations()->count())->toBe($beforeTranslationCount, 'The refused edit-save must not leave partial translation changes behind.')
        ->and($fresh->sourceTranslation()?->title)->toBe('EGM002 source title')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())
        ->toBeFalse('A general-form publication attempt must never record a tour.publish governance event.');
})->with([
    'es-source draft' => ['es-source draft'],
    'es-source draft blank description' => ['es-source draft blank description'],
    'unapproved partner' => ['unapproved partner'],
    'rejected pre-state' => ['rejected pre-state'],
    'archived pre-state' => ['archived pre-state'],
]);

it('EGM002 the edit form can never publish, even from an eligible pending review record', function () {
    Queue::fake();

    $tour = egm002Tour(egm002Partner(), 'pending_review', 'es');

    Livewire::test(TourResource\Pages\EditTour::class, ['record' => $tour->getRouteKey()])
        ->fillForm(['status' => TourStatus::Published->value])
        ->call('save')
        ->assertHasFormErrors();

    expect($tour->fresh()->status)->toBe('pending_review', 'A general-form save must refuse publication even when the record would be eligible for approval.')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())
        ->toBeFalse('Publication must remain only through ApproveTourAction.');
});

it('EGM002 a stale edit save is refused and cannot retract a legitimately published tour', function () {
    Queue::fake();

    $tour = egm002Tour(egm002Partner(), 'pending_review');

    $component = Livewire::test(TourResource\Pages\EditTour::class, ['record' => $tour->getRouteKey()]);

    // While this editor is open, the tour is legitimately published through
    // the guarded action by another authorised session.
    app(ApproveTourAction::class)->execute($this->admin, $tour->fresh());
    expect($tour->fresh()->status)->toBe(TourStatus::Published->value);

    // The stale form still carries `pending_review` from mount; saving it
    // with a metadata change must be refused whole, never partially applied.
    $component->fillForm(['location' => 'Must not change'])
        ->call('save')
        ->assertHasFormErrors();

    $fresh = $tour->fresh()->loadMissing('partnerRecord');

    expect($fresh->status)->toBe(TourStatus::Published->value, 'A stale edit-form save silently retracted a legitimately published tour.')
        ->and($fresh->published_at)->not->toBeNull('The publication stamp must survive a stale general save.')
        ->and($fresh->location)->toBe('Rome, Italy', 'The refused stale save must not persist its metadata either.')
        ->and($fresh->partnerRecord?->onboarding_status)->toBe('approved')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1)
        ->and(GovernanceAuditLog::where('action', 'tour.unpublish')->where('target_id', $tour->id)->exists())
        ->toBeFalse('A stale save must not stand in for the governed tour.unpublish transition.');
});

it('EGM002 a published tour is never reassigned to an unapproved partner through the edit form', function () {
    Queue::fake();

    $tour = egm002Tour(egm002Partner(), 'pending_review');

    // Legitimately published through the guarded action first.
    app(ApproveTourAction::class)->execute($this->admin, $tour->fresh());

    $unapprovedPartner = egm002Partner('pending');

    Livewire::test(TourResource\Pages\EditTour::class, ['record' => $tour->getRouteKey()])
        ->fillForm(['partner_id' => $unapprovedPartner->id])
        ->call('save')
        ->assertHasFormErrors();

    $fresh = $tour->fresh()->loadMissing('partnerRecord');

    expect($fresh->partner_id)->not->toBe($unapprovedPartner->id, 'The edit form reassigned a published tour to an unapproved partner.')
        ->and($fresh->partnerRecord?->onboarding_status)->toBe('approved', 'The published tour must keep its approved owner after the refused save.')
        ->and($fresh->status)->toBe(TourStatus::Published->value)
        ->and($fresh->translations()->count())->toBe(1, 'The refused ownership save must not leave partial translation changes behind.')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
});

// ---- ApproveTourAction actor authorization -----------------------------------

it('EGM002 the approval action refuses an unauthorized actor', function (string $case) {
    $tour = egm002Tour(egm002Partner(), 'pending_review');

    $actor = match ($case) {
        'partner-role actor' => User::factory()->partner()->create(),
        'admin without manage-tours flag' => tap(
            User::factory()->admin()->create(),
            fn (User $flaglessAdmin) => $flaglessAdmin->adminPermission()->create(['flags' => ['manage_tours' => false]]),
        ),
    };

    try {
        app(ApproveTourAction::class)->execute($actor, $tour);
        $this->fail('The approval action must refuse the unauthorized actor.');
    } catch (AuthorizationException) {
        // Explicit refusal confirmed; the checks below verify the ungated state.
    }

    $fresh = $tour->fresh();

    expect($fresh->status)->toBe('pending_review', "{$case} published the tour directly because the action trusted its actor parameter.")
        ->and($fresh->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())
        ->toBeFalse('Rejected unauthorized approval must record no tour.publish governance audit event.');
})->with([
    'partner-role actor' => 'partner-role actor',
    'admin without manage-tours flag' => 'admin without manage-tours flag',
]);
