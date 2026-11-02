<?php

use App\Domains\Admin\Actions\ArchiveTourAction;
use App\Domains\Admin\Actions\ExportBookingsAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Services\PlatformAnalyticsService;
use App\Domains\Admin\Services\TravelerModerationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Services\ApplicationFeeCalculator;
use App\Domains\Reviews\Actions\FlagReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Filament\Resources\DestinationResource;
use App\Filament\Resources\DestinationResource\Pages\CreateDestination;
use App\Filament\Resources\DestinationResource\Pages\EditDestination;
use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Filament\Resources\TourResource\Pages\ListTours;
use App\Filament\Resources\TravelerResource;
use App\Filament\Resources\TravelerResource\Pages\ListTravelers;
use App\Filament\Resources\TravelerResource\Pages\ViewTraveler;
use App\Filament\Widgets\BookingTrendsChartWidget;
use App\Filament\Widgets\TopRankingsWidget;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

if (! function_exists('marketOpsAdmin')) {
    function marketOpsAdmin(array $flags): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => $flags]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('marketOpsApprovedPartner')) {
    function marketOpsApprovedPartner(string $companyName = 'Market Ops Tours'): Partner
    {
        $user = User::factory()->partner()->create();

        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);

        $partner->profile()->create([
            'company_name' => $companyName,
            'contact_email' => $user->email,
        ]);

        return $partner->fresh(['profile']);
    }
}

if (! function_exists('marketOpsTour')) {
    function marketOpsTour(Partner $partner, string $location, int $price = 5000, string $status = 'published'): Tour
    {
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
            'slug' => 'marketops-' . Str::slug($location) . '-' . uniqid(),
            'location' => $location,
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 12,
            'price_amount' => $price,
            'status' => $status,
        ]);

        $tour->translations()->create([
            'locale' => 'en',
            'title' => str($location)->before(',')->toString() . ' Market Ops Tour',
            'description' => 'Market-ops description for the ' . $location . ' tour.',
            'itinerary' => [['day' => 1, 'title' => 'Arrival', 'stops' => []]],
        ]);

        return $tour;
    }
}

if (! function_exists('marketOpsBooking')) {
    function marketOpsBooking(User $traveler, Tour $tour, int $total, string $status, ?string $createdAt = null): Booking
    {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => $tour->id,
            'tour_date' => now()->toDateString(),
            'participant_count' => 2,
            'price_per_person' => $total,
            'total_price' => $total,
            'currency' => 'EUR',
            'status' => $status,
            'locale' => 'en',
        ]);

        if ($createdAt !== null) {
            $booking->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $booking->fresh();
    }
}

// ---------------------------------------------------------------------------
// T-B01: Marketplace analytics (KPIs, trends, top rankings, widgets)
// ---------------------------------------------------------------------------

