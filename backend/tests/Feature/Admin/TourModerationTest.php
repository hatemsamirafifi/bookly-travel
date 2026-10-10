<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\RejectTourAction;
use App\Domains\Admin\Actions\UnpublishTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

if (! function_exists('adminWithFlag')) {
    function adminWithFlag(string $flag): User
    {
        $admin = User::factory()->admin()->create();

        $admin->adminPermission()->create(['flags' => [$flag => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('makePartner')) {
    function makePartner(string $onboarding = 'approved'): Partner
    {
        $user = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => $onboarding,
            'is_active' => $onboarding === 'approved',
        ]);
    }
}

if (! function_exists('makeTour')) {
    function makeTour(Partner $partner, string $status = 'pending_review'): Tour
    {
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
            'slug' => 'mod-tour-' . uniqid(),
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
            'title' => 'English source tour',
            'description' => 'Authoritative English tour description.',
        ]);

        return $tour;
    }
}

if (! function_exists('t036ModPartnerWithToken')) {
    function t036ModPartnerWithToken(): array
    {
        $user = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);
        $token = $user->createToken('test', ['partner'])->plainTextToken;

        return [$partner, $token];
    }
}

if (! function_exists('t036ModSeedTour')) {
    function t036ModSeedTour(Partner $partner, string $status = 'draft'): Tour
    {
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
            'slug' => 't036-mod-' . uniqid(),
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
            'title' => 'T036 English source tour',
            'description' => 'T036 authoritative English tour description.',
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

if (! function_exists('t036ModDerivativeStates')) {
    function t036ModDerivativeStates(Tour $tour, string $esStatus, string $itStatus): void
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

if (! function_exists('t036ModEnglishCase')) {
    function t036ModEnglishCase(Tour $tour, string $case): void
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
    $this->admin = adminWithFlag('manage_tours');
});

it('publishes a pending tour for an approved partner and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'pending_review');

    $tour = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($tour->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeTrue();

    $log = GovernanceAuditLog::where('action', 'tour.publish')->first();
    expect($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->before_state)->toBe(['status' => 'pending_review'])
        ->and($log->after_state)->toBe(['status' => 'published']);
});

it('blocks publishing when the owning partner is not approved (FR-005)', function () {
    $partner = makePartner('pending');
    $tour = makeTour($partner, 'pending_review');

    expect($tour->canTransitionTo(TourStatus::Published))->toBeFalse()
        ->and(fn () => app(ApproveTourAction::class)->execute($this->admin, $tour))->toThrow(HttpException::class)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->exists())->toBeFalse();
});

it('requires English source at approval but not Spanish or Italian', function () {
    $tour = makeTour(makePartner('approved'), 'pending_review');
    expect($tour->translations()->pluck('locale')->all())->toBe(['en']);

    app(ApproveTourAction::class)->execute($this->admin, $tour);
    expect($tour->fresh()->status)->toBe('published');

    $withoutEnglish = makeTour(makePartner('approved'), 'pending_review');
    $withoutEnglish->translations()->delete();
    expect(fn () => app(ApproveTourAction::class)->execute($this->admin, $withoutEnglish))
        ->toThrow(HttpException::class);
});

it('rejects a pending tour with a reason and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'pending_review');

    $tour = app(RejectTourAction::class)->execute($this->admin, $tour, ['rejection_reason' => 'Needs better photos']);

    expect($tour->fresh()->status)->toBe(TourStatus::Rejected->value);

    $log = GovernanceAuditLog::where('action', 'tour.reject')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['reason'])->toBe('Needs better photos')
        ->and($log->before_state)->toBe(['status' => 'pending_review'])
        ->and($log->after_state)->toBe(['status' => 'rejected']);
});

it('unpublishes a published tour and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'published');

    $tour = app(UnpublishTourAction::class)->execute($this->admin, $tour);

    expect($tour->fresh()->status)->toBe(TourStatus::Draft->value)
        ->and(GovernanceAuditLog::where('action', 'tour.unpublish')->where('target_id', $tour->id)->exists())->toBeTrue();
});

it('prevents rejecting a tour that is not in a rejectable state', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'draft');

    expect($tour->canTransitionTo(TourStatus::Rejected))->toBeFalse()
        ->and(fn () => app(RejectTourAction::class)->execute($this->admin, $tour, ['rejection_reason' => 'x']))->toThrow(HttpException::class);
});

// ---- T036 (Spec019 US3): submit/approve English-source gate ----

