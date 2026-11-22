<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Spec019TranslationProcess;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = makePartner();
    $this->traveler = User::factory()->traveler()->create();
    $category = Category::create(['slug' => 'owner-bookings', 'name' => 'Owner bookings']);
    $this->tour = Tour::create([
        'partner_id' => $this->owner->id,
        'category_id' => $category->id,
        'slug' => 'owner-booking-' . bin2hex(random_bytes(6)),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    addAvailabilityRule($this->tour);
    addTranslation($this->tour, 'en', 'Approved booking tour');
    $this->payload = [
        'tour_slug' => $this->tour->slug,
        'tour_date' => now()->addDays(5)->toDateString(),
        'participant_count' => 1,
        'locale' => 'en',
    ];
    $this->headers = [
        'Authorization' => 'Bearer ' . $this->traveler->createToken('test')->plainTextToken,
        'Idempotency-Key' => Str::uuid()->toString(),
    ];
    // Only the external payment provider is deterministic; the real booking,
    // capacity, transactional and payment domain actions remain exercised.
    $this->mock(StripeService::class)->shouldReceive('createPaymentIntent')
        ->andReturn('pi_owner_test_secret_local');
});

it('rejects new bookings independently when a published tours owner is ineligible', function (string $status, bool $active, string $locale) {
    DB::table('partners')->where('id', $this->owner->id)->update([
        'onboarding_status' => $status,
        'is_active' => $active,
    ]);
    expect($this->tour->fresh()->status)->toBe('published');

    $this->postJson('/api/public/bookings', array_replace($this->payload, ['locale' => $locale]), $this->headers)
        ->assertNotFound()->assertJsonMissingPath('data');
    expect(Booking::where('tour_id', $this->tour->id)->count())->toBe(0)
        ->and(Payment::count())->toBe(0);
})->with([
    'pending' => ['pending', true],
    'rejected' => ['rejected', true],
    'suspended' => ['suspended', false],
    'inactive' => ['approved', false],
    'legacy complete' => ['complete', true],
])->with(['en', 'es', 'it']);

it('preserves an authorized same-request retry after owner inactivation without creating new effects', function () {
    $created = $this->postJson('/api/public/bookings', $this->payload, $this->headers)->assertCreated();
    $reference = $created->json('data.reference');
    $before = Booking::where('reference', $reference)->firstOrFail()->getAttributes();
    $payments = Payment::count();
    DB::table('partners')->where('id', $this->owner->id)->update(['is_active' => false]);

    $this->postJson('/api/public/bookings', $this->payload, $this->headers)
        ->assertOk()->assertJsonPath('data.reference', $reference);
    expect(Booking::where('tour_id', $this->tour->id)->count())->toBe(1)
        ->and(Booking::where('reference', $reference)->firstOrFail()->getAttributes())->toBe($before)
        ->and(Payment::count())->toBe($payments);
});

it('preserves same-key request validation before ineligible-owner refusal', function (bool $foreignTraveler) {
    $this->postJson('/api/public/bookings', $this->payload, $this->headers)->assertCreated();
    DB::table('partners')->where('id', $this->owner->id)->update(['is_active' => false]);
    $headers = $this->headers;
    $payload = $this->payload;
    if ($foreignTraveler) {
        $headers['Authorization'] = 'Bearer ' . User::factory()->traveler()->create()->createToken('test')->plainTextToken;
        // Separate real requests must not reuse the test application's cached
        // Sanctum guard user from the first traveler (BookingOptions precedent).
        app('auth')->forgetGuards();
    } else {
        $payload['participant_count'] = 2;
    }

    $this->postJson('/api/public/bookings', $payload, $headers)->assertConflict();
    expect(Booking::where('tour_id', $this->tour->id)->count())->toBe(1);
})->with([false, true]);

it('retains confirmed booking history when the owner becomes ineligible', function () {
    $created = $this->postJson('/api/public/bookings', $this->payload, $this->headers)->assertCreated();
    $reference = $created->json('data.reference');
    $booking = Booking::where('reference', $reference)->firstOrFail();
    // Historical confirmed fixture; this does not claim payment confirmation
    // acceptance or change the real confirmation workflow.
    DB::table('bookings')->where('id', $booking->id)->update(['status' => Booking::STATUS_CONFIRMED]);
    $before = $booking->fresh()->getAttributes();
    DB::table('partners')->where('id', $this->owner->id)->update(['is_active' => false]);

    $this->getJson('/api/public/traveler/bookings/' . $reference, $this->headers)
        ->assertOk()->assertJsonPath('data.reference', $reference)->assertJsonPath('data.status', 'confirmed');
    $this->getJson('/api/public/tours/' . $this->tour->slug . '?locale=en')->assertNotFound();
    $newHeaders = array_replace($this->headers, ['Idempotency-Key' => Str::uuid()->toString()]);
    $this->postJson('/api/public/bookings', $this->payload, $newHeaders)->assertNotFound();
    expect($booking->fresh()->getAttributes())->toBe($before)
        ->and(Booking::where('tour_id', $this->tour->id)->count())->toBe(1);
});