it('computes KPI snapshot, trend series and top rankings from live data (T-B01)', function () {
    Cache::flush();
    $traveler = User::factory()->traveler()->create();
    $partnerA = marketOpsApprovedPartner('Rome Co');
    $partnerB = marketOpsApprovedPartner('Paris Co');

    $rome = marketOpsTour($partnerA, 'Rome, Italy');
    $paris = marketOpsTour($partnerB, 'Paris, France');

    marketOpsBooking($traveler, $rome, 5000, Booking::STATUS_CONFIRMED);
    marketOpsBooking($traveler, $rome, 8000, Booking::STATUS_CONFIRMED);
    marketOpsBooking($traveler, $rome, 999, Booking::STATUS_CANCELLED);
    marketOpsBooking($traveler, $paris, 3000, Booking::STATUS_COMPLETED);
    marketOpsBooking($traveler, $paris, 2000, Booking::STATUS_PENDING_PAYMENT);

    // booking_id is not nullable on reviews — anchor each review to a booking.
    $romeReviewBooking = marketOpsBooking($traveler, $rome, 4500, Booking::STATUS_COMPLETED, now()->subDays(2));
    $parisReviewBooking = marketOpsBooking($traveler, $paris, 3500, Booking::STATUS_COMPLETED, now()->subDays(2));

    Review::create([
        'booking_id' => $romeReviewBooking->id,
        'tour_id' => $rome->id,
        'traveler_id' => $traveler->id,
        'rating' => 5,
        'comment' => 'Great tour',
        'status' => 'visible',
        'locale' => 'en',
    ]);
    Review::create([
        'booking_id' => $parisReviewBooking->id,
        'tour_id' => $paris->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'comment' => 'Nice trip',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $analytics = app(PlatformAnalyticsService::class);

    [$expectedFrom, $expectedTo] = $analytics->resolveDateRange('30d');

    $kpis = $analytics->kpis('30d');
    $expectedCommission = (new ApplicationFeeCalculator)->calculate(
        24000,
        (string) config('services.stripe.platform_commission_percent', '15.00'),
    );

    expect($kpis['total_bookings'])->toBe(7)
        ->and($kpis['confirmed_bookings'])->toBe(2)
        ->and($kpis['cancelled_bookings'])->toBe(1)
        ->and($kpis['gross_booking_value_cents'])->toBe(24000)
        ->and($kpis['platform_commission_cents'])->toBe($expectedCommission)
        ->and($kpis['active_partners'])->toBe(2)
        ->and($kpis['published_tours'])->toBe(2)
        ->and($kpis['pending_tours'])->toBe(0)
        ->and($kpis['total_travelers'])->toBeGreaterThanOrEqual(1)
        ->and($kpis['flagged_reviews'])->toBe(0)
        ->and($kpis['average_rating'])->toBe(4.5);

    expect(Cache::has(sprintf('admin:analytics:kpis:30d:%s:%s', $expectedFrom->toDateString(), $expectedTo->toDateString())))->toBeTrue();

    $trends = $analytics->bookingAndRevenueTrends('30d');
    expect(count($trends['labels']))->toBe(30)
        ->and(count($trends['bookings']))->toBe(30)
        ->and(count($trends['revenue_cents']))->toBe(30)
        ->and(end($trends['bookings']))->toBe(5)
        ->and(end($trends['revenue_cents']))->toBe(16000)
        ->and(array_sum($trends['bookings']))->toBe(7)
        ->and(array_sum($trends['revenue_cents']))->toBe(24000);

    $topTours = $analytics->topTours(5);
    expect($topTours[0]['tour_id'])->toBe($rome->id)
        ->and($topTours[0]['bookings'])->toBe(4)
        ->and($topTours[0]['revenue_cents'])->toBe(17500)
        ->and($topTours[1]['tour_id'])->toBe($paris->id)
        ->and($topTours[1]['bookings'])->toBe(3)
        ->and($topTours[1]['revenue_cents'])->toBe(6500);

    $topPartners = $analytics->topPartners(5);
    expect($topPartners[0]['partner_id'])->toBe($partnerA->id)
        ->and($topPartners[0]['bookings'])->toBe(4)
        ->and($topPartners[0]['revenue_cents'])->toBe(17500)
        ->and($topPartners[0]['name'])->toBe('Rome Co');

    $topDestinations = $analytics->topDestinations(5);
    expect(count($topDestinations))->toBe(2)
        ->and(collect($topDestinations)->pluck('location')->contains('Rome, Italy'))->toBeTrue()
        ->and(collect($topDestinations)->pluck('location')->contains('Paris, France'))->toBeTrue()
        ->and($topDestinations[0]['bookings'])->toBe(4);
});

it('resolves every date-range preset, including swapped custom dates (T-B01)', function () {
    $analytics = app(PlatformAnalyticsService::class);

    [$from, $to] = $analytics->resolveDateRange('today');
    expect($from->isToday())->toBeTrue()->and($to->isToday())->toBeTrue();

    [$from, $to] = $analytics->resolveDateRange('yesterday');
    expect($from->isYesterday())->toBeTrue()->and($to->isYesterday())->toBeTrue();

    [$from, $to] = $analytics->resolveDateRange('7d');
    expect((int) $from->diffInDays($to))->toBe(6);

    [$from, $to] = $analytics->resolveDateRange('90d');
    expect((int) $from->diffInDays($to))->toBe(89);

    [$from, $to] = $analytics->resolveDateRange('custom', '2026-01-05', '2026-01-09');
    expect($from->toDateString())->toBe('2026-01-05')
        ->and($to->toDateString())->toBe('2026-01-09');

    [$from, $to] = $analytics->resolveDateRange('custom', '2026-01-09', '2026-01-05');
    expect($from->toDateString())->toBe('2026-01-05')
        ->and($to->toDateString())->toBe('2026-01-09');

    [$from, $to] = $analytics->resolveDateRange('custom', 'not-a-date', '2026-01-09');
    expect((int) $from->diffInDays($to))->toBe(29);

    [$from, $to] = $analytics->resolveDateRange('unknown-preset');
    expect((int) $from->diffInDays($to))->toBe(29);
});

it('gates the new analytics widgets behind view_all_analytics (T-B01)', function () {
    $withFlag = marketOpsAdmin(['view_all_analytics' => true]);
    $withoutFlag = marketOpsAdmin([]);

    actingAs($withFlag);
    expect(BookingTrendsChartWidget::canView())->toBeTrue()
        ->and(TopRankingsWidget::canView())->toBeTrue();

    actingAs($withoutFlag);
    expect(BookingTrendsChartWidget::canView())->toBeFalse()
        ->and(TopRankingsWidget::canView())->toBeFalse();

    actingAs($withFlag);
    Livewire::test(BookingTrendsChartWidget::class)->assertOk();

    Livewire::test(TopRankingsWidget::class)->assertOk();
});

// ---------------------------------------------------------------------------
// T-B02: Traveler moderation (suspend/reactivate + access control)
// ---------------------------------------------------------------------------

it('suspends a traveler, revoking every active API token and auditing it (T-B02)', function () {
    $admin = marketOpsAdmin(['manage_travelers' => true]);
    $traveler = User::factory()->traveler()->create();

    $traveler->createToken('mobile-app');
    $traveler->createToken('mobile-app-2');
    expect($traveler->tokens()->count())->toBe(2);

    app(TravelerModerationService::class)->suspend($admin, $traveler, 'Repeat refund abuse.');

    $traveler = $traveler->fresh();
    expect($traveler->status)->toBe('suspended')
        ->and($traveler->suspended_at)->not->toBeNull()
        ->and($traveler->suspension_reason)->toBe('Repeat refund abuse.')
        ->and($traveler->tokens()->count())->toBe(0);

    $log = GovernanceAuditLog::where('action', 'traveler.suspend')
        ->where('target_id', $traveler->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->target_type)->toBeString()->not->toBeNull()
        ->and($log->metadata['reason'])->toBe('Repeat refund abuse.')
        ->and($log->before_state['status'])->not->toBe('suspended')
        ->and($log->after_state['status'])->toBe('suspended');
});

it('reactivates a suspended traveler and clears the suspension fields (T-B02)', function () {
    $admin = marketOpsAdmin(['manage_travelers' => true, 'manage_users' => true]);
    $traveler = User::factory()->traveler()->create();

    $service = app(TravelerModerationService::class);
    $service->suspend($admin, $traveler, 'Manual check.');
    $service->reactivate($admin, $traveler);

    $traveler = $traveler->fresh();
    expect($traveler->status)->toBe('active')
        ->and($traveler->suspended_at)->toBeNull()
        ->and($traveler->suspension_reason)->toBeNull();

    expect(GovernanceAuditLog::where('action', 'traveler.reactivate')
        ->where('target_id', $traveler->id)
        ->exists())->toBeTrue();
});

it('refuses to suspend twice, an empty reason, or a non-traveler target (T-B02)', function () {
    $admin = marketOpsAdmin(['manage_travelers' => true]);
    $traveler = User::factory()->traveler()->create();
    $service = app(TravelerModerationService::class);

    expect(fn () => $service->suspend($admin, $traveler, '   '))
        ->toThrow(HttpException::class, 'A suspension reason is required.');

    $service->suspend($admin, $traveler, 'First strike');

    expect(fn () => $service->suspend($admin, $traveler->fresh(), 'Second strike'))
        ->toThrow(HttpException::class, 'Traveler is already suspended.');

    $adminUser = User::factory()->admin()->create();

    expect(fn () => $service->suspend($admin, $adminUser->fresh(), 'Not a traveler'))
        ->toThrow(HttpException::class, 'Only traveler accounts can be moderated.');
});

it('denies traveler moderation to admins without manage_travelers or manage_users (T-B02)', function () {
    $flagless = marketOpsAdmin([]);
    $traveler = User::factory()->traveler()->create();

    expect(TravelerModerationService::isAuthorized($flagless))->toBeFalse()
        ->and(fn () => app(TravelerModerationService::class)->suspend($flagless, $traveler, 'No flag'))
        ->toThrow(HttpException::class);

    // TravelerResource itself is gated on the same authorization helper.
    expect(TravelerResource::canViewAny())->toBeFalse();
});

it('suspends and reactivates travelers via the Filament list/table actions (T-B02)', function () {
    actingAs(marketOpsAdmin(['manage_travelers' => true]));

    $traveler = User::factory()->traveler()->create();
    $traveler->createToken('app');

    Livewire::test(ListTravelers::class)
        ->assertCanSeeTableRecords([$traveler])
        ->callTableAction('suspend', $traveler, ['reason' => 'Fraudulent reviews.'])
        ->assertHasNoTableActionErrors();

    expect($traveler->fresh()->status)->toBe('suspended')
        ->and($traveler->fresh()->suspension_reason)->toBe('Fraudulent reviews.')
        ->and($traveler->fresh()->tokens()->count())->toBe(0);

    Livewire::test(ListTravelers::class)
        ->callTableAction('reactivate', $traveler->fresh())
        ->assertHasNoTableActionErrors();

    expect($traveler->fresh()->status)->toBe('active');

    // Infolist + view page render with audit + booking/review summaries.
    Livewire::test(ViewTraveler::class, ['record' => $traveler->getRouteKey()])
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// T-B03: archive/flag/export marketplace actions
// ---------------------------------------------------------------------------

it('archives a published tour and audits the reason (T-B03)', function () {
    $admin = marketOpsAdmin(['manage_tours' => true]);
    $partner = marketOpsApprovedPartner();
    $tour = marketOpsTour($partner, 'Rome, Italy');
    $tour->update(['status' => 'published']);

    $archived = app(ArchiveTourAction::class)->execute($admin, $tour->fresh(), 'Contract terminated with partner.');

    expect($archived->fresh()->status)->toBe('archived')
        ->and(GovernanceAuditLog::where('action', 'tour.archive')
            ->where('target_id', $tour->id)
            ->where('target_type', 'tour')
            ->exists())->toBeTrue();

    $log = GovernanceAuditLog::where('action', 'tour.archive')->where('target_id', $tour->id)->first();
    expect($log->metadata['reason'])->toBe('Contract terminated with partner.')
        ->and($log->before_state['status'])->toBe('published')
        ->and($log->after_state['status'])->toBe('archived');
});

it('refuses to archive a draft tour, an already-archived tour, or without a reason (T-B03)', function () {
    $admin = marketOpsAdmin(['manage_tours' => true]);
    $partner = marketOpsApprovedPartner();

    $draft = marketOpsTour($partner, 'Naples, Italy', 4000, 'draft');

    expect(fn () => app(ArchiveTourAction::class)->execute($admin, $draft, 'No reason needed'))
        ->toThrow(HttpException::class, 'Tour cannot be archived from its current state.');

    $published = marketOpsTour($partner, 'Florence, Italy');
    $published->update(['status' => 'published']);

    expect(fn () => app(ArchiveTourAction::class)->execute($admin, $published->fresh(), '   '))
        ->toThrow(HttpException::class, 'An archive reason is required.');

    app(ArchiveTourAction::class)->execute($admin, $published->fresh(), 'End of season.');

    expect(fn () => app(ArchiveTourAction::class)->execute($admin, $published->fresh(), 'Again'))
        ->toThrow(HttpException::class, 'Tour cannot be archived from its current state.');
});

it('exposes the archive action on published tours and archives via Filament (T-B03)', function () {
    actingAs(marketOpsAdmin(['manage_tours' => true]));

    $partner = marketOpsApprovedPartner();
    $tour = marketOpsTour($partner, 'Venice, Italy');
    $tour->update(['status' => 'published']);

    Livewire::test(ListTours::class)
        ->callTableAction('archive', $tour->fresh(), ['reason' => 'Duplicate listing.'])
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe('archived');
    expect(GovernanceAuditLog::where('action', 'tour.archive')
        ->where('target_id', $tour->id)
        ->where('metadata->reason', 'Duplicate listing.')
        ->exists())->toBeTrue();
});

it('flags a visible review, drops it from the aggregate and audits it (T-B03)', function () {
    $admin = marketOpsAdmin(['moderate_reviews' => true]);
    $traveler = User::factory()->traveler()->create();
    $partner = marketOpsApprovedPartner();
    $tour = marketOpsTour($partner, 'Sicily, Italy');
    $booking = marketOpsBooking($traveler, $tour, 4500, Booking::STATUS_CONFIRMED, now()->subDays(2));

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 2,
        'comment' => 'Confusing and possibly fake.',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    $flagged = app(FlagReviewAction::class)->execute($review->fresh(), $admin->id, 'Suspected competitor astroturfing.');

    expect($flagged->fresh()->status)->toBe('flagged');

    $trail = ReviewAuditTrail::where('review_id', $review->id)->where('action', 'flag')->first();
    expect($trail)->not->toBeNull()
        ->and($trail->actor_type)->toBe('admin')
        ->and($trail->actor_id)->toBe($admin->id)
        ->and($trail->reason)->toBe('Suspected competitor astroturfing.');

    $log = GovernanceAuditLog::where('action', 'review.flag')
        ->where('target_id', $review->id)
        ->where('target_type', 'review')
        ->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['reason'])->toBe('Suspected competitor astroturfing.')
        ->and($log->before_state['status'])->toBe('visible')
        ->and($log->after_state['status'])->toBe('flagged');
});

it('only flags visible reviews and requires a real admin actor (T-B03)', function () {
    $admin = marketOpsAdmin(['moderate_reviews' => true]);
    $traveler = User::factory()->traveler()->create();
    $partner = marketOpsApprovedPartner();
    $tour = marketOpsTour($partner, 'Tokyo, Japan');
    $booking = marketOpsBooking($traveler, $tour, 7000, Booking::STATUS_CONFIRMED);

    $hidden = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 1,
        'comment' => 'Already hidden',
        'status' => 'hidden',
        'locale' => 'en',
    ]);

    expect(fn () => app(FlagReviewAction::class)->execute($hidden, $admin->id, 'nope'))
        ->toThrow(HttpException::class, 'Only visible reviews can be flagged.');

    $visibleBooking = marketOpsBooking($traveler, $tour, 7100, Booking::STATUS_CONFIRMED);
    $visible = Review::create([
        'booking_id' => $visibleBooking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 1,
        'comment' => 'Visible review',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    expect(fn () => app(FlagReviewAction::class)->execute($visible, 999999999, 'nope'))
        ->toThrow(HttpException::class, 'Admin actor not found.');
});

it('flags a visible review via the Filament review table action (T-B03)', function () {
    actingAs(marketOpsAdmin(['moderate_reviews' => true]));
    $traveler = User::factory()->traveler()->create();
    $partner = marketOpsApprovedPartner();
    $tour = marketOpsTour($partner, 'Singapore, Singapore');
    $booking = marketOpsBooking($traveler, $tour, 6000, Booking::STATUS_CONFIRMED);

    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 1,
        'comment' => 'Filament flag me',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    Livewire::test(ListReviews::class)
        ->callTableAction('flag', $review, ['reason' => 'Manual review needed.'])
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->status)->toBe('flagged');
});

it('exports bookings CSV for entitled admins and audits the export (T-B03)', function () {
    $admin = marketOpsAdmin(['manage_bookings' => true]);
    $traveler = User::factory()->traveler()->create();
    $partner = marketOpsApprovedPartner('Export Tours');
    $tour = marketOpsTour($partner, 'Barcelona, Spain');

    $confirmed = marketOpsBooking($traveler, $tour, 2500, Booking::STATUS_CONFIRMED);
    $cancelled = marketOpsBooking($traveler, $tour, 1000, Booking::STATUS_CANCELLED, now()->subDays(3));

    $action = app(ExportBookingsAction::class);

    expect(ExportBookingsAction::isAuthorized($admin))->toBeTrue();

    $allRows = $action->toCsv($admin, []);

    expect($confirmed->traveler->email)->toBeString();
    expect($allRows)->toContain('reference,traveler_email,tour_title,tour_date,participant_count,total_price,status,created_at')
        ->and($allRows)->toContain((string) $confirmed->reference)
        ->and($allRows)->toContain((string) $cancelled->reference);

    $onlyConfirmed = $action->toCsv($admin, ['status' => Booking::STATUS_CONFIRMED]);
    expect($onlyConfirmed)->toContain((string) $confirmed->reference)
        ->and($onlyConfirmed)->not->toContain((string) $cancelled->reference);

    $response = $action->download($admin, []);
    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');

    expect(ExportBookingsAction::isAuthorized(marketOpsAdmin([])))->toBeFalse();

    $log = GovernanceAuditLog::where('action', 'booking.export')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id);
});

it('requires export authorization and exposes the export_csv header action (T-B03)', function () {
    actingAs(marketOpsAdmin(['manage_bookings' => true]));

    $traveler = User::factory()->traveler()->create();
    $partner = marketOpsApprovedPartner('Header Export');
    $tour = marketOpsTour($partner, 'Lisbon, Portugal');
    $booking = marketOpsBooking($traveler, $tour, 1800, Booking::STATUS_CONFIRMED);

    expect(ExportBookingsAction::isAuthorized(marketOpsAdmin([])))->toBeFalse();

    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$booking])
        ->callAction('export_csv', data: ['status' => Booking::STATUS_CONFIRMED]);

    $log = GovernanceAuditLog::where('action', 'booking.export')
        ->where('metadata->filters->status', Booking::STATUS_CONFIRMED)
        ->orderBy('id', 'desc')
        ->first();
    expect($log)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// T-B03: destination management (DestinationResource CRUD)
// ---------------------------------------------------------------------------

it('creates a destination through the Filament create page (T-B03)', function () {
    actingAs(marketOpsAdmin(['manage_tours' => true]));

    $adminNoFlag = marketOpsAdmin([]);
    expect(DestinationResource::canViewAny())->toBeTrue()
        ->and(DestinationResource::isAuthorized($adminNoFlag))->toBeFalse();

    actingAs($adminNoFlag);
    expect(DestinationResource::canViewAny())->toBeFalse();

    actingAs(marketOpsAdmin(['manage_tours' => true]));

    Livewire::test(CreateDestination::class)
        ->fillForm([
            'name' => 'Tuscan Wine Country',
            'slug' => 'tuscan-wine-country',
            'description' => 'Vineyards and hilltop towns across Chianti.',
            'display_order' => 2,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $destination = Destination::where('slug', 'tuscan-wine-country')->first();
    expect($destination)->not->toBeNull()
        ->and($destination->name)->toBe('Tuscan Wine Country')
        ->and($destination->is_active)->toBeTrue();
});

it('edits and deactivates a destination through the Filament edit page (T-B03)', function () {
    actingAs(marketOpsAdmin(['manage_tours' => true]));

    $destination = Destination::create([
        'name' => 'Amalfi Coast',
        'slug' => 'amalfi-coast',
        'description' => 'Cliffside villages.',
        'display_order' => 1,
        'is_active' => true,
    ]);

    Livewire::test(EditDestination::class, ['record' => $destination->getRouteKey()])
        ->assertSuccessful()
        ->fillForm([
            'name' => 'Amalfi Coast & Capri',
            'slug' => 'amalfi-coast-capri',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $destination->refresh();
    expect($destination->name)->toBe('Amalfi Coast & Capri')
        ->and($destination->slug)->toBe('amalfi-coast-capri')
        ->and($destination->is_active)->toBeFalse();
});
