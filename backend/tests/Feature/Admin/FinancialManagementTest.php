<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Services\FinancialOverviewService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Filament\Pages\FinancialOverviewPage;
use App\Filament\Resources\FinancialLedgerResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ----- fixtures -----

function financeAdmin(array $flags): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh('adminPermission');
}

function financeTour(): Tour
{
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'fin-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
}

function financeBooking(string $status, int $totalPrice): Booking
{
    $traveler = User::factory()->traveler()->create();

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => financeTour()->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => $totalPrice,
        'total_price' => $totalPrice,
        'currency' => 'EUR',
        'status' => $status,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
}

function financePayment(Booking $booking, string $type, int $amount, string $status): Payment
{
    return Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_fin_' . $booking->id . '_' . $type . '_' . uniqid(),
        'stripe_refund_id' => $type === 'refund' ? 're_fin_' . uniqid() : null,
        'type' => $type,
        'amount' => $amount,
        'currency' => 'EUR',
        'status' => $status,
    ]);
}

function financeLedgerEntry(Booking $booking, Payment $payment, string $entryType, string $amount): void
{
    FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => $entryType,
        'amount' => $amount,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => ucfirst($entryType) . ' for booking ' . $booking->reference,
    ]);
}

// ----- T-C01: FinancialOverviewService::summary() -----

it('calculates integer-cent summary totals from bookings, payments and the ledger', function () {
    $grossOne = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $grossTwo = financeBooking(Booking::STATUS_COMPLETED, 6000);
    $cancelled = financeBooking(Booking::STATUS_CANCELLED, 3000); // excluded

    $captureOne = financePayment($grossOne, 'capture', 5000, 'succeeded');
    financeLedgerEntry($grossOne, $captureOne, 'debit', '5000');
    $captureTwo = financePayment($grossTwo, 'capture', 6000, 'succeeded');
    financeLedgerEntry($grossTwo, $captureTwo, 'debit', '6000');

    $refund = financePayment($grossOne, 'refund', 1500, 'succeeded');
    financeLedgerEntry($grossOne, $refund, 'credit', '1500');

    financeLedgerEntry($grossTwo, $captureTwo, 'commission', '2000');

    $failed = financeBooking(Booking::STATUS_PENDING_PAYMENT, 1000);
    financePayment($failed, 'capture', 1000, 'failed');

    $summary = app(FinancialOverviewService::class)->summary();

    expect($summary['currency'])->toBe('EUR')
        ->and($summary['gross_booking_value_cents'])->toBe(11000)
        ->and($summary['captured_payments_cents'])->toBe(11000)
        ->and($summary['refunded_amount_cents'])->toBe(1500)
        ->and($summary['platform_commission_cents'])->toBe(2000)
        ->and($summary['partner_payout_liability_cents'])->toBe(7500)
        ->and($summary['failed_payments_count'])->toBe(1)
        ->and($summary['gross_booking_value'])->toBe('€110.00')
        ->and($summary['partner_payout_liability'])->toBe('€75.00');
});

it('falls back to fifteen percent commission while no ledger commission entries exist', function () {
    $booking = financeBooking(Booking::STATUS_CONFIRMED, 10000);
    $payment = financePayment($booking, 'capture', 10000, 'succeeded');
    financeLedgerEntry($booking, $payment, 'debit', '10000');

    $summary = app(FinancialOverviewService::class)->summary();

    expect($summary['platform_commission_cents'])->toBe(1500)
        ->and($summary['platform_commission'])->toBe('€15.00')
        ->and($summary['partner_payout_liability_cents'])->toBe(8500);
});

it('never reports a negative partner payout liability', function () {
    $booking = financeBooking(Booking::STATUS_CONFIRMED, 2000);
    $payment = financePayment($booking, 'capture', 2000, 'succeeded');
    financeLedgerEntry($booking, $payment, 'debit', '2000');
    financeLedgerEntry($booking, $payment, 'commission', '3000');

    $summary = app(FinancialOverviewService::class)->summary();

    expect($summary['partner_payout_liability_cents'])->toBe(0);
});

