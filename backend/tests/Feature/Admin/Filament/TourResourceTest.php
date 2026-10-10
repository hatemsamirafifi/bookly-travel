<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Services\TourTranslationService;
use App\Enums\TourStatus;
use App\Filament\Resources\TourResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function adminWithToursFlag(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_tours' => true]]);

    return $admin->fresh('adminPermission');
}

function makeApprovedPartnerTour(string $status = 'pending_review'): Tour
{
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'filament-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => $status,
    ]);
    $tour->translations()->create([
        'locale' => 'en',
        'title' => 'English moderation source',
        'description' => 'The required English description shown to moderators before publication.',
        'itinerary' => [['day' => 1, 'title' => 'English arrival day', 'stops' => []]],
    ]);

    return $tour;
}

if (! function_exists('t036FilSeedTour')) {
    function t036FilSeedTour(string $status = 'pending_review'): Tour
    {
        $partnerUser = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);

        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
            'slug' => 't036-filament-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'cover_image_url' => 'https://images.example.com/t036-cover.jpg',
            'status' => $status,
        ]);
        $tour->translations()->create([
            'locale' => 'en',
            'title' => 'T036 filament English source',
            'description' => 'T036 filament authoritative English description.',
        ]);
        PricingTier::create([
            'tour_id' => $tour->id,
            'name' => 'Standard',
            'price' => 50.00,
            'min_participants' => 1,
            'max_participants' => 10,
        ]);

        return $tour->fresh();
    }
}

if (! function_exists('t036FilDerivativeStates')) {
    function t036FilDerivativeStates(Tour $tour, string $esStatus, string $itStatus): void
    {
        $english = $tour->translations()->where('locale', 'en')->firstOrFail();
        $hash = app(TourTranslationService::class)->sourceHash($english);

        foreach (['es' => $esStatus, 'it' => $itStatus] as $locale => $status) {
            $tour->translationStates()->updateOrCreate(
                ['locale' => $locale],
                [
                    'source_hash' => $hash,
                    'translated_hash' => null,
                    'status' => $status,
                    'last_error_code' => $status === 'failed' ? 'PROVIDER_TIMEOUT' : null,
                ]
            );
        }
    }
}

if (! function_exists('t036FilEnglishCase')) {
    function t036FilEnglishCase(Tour $tour, string $case): void
    {
        $enId = $tour->translations()->where('locale', 'en')->first()?->id;

        switch ($case) {
            case 'missing row':
                $tour->translations()->where('locale', 'en')->delete();
                break;
            case 'empty title':
                DB::table('tour_translations')->where('id', $enId)->update(['title' => '']);
                break;
            case 'whitespace title':
                DB::table('tour_translations')->where('id', $enId)->update(['title' => '   ']);
                break;
            case 'empty description':
                DB::table('tour_translations')->where('id', $enId)->update(['description' => '']);
                break;
            case 'whitespace description raw':
                DB::table('tour_translations')->where('id', $enId)->update(['description' => "  \t \n  "]);
                break;
            case 'null description':
                DB::table('tour_translations')->where('id', $enId)->update(['description' => null]);
                break;
        }
    }
}

beforeEach(function () {
    $this->admin = adminWithToursFlag();
    actingAs($this->admin);
});

it('lists tour records in the Filament table', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->assertCanSeeTableRecords([$tour]);
});

it('shows the English source and derived statuses separately on the moderation view', function () {
    $tour = makeApprovedPartnerTour();
    $tour->translationStates()->create(['locale' => 'es', 'source_hash' => str_repeat('a', 64), 'status' => 'pending']);
    $tour->translationStates()->create(['locale' => 'it', 'source_hash' => str_repeat('a', 64), 'status' => 'stale']);

    Livewire::test(TourResource\Pages\ViewTour::class, ['record' => $tour->getRouteKey()])
        ->assertSee('English moderation source')
        ->assertSee('English arrival day')
        ->assertSee('Spanish')
        ->assertSee('Pending')
        ->assertSee('Italian')
        ->assertSee('Outdated')
        ->assertDontSee(str_repeat('a', 64));
});

