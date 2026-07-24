<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Jobs\ExpirePendingBookingsJob;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('expires pending payment bookings older than 15 minutes', function () {
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'expire-test-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $freshBooking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->subMinutes(16),
    ]);

    $staleBooking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->subMinutes(16),
    ]);

    // Dispatch the job synchronously
    (new ExpirePendingBookingsJob)->handle();

    $freshBooking->refresh();
    $staleBooking->refresh();

    expect($freshBooking->status)->toBe(Booking::STATUS_EXPIRED);
    expect($staleBooking->status)->toBe(Booking::STATUS_EXPIRED);
});

it('does not expire bookings that are still within the 15-minute window', function () {
    $category = Category::firstOrCreate(['slug' => 'test2'], ['name' => 'Test 2']);
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'expire-test2-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->subMinutes(10),
    ]);

    (new ExpirePendingBookingsJob)->handle();

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_PENDING_PAYMENT);
});