// ----- T-C01: reconciliationReport() -----

it('reconciles bookings cleanly when payments and ledger agree', function () {
    $booking = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $payment = financePayment($booking, 'capture', 5000, 'succeeded');
    financeLedgerEntry($booking, $payment, 'debit', '5000');

    $report = app(FinancialOverviewService::class)->reconciliationReport();

    expect($report['total_checked'])->toBe(1)
        ->and($report['matched_count'])->toBe(1)
        ->and($report['discrepancies'])->toBe([]);
});

it('flags a confirmed booking with no succeeded capture payment as a discrepancy', function () {
    financeBooking(Booking::STATUS_CONFIRMED, 4000);

    $report = app(FinancialOverviewService::class)->reconciliationReport();

    expect($report['total_checked'])->toBe(1)
        ->and($report['matched_count'])->toBe(0)
        ->and($report['discrepancies'])->toHaveCount(1)
        ->and($report['discrepancies'][0]['issue'])->toBe('no_succeeded_capture')
        ->and($report['discrepancies'][0]['booking_total_cents'])->toBe(4000)
        ->and($report['discrepancies'][0]['payment_total_cents'])->toBe(0)
        ->and($report['discrepancies'][0]['ledger_net_cents'])->toBe(0)
        ->and($report['discrepancies'][0]['booking_reference'])->toStartWith('BKO-');
});

it('separates matched bookings from discrepancy bookings in one report', function () {
    $matched = financeBooking(Booking::STATUS_CONFIRMED, 3000);
    $matchedPayment = financePayment($matched, 'capture', 3000, 'succeeded');
    financeLedgerEntry($matched, $matchedPayment, 'debit', '3000');

    $refundedOk = financeBooking(Booking::STATUS_CANCELLED, 2500);
    $refundedOkCapture = financePayment($refundedOk, 'capture', 2500, 'succeeded');
    financeLedgerEntry($refundedOk, $refundedOkCapture, 'debit', '2500');
    $refundedOkRefund = financePayment($refundedOk, 'refund', 2500, 'succeeded');
    financeLedgerEntry($refundedOk, $refundedOkRefund, 'credit', '2500');

    $uncaptured = financeBooking(Booking::STATUS_CONFIRMED, 4000);

    $underpaid = financeBooking(Booking::STATUS_COMPLETED, 6000);
    $underpaidPayment = financePayment($underpaid, 'capture', 2000, 'succeeded');
    financeLedgerEntry($underpaid, $underpaidPayment, 'debit', '2000');

    $report = app(FinancialOverviewService::class)->reconciliationReport();

    expect($report['total_checked'])->toBe(4)
        ->and($report['matched_count'])->toBe(2)
        ->and($report['discrepancies'])->toHaveCount(2);

    $byReference = collect($report['discrepancies'])->keyBy('booking_reference');

    expect($byReference[$uncaptured->reference]['issue'])->toBe('no_succeeded_capture')
        ->and($byReference[$underpaid->reference]['issue'])->toBe('payment_total_mismatch')
        ->and($byReference[$underpaid->reference]['payment_total_cents'])->toBe(2000)
        ->and($byReference[$underpaid->reference]['ledger_net_cents'])->toBe(2000);
});

// ----- T-C01: exportLedgerCsv() -----

it('denies a view_finances-only admin without the export flag from the ledger export', function () {
    $admin = financeAdmin(['view_finances' => true]);
    actingAs($admin);

    expect(fn () => app(FinancialOverviewService::class)->exportLedgerCsv($admin))
        ->toThrow(HttpException::class)
        ->and(GovernanceAuditLog::where('action', 'finance.export')->exists())->toBeFalse();
});