it('serializes a new booking behind concurrent owner eligibility or identity changes', function (bool $transfer) {
    $previous = DB::getDefaultConnection();
    config()->set('database.connections.egm003_booking_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('egm003_booking_owner');
    $owner = $traveler = $tour = $category = null;
    $ownerUser = null;
    $replacementOwner = $replacementUser = null;
    $worker = null;
    $directory = sys_get_temp_dir() . '/egm003-booking-owner-' . uniqid();
    mkdir($directory, 0700);
    $arrival = $directory . '/arrival';
    $output = $directory . '/stdout';
    $error = $directory . '/stderr';
    try {
        $identity = DB::selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe('bookly_test')->and($identity->usr)->toBe('bookly_test');
        $owner = TourRevisionFixtures::partner();
        $ownerUser = $owner->user;
        $traveler = User::factory()->traveler()->create();
        $category = Category::create(['slug' => 'booking-owner-race-' . uniqid(), 'name' => 'Booking owner race']);
        $tour = Tour::create([
            'partner_id' => $owner->id, 'category_id' => $category->id,
            'slug' => 'booking-owner-race-' . uniqid(), 'source_locale' => 'en',
            'status' => 'published', 'price_amount' => 5000,
            'location' => 'Rome, Italy', 'duration_minutes' => 120,
            'duration_label' => '2 hours', 'group_size_min' => 1, 'group_size_max' => 10,
        ]);
        $tour->translations()->create([
            'locale' => 'en', 'title' => 'Booking race source', 'description' => 'Booking race source description.',
        ]);
        AvailabilityRule::create([
            'tour_id' => $tour->id, 'rule_type' => 'recurring',
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
        ]);

        if ($transfer) {
            $replacementOwner = TourRevisionFixtures::partner();
            $replacementUser = $replacementOwner->user;
        }
        DB::beginTransaction();
        if ($transfer) {
            DB::table('tours')->where('id', $tour->id)->update(['partner_id' => $replacementOwner->id]);
        } else {
            DB::table('partners')->where('id', $owner->id)->update(['is_active' => false]);
        }
        $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
        $worker = Spec019TranslationProcess::spawn(
            base_path('tests/Support/TourRevisionWorker.php'),
            ['booking ownership', (string) $traveler->id, (string) $tour->id, Str::uuid()->toString(), $arrival],
            $output, $error, 'Booking versus owner eligibility',
        );
        Spec019TranslationProcess::waitForFile($arrival, 60, 'Booking worker arrival', $error);
        $childPid = (int) file_get_contents($arrival);
        $blocked = false;
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline && $worker->isAlive()) {
            $blocked = (int) DB::selectOne(
                'select case when ? = any(pg_blocking_pids(?)) then 1 else 0 end as blocked',
                [$parentPid, $childPid],
            )->blocked === 1;
            if ($blocked) {
                break;
            }
            usleep(20000);
        }
        $diagnostic = 'Booking must wait for the actual owner-row eligibility transaction.';
        if (! $blocked) {
            $diagnostic .= ' Child exit=' . $worker->waitExit(60)
                . '; output=' . trim(file_get_contents($output))
                . '; error=' . trim(file_get_contents($error))
                . '; persisted bookings=' . Booking::where('tour_id', $tour->id)->count();
        }
        expect($blocked)->toBeTrue($diagnostic);
        DB::commit();
        expect($worker->waitExit(60))->toBe(0)
            ->and(trim(file_get_contents($output)))->toBe($transfer ? 'refused:409' : 'refused:404')
            ->and(Booking::where('tour_id', $tour->id)->count())->toBe(0);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $worker?->close();
        if ($tour !== null) {
            $bookings = Booking::where('tour_id', $tour->id)->pluck('id');
            DB::table('payments')->whereIn('booking_id', $bookings)->delete();
            DB::table('booking_audit_logs')->whereIn('booking_id', $bookings)->delete();
            Booking::whereIn('id', $bookings)->delete();
            $tour->delete();
        }
        $owner?->delete();
        $ownerUser?->delete();
        $replacementOwner?->delete();
        $replacementUser?->delete();
        $traveler?->delete();
        $category?->delete();
        DB::setDefaultConnection($previous);
        DB::purge('egm003_booking_owner');
        foreach ([$arrival, $output, $error] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);
    }
})->with(['eligibility change' => [false], 'owner transfer' => [true]]);
