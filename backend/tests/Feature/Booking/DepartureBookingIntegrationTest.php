<?php

use App\Domains\Booking\Actions\CancelBookingAction;
use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\VoucherService;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Listeners\ConfirmBookingOnPayment;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\Spec019TranslationProcess;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $this->travelerUser = User::factory()->traveler()->create();

    $category = Category::firstOrCreate(['slug' => 's5-booking-cat'], ['name' => 'Booking Cat']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 's5-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '10:00:00',
        'start_date' => Carbon::now()->subDays(10)->toDateString(),
        'end_date' => Carbon::now()->addDays(90)->toDateString(),
        'capacity' => 10,
    ]);

    $this->option = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Gondola Ride',
        'price_amount' => 5000,
        'group_size_max' => 6,
        'start_time' => '10:00:00',
        'status' => 'active',
    ]);

    // Tiered pricing:
    // 1-2 participants: €50 (5000c)
    // 3-4 participants: €45 (4500c)
    // 5-6 participants: €40 (4000c)
    $this->tier1 = PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'name' => 'Solo/Duo',
        'min_participants' => 1,
        'max_participants' => 2,
        'price_amount' => 5000,
        'price' => '50.00',
        'currency' => 'EUR',
    ]);

    $this->tier2 = PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'name' => 'Small Group',
        'min_participants' => 3,
        'max_participants' => 4,
        'price_amount' => 4500,
        'price' => '45.00',
        'currency' => 'EUR',
    ]);

    $this->tier3 = PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'name' => 'Full Gondola',
        'min_participants' => 5,
        'max_participants' => 6,
        'price_amount' => 4000,
        'price' => '40.00',
        'currency' => 'EUR',
    ]);

    // Explicit departure in the future
    $this->departure = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => Carbon::now()->addDays(5)->setTime(10, 0, 0)->utc(),
        'timezone' => 'Europe/Rome',
        'local_date' => Carbon::now()->addDays(5)->toDateString(),
        'local_time' => '10:00:00',
        'capacity' => 6,
        'booking_cutoff_hours' => 2,
        'status' => 'active',
    ]);

    // Mock payment intent action so Stripe is not contacted
    $this->mock(CreatePaymentIntentAction::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturn('pi_test_secret_123');
    });
});

test('it creates a booking for an explicit departure with independent capacity and tiered pricing', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 3,
        locale: 'en',
        idempotencyKey: '00000000-0000-0000-0000-' . str_pad((string) rand(1, 999999), 12, '0', STR_PAD_LEFT),
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 4500,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    $action = app(CreateBookingAction::class);
    $result = $action->execute($dto);

    $bookingData = $result['data'];
    expect($bookingData['reference'])->not->toBeEmpty()
        ->and($bookingData['departure']['id'])->toBe($this->departure->id)
        ->and($bookingData['pricing_snapshot']['tier_id'])->toBe($this->tier2->id)
        ->and($bookingData['pricing_snapshot']['unit_price_cents'])->toBe(4500)
        ->and($bookingData['pricing_snapshot']['total_price_cents'])->toBe(13500);

    // Departure capacity accounting: 3 seats consumed out of 6 -> 3 remaining
    expect($this->departure->fresh()->reservedCapacity())->toBe(3)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(3);
});

test('it enforces independent capacity (D4) and refuses overbooking', function () {
    $action = app(CreateBookingAction::class);

    // 1st booking: 4 seats
    $dto1 = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 4,
        locale: 'en',
        idempotencyKey: '11111111-1111-1111-1111-111111111111',
        travelerId: $this->travelerUser->id,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );
    $action->execute($dto1);

    expect($this->departure->fresh()->remainingCapacity())->toBe(2);

    // 2nd booking: requested 3 seats, but only 2 remain -> 409 Conflict
    $dto2 = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 3,
        locale: 'en',
        idempotencyKey: '22222222-2222-2222-2222-222222222222',
        travelerId: $this->travelerUser->id,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    expect(fn () => $action->execute($dto2))->toThrow(ConflictHttpException::class);
});

test('it releases expired pending_payment holds on departures (D4 rule 5)', function () {
    // Create an expired pending booking on the departure
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->travelerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $this->departure->id,
        'tour_date' => $this->departure->local_date,
        'start_time' => '10:00:00',
        'participant_count' => 4,
        'price_per_person' => 4500,
        'total_price' => 18000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'pending_expires_at' => Carbon::now()->subMinutes(10), // Expired!
        'idempotency_key' => 'exp-idemp-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    // Expired pending hold does not consume capacity
    expect($this->departure->fresh()->reservedCapacity())->toBe(0)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(6);
});