it('exports ledger rows as CSV and writes a finance.export audit entry', function () {
    $admin = financeAdmin(['export_financial_reports' => true]);
    actingAs($admin);

    $booking = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $payment = financePayment($booking, 'capture', 5000, 'succeeded');
    $debit = FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 5000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    $csv = app(FinancialOverviewService::class)->exportLedgerCsv($admin);

    $lines = collect(explode("\n", rtrim($csv)))
        ->filter(fn ($line) => $line !== '')
        ->values();

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('booking_reference')
        ->and($lines[1])->toContain((string) $debit->id)
        ->and($lines[1])->toContain($booking->reference)
        ->and($lines[1])->toContain($payment->id)
        ->and($lines[1])->toContain('debit')
        ->and($lines[1])->toContain('5000');

    $audit = GovernanceAuditLog::where('action', 'finance.export')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_type)->toBe('admin')
        ->and($audit->actor_id)->toBe($admin->id)
        ->and($audit->metadata['row_count'])->toBe(1);
});

// ----- T-C02: read-only resources + ledger immutability -----

it('keeps the payments and ledger resources strictly read-only', function () {
    $booking = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $payment = financePayment($booking, 'capture', 5000, 'succeeded');
    $ledgerEntry = FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 5000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    expect(PaymentResource::canCreate())->toBeFalse()
        ->and(PaymentResource::canEdit($payment))->toBeFalse()
        ->and(PaymentResource::canDelete($payment))->toBeFalse()
        ->and(PaymentResource::canDeleteAny())->toBeFalse()
        ->and(FinancialLedgerResource::canCreate())->toBeFalse()
        ->and(FinancialLedgerResource::canEdit($ledgerEntry))->toBeFalse()
        ->and(FinancialLedgerResource::canDelete($ledgerEntry))->toBeFalse()
        ->and(FinancialLedgerResource::canDeleteAny())->toBeFalse();
});

it('enforces financial ledger entry immutability at the model layer', function () {
    $booking = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $payment = financePayment($booking, 'capture', 5000, 'succeeded');
    $ledgerEntry = FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 5000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    $updateResult = $ledgerEntry->update(['amount' => 1]);
    $deleteResult = $ledgerEntry->delete();

    expect($updateResult)->toBeFalse()
        ->and($deleteResult)->toBeFalse()
        ->and(FinancialLedgerEntry::find($ledgerEntry->id)->amount)->toBe(5000)
        ->and(FinancialLedgerEntry::where('id', $ledgerEntry->id)->exists())->toBeTrue();
});

// ----- T-C02: finance page + resource access (403) -----

it('grants finance access to an admin holding view_finances and renders the pages', function () {
    actingAs(financeAdmin(['view_finances' => true]));

    $booking = financeBooking(Booking::STATUS_CONFIRMED, 5000);
    $payment = financePayment($booking, 'capture', 5000, 'succeeded');
    financeLedgerEntry($booking, $payment, 'debit', '5000');

    $this->get('/admin/payments')->assertSuccessful();
    $this->get('/admin/financial-ledger-entries')->assertSuccessful();
    $this->get('/admin/financial-overview-page')->assertSuccessful();

    expect(PaymentResource::canViewAny())->toBeTrue()
        ->and(FinancialLedgerResource::canViewAny())->toBeTrue()
        ->and(FinancialOverviewPage::canAccess())->toBeTrue();
});

it('grants finance access to an admin holding view_all_analytics only', function () {
    actingAs(financeAdmin(['view_all_analytics' => true]));

    expect(PaymentResource::canViewAny())->toBeTrue()
        ->and(FinancialLedgerResource::canViewAny())->toBeTrue()
        ->and(FinancialOverviewPage::canAccess())->toBeTrue();
});

it('returns 403 on the finance routes for an admin without finance flags', function () {
    actingAs(financeAdmin(['manage_bookings' => true]));

    expect(PaymentResource::canViewAny())->toBeFalse()
        ->and(FinancialLedgerResource::canViewAny())->toBeFalse()
        ->and(FinancialOverviewPage::canAccess())->toBeFalse();

    $this->get('/admin/payments')->assertForbidden();
    $this->get('/admin/financial-ledger-entries')->assertForbidden();
    $this->get('/admin/financial-overview-page')->assertForbidden();
});
