<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.key' => 'pk_test_placeholder']);
});

it('has UPDATED_AT set to null on FinancialLedgerEntry', function () {
    expect(FinancialLedgerEntry::UPDATED_AT)->toBeNull();
});

it('prevents updates on financial ledger entries', function () {
    $category = Category::firstOrCreate(['slug' => 'ledger'], ['name' => 'Ledger Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'ledger-tour-' . uniqid(),
        'location' => 'Amalfi, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 8000,
        'total_price' => 16000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_immut_' . uniqid(),
        'type' => 'charge',
        'amount' => 16000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    $entry = FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 16000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    $originalAmount = $entry->amount;

    // Attempt to update — model event returns false, so save is ignored
    $entry->amount = 99999;
    $result = $entry->save();

    expect($result)->toBeFalse();

    $entry->refresh();
    expect($entry->amount)->toBe($originalAmount);
});

it('prevents deletion of financial ledger entries', function () {
    $category = Category::firstOrCreate(['slug' => 'del'], ['name' => 'Delete Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'del-tour-' . uniqid(),
        'location' => 'Siena, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 7000,
        'total_price' => 7000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_del_' . uniqid(),
        'type' => 'charge',
        'amount' => 7000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    $entry = FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 7000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    $entryId = $entry->id;
    $result = $entry->delete();

    expect($result)->toBeFalse();
    expect(FinancialLedgerEntry::find($entryId))->not->toBeNull();
});

it('can append new entries but not alter old ones', function () {
    $category = Category::firstOrCreate(['slug' => 'append'], ['name' => 'Append Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'append-tour-' . uniqid(),
        'location' => 'Cinque Terre, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 10000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(10)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 10000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $payment1 = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_append1_' . uniqid(),
        'type' => 'charge',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment1->id,
        'entry_type' => 'debit',
        'amount' => 10000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    $payment2 = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_append2_' . uniqid(),
        'stripe_refund_id' => 're_test',
        'type' => 'refund',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'refunded',
    ]);

    FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment2->id,
        'entry_type' => 'credit',
        'amount' => 10000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Refund issued for booking ' . $booking->reference,
    ]);

    $entries = FinancialLedgerEntry::where('booking_id', $booking->id)
        ->orderBy('created_at')
        ->get();

    expect($entries)->toHaveCount(2);
    expect($entries[0]->entry_type)->toBe('debit');
    expect($entries[1]->entry_type)->toBe('credit');
    expect($entries[0]->amount)->toBe(10000);
    expect($entries[1]->amount)->toBe(10000);

    // Verify first entry still cannot be modified
    $first = $entries[0];
    $first->amount = 99999;
    expect($first->save())->toBeFalse();
    $first->refresh();
    expect($first->amount)->toBe(10000);
});