test('it handles booking idempotency replay and prevents payload modification conflict', function () {
    $action = app(CreateBookingAction::class);
    $idempotencyKey = '33333333-3333-3333-3333-333333333333';

    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 2,
        locale: 'en',
        idempotencyKey: $idempotencyKey,
        travelerId: $this->travelerUser->id,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    $res1 = $action->execute($dto);
    expect($res1['is_retry'])->toBeFalse();

    // Idempotent retry: returns same booking
    $res2 = $action->execute($dto);
    expect($res2['is_retry'])->toBeTrue()
        ->and($res2['data']['reference'])->toBe($res1['data']['reference']);

    // Replay with different participant count returns 409 Conflict
    $dtoConflict = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 4, // Changed from 2 to 4
        locale: 'en',
        idempotencyKey: $idempotencyKey,
        travelerId: $this->travelerUser->id,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    expect(fn () => $action->execute($dtoConflict))->toThrow(ConflictHttpException::class);
});

test('voucher freshness incorporates departure ID', function () {
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->travelerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $this->departure->id,
        'tour_date' => $this->departure->local_date,
        'start_time' => '10:00:00',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => 'vouch-idemp-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    $voucherService = app(VoucherService::class);
    $hashWithDeparture = $voucherService->contentHash($booking);

    // If departure ID changes, hash differs
    $booking->tour_departure_id = 9999;
    $hashChanged = $voucherService->contentHash($booking);

    expect($hashWithDeparture)->not->toBe($hashChanged);
});

test('public booking endpoint validates departure capacity and prevents overbooking', function () {
    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    // Capacity is 6. Traveler 1 books 4 spots.
    $res1 = $this->actingAs($traveler1)
        ->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => '44444444-4444-4444-4444-444444444444',
        ])
        ->postJson('/api/public/bookings', [
            'tour_slug' => $this->tour->slug,
            'tour_date' => $this->departure->local_date,
            'tour_option_id' => $this->option->id,
            'tour_departure_id' => $this->departure->id,
            'participant_count' => 4,
            'locale' => 'en',
            'page_load_price' => 4500,
        ]);

    $res1->assertStatus(201)
        ->assertJsonPath('data.departure.id', $this->departure->id);

    // Only 2 spots left. Traveler 2 requests 3 spots -> 409 Conflict
    $res2 = $this->actingAs($traveler2)
        ->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => '55555555-5555-5555-5555-555555555555',
        ])
        ->postJson('/api/public/bookings', [
            'tour_slug' => $this->tour->slug,
            'tour_date' => $this->departure->local_date,
            'tour_option_id' => $this->option->id,
            'tour_departure_id' => $this->departure->id,
            'participant_count' => 3,
            'locale' => 'en',
            'page_load_price' => 4500,
        ]);

    $res2->assertStatus(409);
});

test('it flags price drift on tiered departure booking when page_load_price is stale', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 3,
        locale: 'en',
        idempotencyKey: '66666666-6666-6666-6666-666666666666',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 5000, // Stale tier-1 price; tier-2 (3..4) is 4500c
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    $result = app(CreateBookingAction::class)->execute($dto);

    expect($result['price_changed'])->toBeTrue()
        ->and($result['data']['pricing']['price_per_person']['amount'])->toBe(4500)
        ->and($result['data']['pricing']['total']['amount'])->toBe(13500)
        ->and($result['data']['pricing_snapshot']['unit_price_cents'])->toBe(4500)
        ->and($result['data']['pricing_snapshot']['total_price_cents'])->toBe(13500);
});

