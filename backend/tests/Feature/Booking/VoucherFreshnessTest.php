<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\VoucherService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Spec 014 (FR-018, FR-019, SC-008, R3): voucher freshness via a content hash
 * over voucher-relevant fields. getOrGenerate regenerates only when the file is
 * missing, the stored hash is null, or the stored hash differs from the
 * current content hash. Status is intentionally EXCLUDED so confirmed→completed
 * does NOT regenerate (SC-008).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-04 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1]);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'freshness-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Freshness Test Tour',
        'description' => 'Desc',
        'highlights' => ['h'],
        'inclusions' => ['i'],
        'exclusions' => ['e'],
        'meeting_point' => 'Central station',
        'cancellation_policy' => 'Free 24h before',
    ]);
    $this->service = app(VoucherService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeFreshnessBooking(TestCase $scope, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'reference' => Booking::generateReference(),
        'traveler_id' => $scope->traveler->id,
        'tour_id' => $scope->tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free 24h before',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ], $overrides));
}

function freshnessVoucherPath(Booking $booking): string
{
    return storage_path("app/vouchers/voucher-{$booking->reference}.pdf");
}

it('generates a PDF and records the freshness hash on the booking', function () {
    $booking = makeFreshnessBooking($this);

    expect(file_exists(freshnessVoucherPath($booking)))->toBeFalse();

    $path = $this->service->generate($booking);

    expect(file_exists($path))->toBeTrue()
        ->and($booking->fresh()->voucher_generated_at)->not->toBeNull()
        ->and($booking->fresh()->voucher_content_hash)->not->toBeNull()
        ->and($booking->fresh()->voucher_content_hash)->toHaveLength(64); // sha256 hex

    @unlink($path);
});

it('reuses the cached PDF when the content hash is unchanged', function () {
    $booking = makeFreshnessBooking($this);

    $first = $this->service->getOrGenerate($booking);
    $firstMtime = filemtime($first);
    $hashAfterFirst = $booking->fresh()->voucher_content_hash;

    // Subsequent call with identical content MUST reuse the cached file.
    Carbon::setTestNow('2026-07-04 01:00:00'); // advance clock
    $second = $this->service->getOrGenerate($booking);

    expect($second)->toBe($first)
        ->and(filemtime($second))->toBe($firstMtime) // same file, not rewritten
        ->and($booking->fresh()->voucher_content_hash)->toBe($hashAfterFirst);

    @unlink($first);
});

it('regenerates when a voucher-relevant field changes (tour_date)', function () {
    $booking = makeFreshnessBooking($this);
    $first = $this->service->getOrGenerate($booking);
    $firstHash = $booking->fresh()->voucher_content_hash;
    $firstSize = filesize($first);

    // Change a hashed field → content hash differs → regenerate.
    $booking->tour_date = '2026-09-01';
    $booking->save();

    $second = $this->service->getOrGenerate($booking);

    expect($second)->toBe($first) // same path
        ->and($booking->fresh()->voucher_content_hash)->not->toBe($firstHash);
    // A new PDF was written (filesize may match, so assert the hash changed).
    expect(file_exists($second))->toBeTrue();

    @unlink($first);
});

it('regenerates when participant_count changes', function () {
    $booking = makeFreshnessBooking($this);
    $first = $this->service->getOrGenerate($booking);
    $firstHash = $booking->fresh()->voucher_content_hash;

    $booking->participant_count = 5;
    $booking->save();

    $this->service->getOrGenerate($booking);

    expect($booking->fresh()->voucher_content_hash)->not->toBe($firstHash);

    @unlink($first);
});

it('does NOT regenerate on a status-only change (confirmed → completed) (SC-008)', function () {
    $booking = makeFreshnessBooking($this, ['status' => 'confirmed']);
    $first = $this->service->getOrGenerate($booking);
    $firstHash = $booking->fresh()->voucher_content_hash;
    $firstMtime = filemtime($first);

    // Status-only change: the content hash excludes status, so the cached
    // voucher MUST be reused (no regeneration, no new mtime).
    $booking->status = 'completed';
    $booking->save();

    Carbon::setTestNow('2026-07-04 02:00:00');
    $second = $this->service->getOrGenerate($booking);

    expect($second)->toBe($first)
        ->and(filemtime($second))->toBe($firstMtime)
        ->and($booking->fresh()->voucher_content_hash)->toBe($firstHash);

    @unlink($first);
});

it('regenerates when the cached PDF file is missing but the hash is stored', function () {
    $booking = makeFreshnessBooking($this);
    $first = $this->service->getOrGenerate($booking);
    $hash = $booking->fresh()->voucher_content_hash;

    // Simulate the file being purged (e.g. storage cleanup) while the booking
    // still carries a valid hash — getOrGenerate MUST regenerate the file.
    @unlink($first);
    expect(file_exists($first))->toBeFalse();

    $second = $this->service->getOrGenerate($booking);
    expect(file_exists($second))->toBeTrue()
        ->and($booking->fresh()->voucher_content_hash)->toBe($hash); // hash unchanged

    @unlink($second);
});

it('regenerates when the stored hash is null', function () {
    $booking = makeFreshnessBooking($this);
    $first = $this->service->getOrGenerate($booking);

    // Wipe the stored hash (e.g. legacy row) but keep the file.
    $booking->forceFill(['voucher_content_hash' => null, 'voucher_generated_at' => null])->save();

    $second = $this->service->getOrGenerate($booking);
    expect($booking->fresh()->voucher_content_hash)->not->toBeNull();

    @unlink($second);
});