it('T036 submits a draft via the real endpoint when EN/pricing/cover hold across pending and failed ES/IT states', function (string $es, string $it) {
    Queue::fake();
    [$partner, $token] = t036ModPartnerWithToken();
    $tour = t036ModSeedTour($partner, 'draft');
    t036ModDerivativeStates($tour, $es, $it);

    postJson("/api/partner/tours/{$tour->id}/submit", [], ['Authorization' => 'Bearer ' . $token])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');

    expect(Tour::findOrFail($tour->id)->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
})->with([
    'both pending' => ['pending', 'pending'],
    'es pending it failed' => ['pending', 'failed'],
    'es failed it pending' => ['failed', 'pending'],
    'both failed' => ['failed', 'failed'],
]);

it('T036 refuses endpoint submit when the English source is missing or blank', function (string $case) {
    Queue::fake();
    [$partner, $token] = t036ModPartnerWithToken();
    $tour = t036ModSeedTour($partner, 'draft');
    t036ModDerivativeStates($tour, 'pending', 'failed');
    t036ModEnglishCase($tour, $case);

    postJson("/api/partner/tours/{$tour->id}/submit", [], ['Authorization' => 'Bearer ' . $token])
        ->assertUnprocessable();

    expect(Tour::findOrFail($tour->id)->status)->toBe('draft')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
})->with([
    'missing row' => ['missing row'],
    'empty title' => ['empty title'],
    'whitespace title' => ['whitespace title'],
    'empty description' => ['empty description'],
    'whitespace description raw' => ['whitespace description raw'],
    'null description' => ['null description'],
]);

it('T036 submits via the scoped TourService when the English source holds', function () {
    Queue::fake();
    $partner = makePartner('approved');
    $tour = t036ModSeedTour($partner, 'draft');
    t036ModDerivativeStates($tour, 'failed', 'pending');

    $result = app(TourService::class)->submitForReview($tour->fresh());

    expect($result->fresh()->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
});

it('T036 requires English in the scoped TourService submit path', function (string $case) {
    Queue::fake();
    $partner = makePartner('approved');
    $tour = t036ModSeedTour($partner, 'draft');
    t036ModEnglishCase($tour, $case);

    expect(fn () => app(TourService::class)->submitForReview($tour->fresh()))->toThrow(HttpException::class);

    expect(Tour::findOrFail($tour->id)->status)->toBe('draft')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
})->with([
    'missing row' => ['missing row'],
    'empty title' => ['empty title'],
    'whitespace title' => ['whitespace title'],
    'empty description' => ['empty description'],
    'whitespace description raw' => ['whitespace description raw'],
    'null description' => ['null description'],
]);

it('T036 approves a pending tour despite pending/failed ES/IT derivative states', function (string $es, string $it) {
    $partner = makePartner('approved');
    $tour = t036ModSeedTour($partner, 'pending_review');
    t036ModDerivativeStates($tour, $es, $it);

    $result = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($result->fresh()->status)->toBe(TourStatus::Published->value);

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

it('T036 refuses approval when the English source is missing or blank', function (string $case) {
    $partner = makePartner('approved');
    $tour = t036ModSeedTour($partner, 'pending_review');
    t036ModDerivativeStates($tour, 'pending', 'failed');
    t036ModEnglishCase($tour, $case);

    expect(fn () => app(ApproveTourAction::class)->execute($this->admin, $tour->fresh()))->toThrow(HttpException::class);

    expect(Tour::findOrFail($tour->id)->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeFalse();
})->with([
    'missing row' => ['missing row'],
    'empty title' => ['empty title'],
    'whitespace title' => ['whitespace title'],
    'empty description' => ['empty description'],
    'whitespace description raw' => ['whitespace description raw'],
    'null description' => ['null description'],
]);

it('T036 keeps submit owner scoping with cross-partner and unknown ids returning 404', function () {
    Queue::fake();
    [$partnerA, $tokenA] = t036ModPartnerWithToken();
    [, $tokenB] = t036ModPartnerWithToken();
    $tour = t036ModSeedTour($partnerA, 'draft');

    postJson("/api/partner/tours/{$tour->id}/submit", [], ['Authorization' => 'Bearer ' . $tokenB])->assertNotFound();
    postJson('/api/partner/tours/999999/submit', [], ['Authorization' => 'Bearer ' . $tokenA])->assertNotFound();

    expect(Tour::findOrFail($tour->id)->status)->toBe('draft')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->exists())->toBeFalse();
});