test('cancelling a confirmed departure booking issues refund for tiered total and releases departure capacity', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 3,
        locale: 'en',
        idempotencyKey: '77777777-7777-7777-7777-777777777777',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 4500,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    $created = app(CreateBookingAction::class)->execute($dto);
    $reference = $created['data']['reference'];

    $booking = Booking::where('reference', $reference)->firstOrFail();
    $booking->update([
        'status' => Booking::STATUS_CONFIRMED,
        'payment_confirmed_at' => now(),
        'stripe_payment_intent_id' => 'pi_dep_refund_test_123',
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_dep_refund_test_123',
        'type' => 'charge',
        'amount' => 13500,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    expect($this->departure->fresh()->reservedCapacity())->toBe(3)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(3);

    $this->mock(PaymentGateway::class, function ($mock): void {
        $mock->shouldReceive('refund')
            ->once()
            ->with('pi_dep_refund_test_123', Mockery::any())
            ->andReturn('re_dep_test_123');
    });

    app(CancelBookingAction::class)->execute($reference, $this->travelerUser->id, 'Plan changed');

    $booking->refresh();
    $refund = Payment::where('booking_id', $booking->id)
        ->where('type', 'refund')
        ->first();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($refund)->not->toBeNull()
        ->and($refund->status)->toBe('refunded')
        ->and($refund->amount)->toBe(13500)
        ->and($refund->stripe_refund_id)->toBe('re_dep_test_123')
        ->and($this->departure->fresh()->reservedCapacity())->toBe(0)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(6);
});

test('it rejects departure booking creation with 404 when owning partner is ineligible', function () {
    $this->partner->update(['is_active' => false]);

    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 2,
        locale: 'en',
        idempotencyKey: '88888888-8888-8888-8888-888888888888',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 5000,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    expect(fn () => app(CreateBookingAction::class)->execute($dto))
        ->toThrow(NotFoundHttpException::class);
});

test('it rejects departure booking when tour_date does not match departure local_date', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: Carbon::parse($this->departure->local_date)->addDay()->toDateString(),
        participantCount: 2,
        locale: 'en',
        idempotencyKey: '99999999-9999-9999-9999-999999999999',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 5000,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    expect(fn () => app(CreateBookingAction::class)->execute($dto))
        ->toThrow(ValidationException::class);
});