it('denies a traveler and an admin without manage-tours access to moderation', function () {
    $tour = makeApprovedPartnerTour();
    actingAs(User::factory()->create());
    $this->get('/admin/tours')->assertForbidden();

    $limitedAdmin = User::factory()->admin()->create();
    $limitedAdmin->adminPermission()->create(['flags' => ['manage_tours' => false]]);
    actingAs($limitedAdmin);
    $this->get('/admin/tours')->assertForbidden();

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
});

it('publishes a tour via the Filament publish action and writes audit', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableAction('publish', $tour)
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeTrue();
});

it('rejects a tour via the Filament reject action with a reason', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableAction('reject', $tour, ['rejection_reason' => 'Bad photos'])
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe(TourStatus::Rejected->value);
    $log = GovernanceAuditLog::where('action', 'tour.reject')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()->and($log->metadata['reason'])->toBe('Bad photos');
});

it('bulk-publishes selected tours and writes an audit per item', function () {
    $tourA = makeApprovedPartnerTour('pending_review');
    $tourB = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableBulkAction('bulk_publish', [$tourA, $tourB])
        ->assertHasNoTableActionErrors();

    expect($tourA->fresh()->status)->toBe(TourStatus::Published->value)
        ->and($tourB->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$tourA->id, $tourB->id])->where('action', 'tour.publish')->count())->toBe(2);
});

it('bulk-rejects selected tours with a shared reason and writes an audit per item', function () {
    $tourA = makeApprovedPartnerTour('pending_review');
    $tourB = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableBulkAction('bulk_reject', [$tourA, $tourB], ['rejection_reason' => 'Policy violation'])
        ->assertHasNoTableActionErrors();

    expect($tourA->fresh()->status)->toBe(TourStatus::Rejected->value)
        ->and($tourB->fresh()->status)->toBe(TourStatus::Rejected->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$tourA->id, $tourB->id])->where('action', 'tour.reject')->count())->toBe(2);
});

// ---- T036 (Spec019 US3): Filament publish English-source gate ----

it('T036 publishes via the Filament publish action despite pending/failed ES/IT derivative states', function (string $es, string $it) {
    Queue::fake();
    $tour = t036FilSeedTour('pending_review');
    t036FilDerivativeStates($tour, $es, $it);

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableAction('publish', $tour)
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe(TourStatus::Published->value);

    $log = GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->before_state)->toBe(['status' => 'pending_review'])
        ->and($log->after_state)->toBe(['status' => 'published']);
})->with([
    'both pending' => ['pending', 'pending'],
    'es pending it failed' => ['pending', 'failed'],
    'es failed it pending' => ['failed', 'pending'],
    'both failed' => ['failed', 'failed'],
]);

it('T036 refuses the Filament publish action when the English source is missing or blank', function (string $case) {
    Queue::fake();
    $tour = t036FilSeedTour('pending_review');
    t036FilDerivativeStates($tour, 'pending', 'failed');
    t036FilEnglishCase($tour, $case);

    Livewire::test(TourResource\Pages\ListTours::class)
        ->mountTableAction('publish', $tour->fresh())
        ->call('callMountedTableAction')
        ->assertStatus(422);

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeFalse();
})->with([
    'missing row' => ['missing row'],
    'empty title' => ['empty title'],
    'whitespace title' => ['whitespace title'],
    'empty description' => ['empty description'],
    'whitespace description raw' => ['whitespace description raw'],
    'null description' => ['null description'],
]);

it('T036 keeps publish authorization tied to manage-tours on the real record', function () {
    $tour = t036FilSeedTour('pending_review');

    expect($this->admin->can('publish', $tour))->toBeTrue();

    $limitedAdmin = User::factory()->admin()->create();
    $limitedAdmin->adminPermission()->create(['flags' => ['manage_tours' => false]]);
    expect($limitedAdmin->can('publish', $tour))->toBeFalse();

    actingAs($limitedAdmin);
    $this->get('/admin/tours')->assertForbidden();

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
});