test('real PostgreSQL concurrency prevents overbooking on the final seat of an explicit departure', function () {
    $previousDefault = DB::getDefaultConnection();
    config()->set('database.connections.egm005_dep_race', config('database.connections.pgsql'));
    DB::setDefaultConnection('egm005_dep_race');

    $partnerUser = $partner = $traveler1 = $traveler2 = $category = $tour = $option = $departure = null;
    $dir = sys_get_temp_dir() . '/egm005-dep-race-' . uniqid();
    mkdir($dir, 0700);
    $ready1 = $dir . '/ready1';
    $ready2 = $dir . '/ready2';
    $out1 = $dir . '/out1';
    $err1 = $dir . '/err1';
    $out2 = $dir . '/out2';
    $err2 = $dir . '/err2';
    $goFile = $dir . '/go';
    $w1 = $w2 = null;

    try {
        $identity = DB::selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe('bookly_test')->and($identity->usr)->toBe('bookly_test');

        $partnerUser = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
        ]);
        $traveler1 = User::factory()->traveler()->create();
        $traveler2 = User::factory()->traveler()->create();
        $category = Category::create(['slug' => 'egm005-race-' . uniqid(), 'name' => 'Departure Race Cat']);
        $tour = Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'egm005-race-tour-' . uniqid(),
            'source_locale' => 'en',
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => 'published',
        ]);
        $localDate = Carbon::now()->addDays(5)->toDateString();
        AvailabilityRule::create([
            'tour_id' => $tour->id,
            'rule_type' => 'recurring',
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'start_time' => '10:00:00',
            'start_date' => Carbon::now()->subDays(1)->toDateString(),
            'end_date' => Carbon::now()->addDays(30)->toDateString(),
            'capacity' => 10,
        ]);
        $option = TourOption::create([
            'tour_id' => $tour->id,
            'title' => 'Race Slot',
            'price_amount' => 5000,
            'group_size_max' => 3,
            'start_time' => '10:00:00',
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'status' => 'active',
        ]);
        PricingTier::create([
            'tour_id' => $tour->id,
            'tour_option_id' => $option->id,
            'name' => 'Standard',
            'min_participants' => 1,
            'max_participants' => 3,
            'price_amount' => 5000,
            'price' => '50.00',
            'currency' => 'EUR',
        ]);
        $departure = TourDeparture::create([
            'tour_id' => $tour->id,
            'tour_option_id' => $option->id,
            'starts_at' => Carbon::now()->addDays(5)->setTime(10, 0, 0)->utc(),
            'timezone' => 'Europe/Rome',
            'local_date' => $localDate,
            'local_time' => '10:00:00',
            'capacity' => 3,
            'booking_cutoff_hours' => 2,
            'status' => TourDeparture::STATUS_ACTIVE,
        ]);

        // Seed 1 existing confirmed booking consuming 1 of 3 seats -> 2 remain.
        Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $partnerUser->id,
            'tour_id' => $tour->id,
            'tour_option_id' => $option->id,
            'tour_departure_id' => $departure->id,
            'tour_date' => $localDate,
            'start_time' => '10:00:00',
            'participant_count' => 1,
            'price_per_person' => 5000,
            'total_price' => 5000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_CONFIRMED,
            'idempotency_key' => 'seed-race-' . uniqid(),
            'cancellation_policy' => 'Standard',
            'cancellation_window_hours' => 24,
            'locale' => 'en',
        ]);

        $workerPath = base_path('tests/Support/DepartureBookingWorker.php');
        $w1 = Spec019TranslationProcess::spawn(
            $workerPath,
            [$tour->slug, (string) $option->id, (string) $departure->id, $localDate, (string) $traveler1->id, 'aaaaaaaa-aaaa-4aaa-8aaa-' . str_pad((string) rand(1, 999999999999), 12, '0', STR_PAD_LEFT), '2', $ready1, $goFile],
            $out1,
            $err1,
            'Departure race worker 1',
        );
        $w2 = Spec019TranslationProcess::spawn(
            $workerPath,
            [$tour->slug, (string) $option->id, (string) $departure->id, $localDate, (string) $traveler2->id, 'bbbbbbbb-bbbb-4bbb-8bbb-' . str_pad((string) rand(1, 999999999999), 12, '0', STR_PAD_LEFT), '2', $ready2, $goFile],
            $out2,
            $err2,
            'Departure race worker 2',
        );

        Spec019TranslationProcess::waitForFile($ready1, 90, 'Departure race worker 1 ready', $err1, $w1);
        Spec019TranslationProcess::waitForFile($ready2, 90, 'Departure race worker 2 ready', $err2, $w2);
        Spec019TranslationProcess::signal($goFile);

        expect($w1->waitExit(60))->toBe(0)
            ->and($w2->waitExit(60))->toBe(0);

        $raw1 = trim((string) file_get_contents($out1));
        $raw2 = trim((string) file_get_contents($out2));
        expect($raw1)->toMatch('/^(booked:|conflict:409)/', 'Worker 1 stderr: ' . (string) @file_get_contents($err1))
            ->and($raw2)->toMatch('/^(booked:|conflict:409)/', 'Worker 2 stderr: ' . (string) @file_get_contents($err2));

        $outcomes = [explode(':', $raw1)[0], explode(':', $raw2)[0]];
        sort($outcomes);

        expect($outcomes)->toBe(['booked', 'conflict']);

        $freshDeparture = TourDeparture::findOrFail($departure->id);
        expect($freshDeparture->reservedCapacity())->toBe(3)
            ->and($freshDeparture->remainingCapacity())->toBe(0);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $w1?->close();
        $w2?->close();
        foreach ([$ready1, $ready1 . '.spawned', $ready2, $ready2 . '.spawned', $out1, $err1, $out2, $err2, $goFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
        if ($tour !== null) {
            $bookingIds = DB::table('bookings')->where('tour_id', $tour->id)->pluck('id');
            if ($bookingIds->isNotEmpty()) {
                DB::table('booking_audit_logs')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('payments')->whereIn('booking_id', $bookingIds)->delete();
            }
            DB::table('bookings')->where('tour_id', $tour->id)->delete();
            DB::table('tour_departures')->where('tour_id', $tour->id)->delete();
            DB::table('pricing_tiers')->where('tour_id', $tour->id)->delete();
            DB::table('tour_options')->where('tour_id', $tour->id)->delete();
            DB::table('availability_rules')->where('tour_id', $tour->id)->delete();
            $tour->delete();
        }
        $partner?->delete();
        $partnerUser?->delete();
        $traveler1?->delete();
        $traveler2?->delete();
        $category?->delete();
        DB::setDefaultConnection($previousDefault);
        DB::purge('egm005_dep_race');
    }
});

test('it rejects omitting tour_departure_id when the selected option has active departures', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 2,
        locale: 'en',
        idempotencyKey: 'aaaaaaaa-1111-4111-8111-111111111111',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 5000,
        tourOptionId: $this->option->id,
        tourDepartureId: null,
    );

    expect(fn () => app(CreateBookingAction::class)->execute($dto))
        ->toThrow(ValidationException::class);
});

test('late payment webhook does not resurrect an expired departure hold after seats are taken', function () {
    // Booking 1 held 4 of 6 seats, then expired 5 minutes ago
    $expiredPending = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->travelerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $this->departure->id,
        'tour_date' => $this->departure->local_date,
        'start_time' => '10:00:00',
        'participant_count' => 4,
        'price_per_person' => 4500,
        'total_price' => 18000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'pending_expires_at' => Carbon::now()->subMinutes(5),
        'idempotency_key' => 'late-webhook-exp-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
        'stripe_payment_intent_id' => 'pi_late_exp_123',
    ]);

    $payment = Payment::create([
        'booking_id' => $expiredPending->id,
        'stripe_payment_intent_id' => 'pi_late_exp_123',
        'type' => 'charge',
        'amount' => 18000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    // Meanwhile Booking 2 took 4 seats on the same departure (only 2 remain)
    Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->partnerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $this->departure->id,
        'tour_date' => $this->departure->local_date,
        'start_time' => '10:00:00',
        'participant_count' => 4,
        'price_per_person' => 4500,
        'total_price' => 18000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => 'late-webhook-winner-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    app(ConfirmBookingOnPayment::class)->handle(new PaymentSucceeded($payment, $expiredPending));

    expect($expiredPending->fresh()->status)->toBe(Booking::STATUS_EXPIRED)
        ->and($this->departure->fresh()->reservedCapacity())->toBe(4)
        ->and($this->departure->fresh()->remainingCapacity())->toBe(2);
});

test('canCancel uses authoritative departure UTC starts_at for non-UTC timezones', function () {
    // Starts 23h from now in UTC, projected to Asia/Tokyo (+9h -> appears 32h ahead in naive UTC)
    $startsAtUtc = Carbon::now('UTC')->addHours(23);
    $tokyoLocal = $startsAtUtc->copy()->setTimezone('Asia/Tokyo');

    $tokyoDep = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'starts_at' => $startsAtUtc,
        'timezone' => 'Asia/Tokyo',
        'local_date' => $tokyoLocal->format('Y-m-d'),
        'local_time' => $tokyoLocal->format('H:i:s'),
        'capacity' => 6,
        'booking_cutoff_hours' => 2,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->travelerUser->id,
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option->id,
        'tour_departure_id' => $tokyoDep->id,
        'tour_date' => $tokyoDep->local_date,
        'start_time' => $tokyoDep->local_time,
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => 'tokyo-tz-' . uniqid(),
        'cancellation_policy' => 'Standard',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);

    expect($booking->canCancel())->toBeFalse();
});

test('it allows booking a same-day departure before cutoff and respects capacity override above option max', function () {
    Carbon::setTestNow(Carbon::now('UTC')->setTime(10, 0, 0));
    try {
        // Extend tier3 so 8 participants is covered cleanly
        $this->tier3->update(['max_participants' => null]);

        $sameDayDep = TourDeparture::create([
            'tour_id' => $this->tour->id,
            'tour_option_id' => $this->option->id,
            'starts_at' => Carbon::now('UTC')->addHours(4),
            'timezone' => 'UTC',
            'local_date' => Carbon::now('UTC')->toDateString(),
            'local_time' => '14:00:00',
            'capacity' => 12, // Capacity override higher than option.group_size_max (6)
            'booking_cutoff_hours' => 2,
            'status' => TourDeparture::STATUS_ACTIVE,
        ]);

        $dto = new CreateBookingDTO(
            tourSlug: $this->tour->slug,
            tourDate: $sameDayDep->local_date,
            participantCount: 8,
            locale: 'en',
            idempotencyKey: 'bbbbbbbb-2222-4222-8222-222222222222',
            travelerId: $this->travelerUser->id,
            pageLoadPrice: 4000,
            tourOptionId: $this->option->id,
            tourDepartureId: $sameDayDep->id,
        );

        $result = app(CreateBookingAction::class)->execute($dto);

        expect($result['data']['participant_count'])->toBe(8)
            ->and($result['data']['departure']['id'])->toBe($sameDayDep->id)
            ->and($sameDayDep->fresh()->reservedCapacity())->toBe(8);
    } finally {
        Carbon::setTestNow();
    }
});

test('it calculates integer-only tiered pricing snapshot across 4 participants without false drift', function () {
    $dto = new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $this->departure->local_date,
        participantCount: 4,
        locale: 'en',
        idempotencyKey: 'cccccccc-3333-4333-8333-333333333333',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 4500,
        tourOptionId: $this->option->id,
        tourDepartureId: $this->departure->id,
    );

    $result = app(CreateBookingAction::class)->execute($dto);
    expect($result['price_changed'])->toBeFalse();

    $booking = Booking::where('reference', $result['data']['reference'])->firstOrFail();
    expect($booking->price_per_person)->toBe(4500)
        ->and($booking->total_price)->toBe(18000)
        ->and($booking->pricing_tier_id)->toBe($this->tier2->id)
        ->and($booking->pricing_snapshot['unit_price_cents'])->toBe(4500)
        ->and($booking->pricing_snapshot['total_price_cents'])->toBe(18000)
        ->and($booking->pricing_snapshot['tier_name'])->toBe('Small Group');
});

test('option without option-level tiers uses option base_price instead of tour-level tiers at checkout (no false drift)', function () {
    PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => null,
        'name' => 'Tour Standard Tier',
        'min_participants' => 1,
        'max_participants' => null,
        'price_amount' => 8900,
        'price' => '89.00',
        'currency' => 'EUR',
    ]);

    $untieredOption = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Direct Express Slot',
        'price_amount' => 5900,
        'group_size_max' => 6,
        'start_time' => '15:00:00',
        'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        'status' => TourOption::STATUS_ACTIVE,
    ]);

    $untieredDep = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $untieredOption->id,
        'starts_at' => Carbon::now()->addDays(6)->setTime(15, 0, 0)->utc(),
        'timezone' => 'Europe/Rome',
        'local_date' => Carbon::now()->addDays(6)->toDateString(),
        'local_time' => '15:00:00',
        'capacity' => 6,
        'booking_cutoff_hours' => 2,
        'status' => TourDeparture::STATUS_ACTIVE,
    ]);

    $result = app(CreateBookingAction::class)->execute(new CreateBookingDTO(
        tourSlug: $this->tour->slug,
        tourDate: $untieredDep->local_date,
        participantCount: 2,
        locale: 'en',
        idempotencyKey: 'dddddddd-4444-4444-8444-444444444444',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 5900,
        tourOptionId: $untieredOption->id,
        tourDepartureId: $untieredDep->id,
    ));

    expect($result['price_changed'])->toBeFalse()
        ->and($result['data']['pricing_snapshot']['mode'])->toBe('base_price')
        ->and($result['data']['pricing_snapshot']['unit_price_cents'])->toBe(5900)
        ->and($result['data']['pricing_snapshot']['total_price_cents'])->toBe(11800);
});

test('option-less tour with tour-level pricing tiers calculates tiered cents without false drift', function () {
    $category = Category::firstOrCreate(['slug' => 's5-booking-cat'], ['name' => 'Booking Cat']);
    $legacyTour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'legacy-tiered-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Florence, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 8000,
        'status' => 'published',
    ]);
    AvailabilityRule::create([
        'tour_id' => $legacyTour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '11:00:00',
        'start_date' => Carbon::now()->subDay()->toDateString(),
        'end_date' => Carbon::now()->addDays(60)->toDateString(),
        'capacity' => 8,
    ]);
    PricingTier::create([
        'tour_id' => $legacyTour->id,
        'tour_option_id' => null,
        'name' => 'Solo/Duo',
        'min_participants' => 1,
        'max_participants' => 2,
        'price_amount' => 8000,
        'price' => '80.00',
        'currency' => 'EUR',
    ]);
    $groupTier = PricingTier::create([
        'tour_id' => $legacyTour->id,
        'tour_option_id' => null,
        'name' => 'Legacy Group',
        'min_participants' => 3,
        'max_participants' => 8,
        'price_amount' => 6000,
        'price' => '60.00',
        'currency' => 'EUR',
    ]);

    $result = app(CreateBookingAction::class)->execute(new CreateBookingDTO(
        tourSlug: $legacyTour->slug,
        tourDate: Carbon::now()->addDays(4)->toDateString(),
        participantCount: 3,
        locale: 'en',
        idempotencyKey: 'eeeeeeee-5555-4555-8555-555555555555',
        travelerId: $this->travelerUser->id,
        pageLoadPrice: 6000,
    ));

    expect($result['price_changed'])->toBeFalse();
    $booking = Booking::where('reference', $result['data']['reference'])->firstOrFail();
    expect($booking->price_per_person)->toBe(6000)
        ->and($booking->total_price)->toBe(18000)
        ->and($booking->pricing_tier_id)->toBe($groupTier->id)
        ->and($booking->pricing_snapshot['tier_name'])->toBe('Legacy Group');
});
