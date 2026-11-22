<?php

use App\Domains\Booking\Actions\CancelBookingAction;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Booking\Services\VoucherService;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Domains\Payment\Actions\ProcessRefundAction;
use App\Domains\Payment\Actions\ProcessStripeWebhookAction;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Listeners\NotifyAdminOnPaymentFailure;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\ApplicationFeeCalculator;
use App\Domains\Payment\Services\PaymentConfigurationGuard;
use App\Domains\Payment\Services\StripeService;
use App\Events\BookingEmailDeliveryFailed;
use App\Mail\BookingCancelledMail;
use App\Mail\PartnerBookingCancelledMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pest\Expectation;

use function Pest\Laravel\actingAs;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/*
 * Phase 1 — Payment & Security Hardening regression suite.
 *
 * Guards the five hardening invariants:
 *   1. The Slack webhook URL is a credential and NEVER appears in logs — not
 *      on Slack HTTP 500, not when an exception message embeds the URL.
 *   2. Slack outages never roll back committed business writes, and Slack
 *      HTTP calls never execute inside an open DB transaction.
 *   3. CancelBookingAction performs the Stripe refund OUTSIDE its
 *      transaction and emails after commit; mail failures never roll back.
 *   4. ProcessRefundAction refund coordination is retry-safe and idempotent.
 *   5. The real StripeService sends Idempotency-Key as an HTTP header (never
 *      a body param), enriches intents with metadata for Connect AND
 *      non-Connect charges, and passes Connect refund flags. Plus the
 *      fail-closed staging/production configuration guard, the
 *      deterministic-endpoint environment clamp, and voucher URL propagation.
 *
 * Transaction-level note: RefreshDatabase wraps every test in an ambient
 * transaction, so the ambient level is 1, not 0. "Outside the transaction"
 * therefore means "at the ambient level" — a call made inside the code
 * under test's DB::transaction() would record ambient + 1.
 */

uses(RefreshDatabase::class);

/**
 * Records every raw Stripe HTTP request (method, URL, headers, params) and
 * replays canned JSON responses. Same pattern as StripeConnectAndInvoicingTest's
 * RecordingStripeHttpClient, uniquely named to avoid a redeclare collision.
 */
final class HardeningStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, absUrl: string, headers: array, params: array}> */
    public array $requests = [];

    public function __construct(private array $responses) {}

    public function request($method, $absUrl, $headers, $params, $hasFile)
    {
        $this->requests[] = compact('method', 'absUrl', 'headers', 'params');

        $response = array_shift($this->responses);

        if ($response === null) {
            throw new RuntimeException('Unexpected Stripe HTTP request.');
        }

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, ['request-id' => 'req_hardening']];
    }
}

if (! function_exists('hardeningCaptureLogs')) {
    /**
     * Mock every Log level so each write is captured as
     * "{message} {json-encoded context}" and returned after the scenario runs.
     */
    function hardeningCaptureLogs(callable $scenario): array
    {
        $entries = [];

        $capture = function (...$args) use (&$entries): void {
            $entries[] = ($args[0] ?? '') . ' ' . json_encode($args[1] ?? [], JSON_UNESCAPED_SLASHES);
        };

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            Log::shouldReceive($level)->andReturnUsing($capture);
        }

        $scenario();

        return $entries;
    }
}

if (! function_exists('makeHardeningTour')) {
    function makeHardeningTour(): Tour
    {
        $partner = makePartner();
        $category = Category::firstOrCreate(['slug' => 'hardening'], ['name' => 'Hardening']);

        return Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'hardening-tour-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => 'published',
            'cover_image_url' => null,
        ]);
    }
}

if (! function_exists('makeHardeningConnectPartner')) {
    function makeHardeningConnectPartner(): Partner
    {
        $partnerUser = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => 'approved',
            'is_active' => true,
            'stripe_account_id' => 'acct_hardening_123',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_onboarding_completed' => true,
            'stripe_details_submitted' => true,
        ]);
    }
}

if (! function_exists('makeHardeningConnectTour')) {
    function makeHardeningConnectTour(): Tour
    {
        $partner = makeHardeningConnectPartner();
        $category = Category::firstOrCreate(['slug' => 'hardening'], ['name' => 'Hardening']);

        return Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => 'hardening-connect-' . uniqid(),
            'location' => 'Interlaken, Switzerland',
            'duration_minutes' => 240,
            'duration_label' => '4 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 10000,
            'status' => 'published',
            'cover_image_url' => null,
        ]);
    }
}

if (! function_exists('makeHardeningPaidBooking')) {
    /**
     * A confirmed booking with a succeeded charge payment (and its debit
     * ledger entry) — the precondition for cancellation refunds.
     */
    function makeHardeningPaidBooking(Tour $tour, array $paymentMetadata = [], int $amount = 10000): Booking
    {
        $traveler = User::factory()->traveler()->create();

        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => $tour->id,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 2,
            'price_per_person' => 5000,
            'total_price' => $amount,
            'currency' => 'EUR',
            'status' => Booking::STATUS_CONFIRMED,
            'idempotency_key' => Str::uuid()->toString(),
            'cancellation_policy' => 'Free cancellation up to 24 hours before the tour start time.',
            'cancellation_window_hours' => 24,
            'locale' => 'en',
            'payment_confirmed_at' => now(),
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'stripe_payment_intent_id' => 'pi_hard_' . uniqid(),
            'type' => 'charge',
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'metadata' => $paymentMetadata ?: null,
            'card_last_four' => '4242',
            'card_brand' => 'visa',
        ]);

        FinancialLedgerEntry::create([
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'entry_type' => 'debit',
            'amount' => $amount,
            'currency' => 'EUR',
            'actor' => 'system',
            'description' => 'Payment captured for booking ' . $booking->reference,
        ]);

        return $booking;
    }
}

if (! function_exists('hardeningChargeIntent')) {
    function hardeningChargeIntent(Booking $booking): string
    {
        return Payment::where('booking_id', $booking->id)
            ->where('type', 'charge')
            ->firstOrFail()
            ->stripe_payment_intent_id;
    }
}

if (! function_exists('makeHardeningConnectTourBooking')) {
    /**
     * A pending-payment booking on a Connect-enabled partner's tour — routes
     * CreatePaymentIntentAction through the destination-charge path.
     */
    function makeHardeningConnectTourBooking(): Booking
    {
        $traveler = User::factory()->traveler()->create();

        return Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => makeHardeningConnectTour()->id,
            'tour_date' => now()->addDays(7)->toDateString(),
            'participant_count' => 2,
            'price_per_person' => 10000,
            'total_price' => 20000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);
    }
}

if (! function_exists('makeHardeningPlainTourBooking')) {
    /**
     * A pending-payment booking on a plain partner's tour (no Stripe Connect
     * account) — routes CreatePaymentIntentAction through the platform
     * charge path.
     */
    function makeHardeningPlainTourBooking(): Booking
    {
        $traveler = User::factory()->traveler()->create();

        return Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => makeHardeningTour()->id,
            'tour_date' => now()->addDays(7)->toDateString(),
            'participant_count' => 2,
            'price_per_person' => 10000,
            'total_price' => 20000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);
    }
}

if (! function_exists('hardeningSignedWebhook')) {
    /**
     * Execute a Stripe webhook payload through the real signed verification
     * pipeline (Webhook::constructEvent) using the configured test secret.
     */
    function hardeningSignedWebhook(array $payload): void
    {
        $body = json_encode($payload);
        $timestamp = time();
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_hardening');

        app(ProcessStripeWebhookAction::class)->execute((string) $body, $signature);
    }
}

if (! function_exists('assertNoSlackWebhookLeak')) {
    function assertNoSlackWebhookLeak(array $logs): void
    {
        expect($logs)->each(fn (Expectation $entry) => $entry
            ->not->toContain('hooks.slack.com')
            ->not->toContain('REDACTME'));
    }
}

beforeEach(function () {
    config([
        'services.stripe.key' => 'pk_test_hardening',
        'services.stripe.secret' => 'sk_test_hardening',
        'services.stripe.webhook_secret' => 'whsec_hardening',
        'services.stripe.platform_commission_percent' => '15.00',
    ]);
});

afterEach(function () {
    config(['services.slack.admin_webhook_url' => null]);
});

/*
 * 1. Slack webhook URL scrubbing across all four alert sites.
 */

it('never logs the Slack webhook URL when the payment-failure alert hits a Slack HTTP 500', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);

    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $payment = Payment::where('booking_id', $booking->id)->where('type', 'charge')->firstOrFail();

    $logs = hardeningCaptureLogs(function () use ($booking, $payment): void {
        Http::fake(fn () => Http::response('Server error', 500));

        app(NotifyAdminOnPaymentFailure::class)->handle(new PaymentFailed($payment, $booking));
    });

    expect($logs)->each(fn (Expectation $entry) => $entry->not->toContain('hooks.slack.com'));
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'ADMIN ALERT: Payment failed for booking')))->toBeTrue();
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed') && str_contains($entry, 'HTTP 500')))->toBeTrue();
});

it('never logs the Slack webhook URL when the payment-failure alert transport exception embeds the URL', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);

    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $payment = Payment::where('booking_id', $booking->id)->where('type', 'charge')->firstOrFail();

    $logs = hardeningCaptureLogs(function () use ($booking, $payment): void {
        // The transport exception message itself embeds a Slack webhook URL —
        // the redaction in the job must strip it before logging.
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7 while talking to https://hooks.slack.com/services/T000/B000/REDACTME: connection refused'
        ));

        app(NotifyAdminOnPaymentFailure::class)->handle(new PaymentFailed($payment, $booking));
    });

    assertNoSlackWebhookLeak($logs);
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed')
        && str_contains($entry, '[REDACTED_WEBHOOK_URL]')
        && str_contains($entry, $booking->reference)))->toBeTrue();
});

it('never logs the Slack webhook URL when the email-delivery-failure alert hits a Slack HTTP 500', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);
    $booking = makeHardeningPaidBooking(makeHardeningTour());

    $logs = hardeningCaptureLogs(function () use ($booking): void {
        Http::fake(fn () => Http::response('Server error', 500));

        event(new BookingEmailDeliveryFailed($booking, 'SMTP down'));
    });

    expect($logs)->each(fn (Expectation $entry) => $entry->not->toContain('hooks.slack.com'));
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'ADMIN ALERT: Booking confirmation email delivery failed')))->toBeTrue();
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed') && str_contains($entry, 'HTTP 500')))->toBeTrue();
});

it('never logs the Slack webhook URL when the email-delivery-failure transport exception embeds the URL', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);
    $booking = makeHardeningPaidBooking(makeHardeningTour());

    $logs = hardeningCaptureLogs(function () use ($booking): void {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28 to https://hooks.slack.com/services/T111/B111/REDACTME timed out'
        ));

        event(new BookingEmailDeliveryFailed($booking, 'SMTP down'));
    });

    assertNoSlackWebhookLeak($logs);
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed')
        && str_contains($entry, '[REDACTED_WEBHOOK_URL]')))->toBeTrue();
});

it('never logs the Slack webhook URL when a dispute alert hits a Slack HTTP 500, and dispatches the alert only after commit', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);

    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);
    $ambientLevel = DB::transactionLevel();

    $slackLevels = [];
    $logs = hardeningCaptureLogs(function () use ($intentId, &$slackLevels): void {
        Http::fake(function () use (&$slackLevels) {
            $slackLevels[] = DB::transactionLevel();

            return Http::response('Slack is down', 500);
        });

        hardeningSignedWebhook([
            'id' => 'evt_hard_dispute_500',
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_hardening_500',
                'payment_intent' => $intentId,
                'reason' => 'fraudulent',
            ]],
        ]);
    });

    // The dispute state is committed even though Slack returned 500.
    expect(Payment::where('stripe_payment_intent_id', $intentId)->where('status', 'disputed')->exists())->toBeTrue();
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);

    // The Slack HTTP call only executed after the webhook DB transaction
    // committed — at the ambient level, never nested inside it.
    expect($slackLevels)->toBe([$ambientLevel]);

    expect($logs)->each(fn (Expectation $entry) => $entry->not->toContain('hooks.slack.com'));
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'ADMIN ALERT: Dispute created for payment')))->toBeTrue();
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed') && str_contains($entry, 'HTTP 500')))->toBeTrue();
});

it('never logs the Slack webhook URL when a dispute alert transport exception embeds the URL', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);

    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);

    $logs = hardeningCaptureLogs(function () use ($intentId): void {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7 to https://hooks.slack.com/services/T222/B222/REDACTME refused'
        ));

        hardeningSignedWebhook([
            'id' => 'evt_hard_dispute_exc',
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_hardening_exc',
                'payment_intent' => $intentId,
                'reason' => 'product_unacceptable',
            ]],
        ]);
    });

    assertNoSlackWebhookLeak($logs);
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed')
        && str_contains($entry, '[REDACTED_WEBHOOK_URL]')))->toBeTrue();
    // The dispute still committed despite the Slack transport failure.
    expect(Payment::where('stripe_payment_intent_id', $intentId)->where('status', 'disputed')->exists())->toBeTrue();
});

it('never logs the Slack webhook URL when a refund fails during cancellation and the Slack alert also fails', function () {
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/hardening/admin-alert']);

    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $ambientLevel = DB::transactionLevel();

    $slackLevels = [];
    $logs = hardeningCaptureLogs(function () use ($booking, &$slackLevels): void {
        Http::fake(function () use (&$slackLevels) {
            $slackLevels[] = DB::transactionLevel();

            return Http::response('no service', 500);
        });

        // Stripe fails with a message that embeds a Slack webhook URL — the
        // ProcessRefundAction failure path must sanitize it everywhere.
        $this->mock(PaymentGateway::class)
            ->shouldReceive('refund')
            ->once()
            ->andThrow(new RuntimeException(
                'Stripe refund endpoint unreachable; ops hint https://hooks.slack.com/services/T333/B333/REDACTME'
            ));

        $result = app(CancelBookingAction::class)->execute($booking->reference, $booking->traveler_id);

        expect($result['status'])->toBe(Booking::STATUS_CANCELLED);
    });

    // The cancellation stayed committed through the Stripe failure AND the
    // Slack 500; the refund row is marked failed for a later retry.
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'cancelled')->exists())->toBeTrue();

    $refundRow = Payment::where('booking_id', $booking->id)->where('type', 'refund')->firstOrFail();
    expect($refundRow->status)->toBe('failed')
        ->and($refundRow->metadata['last_error_class'])->toBe(RuntimeException::class)
        ->and($refundRow->metadata['failed_at'])->not->toBeNull();

    // The Slack alert ran outside the (already committed) transaction.
    expect($slackLevels)->toBe([$ambientLevel]);

    assertNoSlackWebhookLeak($logs);
    $refundAlert = collect($logs)->first(fn (string $entry) => str_contains($entry, 'ADMIN ALERT: Refund failed for booking'));
    expect($refundAlert)->not->toBeNull()
        ->and($refundAlert)->toContain('[REDACTED_WEBHOOK_URL]');
    expect(collect($logs)->contains(fn (string $entry) => str_contains($entry, 'Slack admin alert failed') && str_contains($entry, 'HTTP 500')))->toBeTrue();
});

/*
 * 2 + 3. Cancellation: refund outside the transaction, emails after commit.
 */

it('executes the Stripe refund outside the cancellation transaction and sends cancellation emails after commit', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);
    $ambientLevel = DB::transactionLevel();
    Mail::fake();

    $refundLevels = [];
    $this->mock(PaymentGateway::class)
        ->shouldReceive('refund')
        ->once()
        ->with($intentId, 'refund_' . $booking->reference)
        ->andReturnUsing(function () use (&$refundLevels): string {
            // If the refund ever ran inside the cancellation transaction this
            // would record ambient + 1 and fail the expectation below.
            $refundLevels[] = DB::transactionLevel();

            return 're_hardening_after_commit';
        });

    $result = app(CancelBookingAction::class)->execute($booking->reference, $booking->traveler_id);

    expect($refundLevels)->toBe([$ambientLevel])
        ->and($result['status'])->toBe(Booking::STATUS_CANCELLED)
        ->and($result['cancelled_at'])->not->toBeNull();

    // Traveler mail is sent after commit; partner mail is queued after commit.
    Mail::assertSent(BookingCancelledMail::class, fn (BookingCancelledMail $mail) => $mail->booking->reference === $booking->reference);
    Mail::assertQueued(PartnerBookingCancelledMail::class, fn (PartnerBookingCancelledMail $mail) => $mail->booking->reference === $booking->reference);

    $refundRow = Payment::where('booking_id', $booking->id)->where('type', 'refund')->firstOrFail();
    expect($refundRow->status)->toBe('refunded')
        ->and($refundRow->stripe_refund_id)->toBe('re_hardening_after_commit')
        ->and($refundRow->amount)->toBe(10000);

    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);
});

it('keeps the committed cancellation when the cancellation mail fails', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP transport down'));
    $this->mock(PaymentGateway::class)
        ->shouldReceive('refund')
        ->once()
        ->andReturn('re_hardening_mail_fail');

    $result = app(CancelBookingAction::class)->execute($booking->reference, $booking->traveler_id);

    // Mail failure is contained after commit: the cancellation, refund, and
    // audit all survive.
    expect($result['status'])->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'cancelled')->exists())->toBeTrue();
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->where('status', 'refunded')->count())->toBe(1);
});

/*
 * 4. ProcessRefundAction retry-safe, idempotent refund coordination.
 */

it('refunds exactly once when executed twice after a succeeded refund', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);

    $this->mock(PaymentGateway::class)
        ->shouldReceive('refund')
        ->once()
        ->with($intentId, 'refund_' . $booking->reference)
        ->andReturn('re_hardening_once');

    $action = app(ProcessRefundAction::class);
    $action->execute($booking);
    $action->execute($booking);

    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->count())->toBe(1);
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->where('status', 'refunded')->count())->toBe(1);
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);
});

it('reuses the same idempotency key and recovers to refunded when a retry follows a failed refund', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);

    $idempotencyKeys = [];
    $this->mock(PaymentGateway::class)
        ->shouldReceive('refund')
        ->twice()
        ->andReturnUsing(function (string $paymentIntentId, ?string $idempotencyKey = null) use (&$idempotencyKeys): string {
            $idempotencyKeys[] = $idempotencyKey;

            if (count($idempotencyKeys) === 1) {
                throw new RuntimeException('Stripe outage during refund');
            }

            return 're_hardening_recovered';
        });

    $action = app(ProcessRefundAction::class);
    $action->execute($booking);

    // First attempt failed cleanly: claimed row marked failed, no rethrow.
    $failedRow = Payment::where('booking_id', $booking->id)->where('type', 'refund')->firstOrFail();
    expect($failedRow->status)->toBe('failed')
        ->and($failedRow->metadata['last_error_class'])->toBe(RuntimeException::class);

    $action->execute($booking);

    expect($idempotencyKeys)->toBe(['refund_' . $booking->reference, 'refund_' . $booking->reference]);

    // Exactly one refund row and one credit ledger entry after the retry.
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->count())->toBe(1);
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->where('status', 'refunded')->count())->toBe(1)
        ->and(Payment::where('booking_id', $booking->id)->where('type', 'refund')->value('stripe_refund_id'))->toBe('re_hardening_recovered');
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);
});

it('reconciles a pending refund row to refunded when the charge.refunded webhook arrives first', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);

    // ProcessRefundAction has claimed the refund (pending row created) and is
    // mid-flight when the authoritative Stripe webhook arrives.
    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => $intentId,
        'stripe_refund_id' => null,
        'type' => 'refund',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'pending',
    ]);

    hardeningSignedWebhook([
        'id' => 'evt_hard_refunded_1',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_hardening_1',
            'payment_intent' => $intentId,
            'currency' => 'eur',
            'refunds' => ['data' => [['id' => 're_hardening_webhook', 'amount' => 10000]]],
        ]],
    ]);

    // The pending row was reconciled in place — no duplicate row, no
    // duplicate credit — with the Stripe refund id backfilled from the event.
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->count())->toBe(1);
    $refundRow = Payment::where('booking_id', $booking->id)->where('type', 'refund')->firstOrFail();
    expect($refundRow->status)->toBe('refunded')
        ->and($refundRow->stripe_refund_id)->toBe('re_hardening_webhook');
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);

    // A late ProcessRefundAction retry recognizes the reconciled state and
    // never calls Stripe again.
    $this->mock(PaymentGateway::class)->shouldReceive('refund')->never();
    app(ProcessRefundAction::class)->execute($booking);

    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->count())->toBe(1);
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);
});

it('skips Stripe when a fresh in-flight pending refund exists and re-claims a stale pending refund older than 5 minutes', function () {
    $booking = makeHardeningPaidBooking(makeHardeningTour());
    $intentId = hardeningChargeIntent($booking);

    $pendingRefund = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => $intentId,
        'stripe_refund_id' => null,
        'type' => 'refund',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'pending',
    ]);

    // 1) Fresh in-flight pending row (< 5 min old): must skip Stripe call.
    $this->mock(PaymentGateway::class)->shouldReceive('refund')->never();
    app(ProcessRefundAction::class)->execute($booking);
    expect($pendingRefund->fresh()->status)->toBe('pending');

    // 2) Stale pending row (> 5 min old): re-claims, bumps updated_at, and completes refund.
    $staleTimestamp = now()->subMinutes(10);
    $pendingRefund->forceFill(['updated_at' => $staleTimestamp])->save();

    $this->mock(PaymentGateway::class)
        ->shouldReceive('refund')
        ->once()
        ->with($intentId, 'refund_' . $booking->reference)
        ->andReturn('re_hardening_stale_recovered');

    app(ProcessRefundAction::class)->execute($booking);

    $recovered = $pendingRefund->fresh();
    expect($recovered->status)->toBe('refunded')
        ->and($recovered->stripe_refund_id)->toBe('re_hardening_stale_recovered')
        ->and($recovered->updated_at->gt($staleTimestamp))->toBeTrue();
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe(1);
});

it('omits refund_application_fee on a Connect refund when application_fee_amount is 0 and reconciles a failed refund via webhook', function () {
    $tour = makeHardeningTour();
    $zeroFeeBooking = makeHardeningPaidBooking($tour, [
        'destination_account_id' => 'acct_hardening_123',
        'application_fee_amount' => 0,
    ]);

    $client = new HardeningStripeHttpClient([
        ['id' => 're_hard_zero_fee', 'object' => 'refund'],
    ]);
    ApiRequestor::setHttpClient($client);

    try {
        app(ProcessRefundAction::class)->execute($zeroFeeBooking);
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    [$req] = $client->requests;
    expect($req['params']['reverse_transfer'])->toBe('true')
        ->and(array_key_exists('refund_application_fee', $req['params']))->toBeFalse();

    // Also verify HandleRefunded reconciles a previously failed refund row.
    $failedBooking = makeHardeningPaidBooking($tour);
    $failedIntent = hardeningChargeIntent($failedBooking);
    Payment::create([
        'booking_id' => $failedBooking->id,
        'stripe_payment_intent_id' => $failedIntent,
        'stripe_refund_id' => null,
        'type' => 'refund',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'failed',
    ]);

    hardeningSignedWebhook([
        'id' => 'evt_hard_refunded_from_failed',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_hardening_failed_rec',
            'payment_intent' => $failedIntent,
            'currency' => 'eur',
            'refunds' => ['data' => [['id' => 're_hardening_from_failed', 'amount' => 10000]]],
        ]],
    ]);

    $reconciled = Payment::where('booking_id', $failedBooking->id)->where('type', 'refund')->firstOrFail();
    expect($reconciled->status)->toBe('refunded')
        ->and($reconciled->stripe_refund_id)->toBe('re_hardening_from_failed');
});

/*
 * 5. Real StripeService request shape (headers, metadata, Connect flags).
 */

it('sends the idempotency key as an HTTP header and never as a body param', function () {
    $client = new HardeningStripeHttpClient([
        ['id' => 'pi_hard_direct', 'object' => 'payment_intent', 'client_secret' => 'pi_hard_direct_secret_abc'],
        ['id' => 're_hard_direct', 'object' => 'refund'],
    ]);
    ApiRequestor::setHttpClient($client);

    try {
        $service = app(StripeService::class);

        expect($service->createPaymentIntent(10000, 'EUR', 'hardening-create-key'))->toBe('pi_hard_direct_secret_abc')
            ->and($service->refund('pi_hard_direct', 'hardening-refund-key'))->toBe('re_hard_direct');
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    [$create, $refund] = $client->requests;

    expect($create['headers'])->toContain('Idempotency-Key: hardening-create-key')
        ->and(array_key_exists('idempotency_key', $create['params']))->toBeFalse()
        ->and($create['params']['metadata']['origin'])->toBe('bookly-travel')
        ->and($create['absUrl'])->toEndWith('/v1/payment_intents');

    expect($refund['headers'])->toContain('Idempotency-Key: hardening-refund-key')
        ->and(array_key_exists('idempotency_key', $refund['params']))->toBeFalse()
        ->and($refund['params']['payment_intent'])->toBe('pi_hard_direct')
        ->and($refund['absUrl'])->toEndWith('/v1/refunds');
});

it('attaches booking metadata to PaymentIntents for both Connect and non-Connect bookings', function () {
    $connectBooking = makeHardeningConnectTourBooking();
    $plainBooking = makeHardeningPlainTourBooking();

    $client = new HardeningStripeHttpClient([
        ['id' => 'pi_hard_meta_connect', 'object' => 'payment_intent', 'client_secret' => 'pi_hard_meta_connect_secret_x'],
        ['id' => 'pi_hard_meta_plain', 'object' => 'payment_intent', 'client_secret' => 'pi_hard_meta_plain_secret_y'],
    ]);
    ApiRequestor::setHttpClient($client);

    try {
        $action = new CreatePaymentIntentAction(app(StripeService::class), new ApplicationFeeCalculator);
        $action->execute($connectBooking);
        $action->execute($plainBooking);
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    [$connectRequest, $plainRequest] = $client->requests;

    expect($connectRequest['params']['metadata']['booking_reference'])->toBe($connectBooking->reference)
        ->and($connectRequest['params']['metadata']['tour_id'])->toBe((string) $connectBooking->tour_id)
        ->and($connectRequest['params']['metadata']['partner_id'])->toBe((string) $connectBooking->tour->partner_id)
        ->and($connectRequest['params']['transfer_data']['destination'])->toBe('acct_hardening_123')
        ->and($connectRequest['params']['application_fee_amount'])->toBe(3000)
        ->and(collect($connectRequest['headers']))->toContain('Idempotency-Key: ' . $connectBooking->idempotency_key);

    expect($plainRequest['params']['metadata']['booking_reference'])->toBe($plainBooking->reference)
        ->and($plainRequest['params']['metadata']['tour_id'])->toBe((string) $plainBooking->tour_id)
        ->and($plainRequest['params']['metadata']['partner_id'])->toBe((string) $plainBooking->tour->partner_id)
        ->and(array_key_exists('transfer_data', $plainRequest['params']))->toBeFalse()
        ->and(array_key_exists('application_fee_amount', $plainRequest['params']))->toBeFalse()
        ->and(collect($plainRequest['headers']))->toContain('Idempotency-Key: ' . $plainBooking->idempotency_key);
});

it('passes reverse_transfer and refund_application_fee for Connect charges and neither for plain charges', function () {
    $tour = makeHardeningTour();
    $connectBooking = makeHardeningPaidBooking($tour, [
        'destination_account_id' => 'acct_hardening_123',
        'application_fee_amount' => 3000,
    ]);
    $plainBooking = makeHardeningPaidBooking($tour);

    $client = new HardeningStripeHttpClient([
        ['id' => 're_hard_connect', 'object' => 'refund'],
        ['id' => 're_hard_plain', 'object' => 'refund'],
    ]);
    ApiRequestor::setHttpClient($client);

    try {
        $action = app(ProcessRefundAction::class);
        $action->execute($connectBooking);
        $action->execute($plainBooking);
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    [$connectRequest, $plainRequest] = $client->requests;

    // stripe-php form-encodes booleans as the strings 'true'/'false'.
    expect($connectRequest['params']['payment_intent'])->toBe(hardeningChargeIntent($connectBooking))
        ->and($connectRequest['params']['reverse_transfer'])->toBe('true')
        ->and($connectRequest['params']['refund_application_fee'])->toBe('true')
        ->and(array_key_exists('idempotency_key', $connectRequest['params']))->toBeFalse()
        ->and(collect($connectRequest['headers']))->toContain('Idempotency-Key: refund_' . $connectBooking->reference);

    expect($plainRequest['params']['payment_intent'])->toBe(hardeningChargeIntent($plainBooking))
        ->and(array_key_exists('reverse_transfer', $plainRequest['params']))->toBeFalse()
        ->and(array_key_exists('refund_application_fee', $plainRequest['params']))->toBeFalse()
        ->and(array_key_exists('idempotency_key', $plainRequest['params']))->toBeFalse()
        ->and(collect($plainRequest['headers']))->toContain('Idempotency-Key: refund_' . $plainBooking->reference);

    // Both refunds landed in the database exactly once.
    expect(Payment::where('booking_id', $connectBooking->id)->where('type', 'refund')->where('status', 'refunded')->where('stripe_refund_id', 're_hard_connect')->count())->toBe(1)
        ->and(Payment::where('booking_id', $plainBooking->id)->where('type', 'refund')->where('status', 'refunded')->where('stripe_refund_id', 're_hard_plain')->count())->toBe(1);
});

/*
 * 6. PaymentConfigurationGuard fail-closed matrix.
 */

it('fails closed on unsafe staging and production payment configuration without leaking secrets', function () {
    $guard = app(PaymentConfigurationGuard::class);

    // Local/testing never enforce — even with broken configuration.
    $guard->assertValid('local');
    $guard->assertValid('testing');
    config([
        'services.payment.gateway' => 'bogus',
        'services.stripe.key' => null,
        'services.stripe.secret' => null,
        'services.stripe.webhook_secret' => null,
    ]);
    $guard->assertValid('local');
    $guard->assertValid(); // resolves the app environment: testing

    // Accepts valid staging (test-mode) and production (live-mode) keys.
    config([
        'services.payment.gateway' => 'stripe',
        'services.stripe.key' => 'pk_test_bookly_staging',
        'services.stripe.secret' => 'sk_test_bookly_staging',
        'services.stripe.webhook_secret' => 'whsec_bookly_staging',
    ]);
    $guard->assertValid('staging');

    config([
        'services.stripe.key' => 'pk_live_bookly',
        'services.stripe.secret' => 'rk_live_bookly',
        'services.stripe.webhook_secret' => 'whsec_bookly_live',
    ]);
    $guard->assertValid('production');

    config([
        'services.stripe.key' => 'pk_test_bookly_staging',
        'services.stripe.secret' => 'rk_test_bookly_staging',
        'services.stripe.webhook_secret' => 'whsec_bookly_staging',
    ]);
    $guard->assertValid('staging');

    // Rejects any non-stripe gateway in staging/production.
    config(['services.payment.gateway' => 'deterministic']);
    expect(fn () => $guard->assertValid('staging'))->toThrow(LogicException::class);
    config(['services.payment.gateway' => '']);
    expect(fn () => $guard->assertValid('production'))->toThrow(LogicException::class);

    // Rejects missing or empty credentials.
    config([
        'services.payment.gateway' => 'stripe',
        'services.stripe.key' => 'pk_test_bookly_staging',
        'services.stripe.secret' => 'sk_test_bookly_staging',
        'services.stripe.webhook_secret' => 'whsec_bookly_staging',
    ]);
    config(['services.stripe.key' => null]);
    expect(fn () => $guard->assertValid('staging'))->toThrow(LogicException::class);
    config(['services.stripe.key' => '']);
    expect(fn () => $guard->assertValid('production'))->toThrow(LogicException::class);
    config(['services.stripe.key' => 'pk_test_bookly_staging', 'services.stripe.secret' => '   ']);
    expect(fn () => $guard->assertValid('staging'))->toThrow(LogicException::class);
    config(['services.stripe.secret' => 'sk_test_bookly_staging', 'services.stripe.webhook_secret' => '']);
    expect(fn () => $guard->assertValid('production'))->toThrow(LogicException::class);

    // Rejects a webhook secret without the whsec_ prefix.
    config(['services.stripe.webhook_secret' => 'not-a-webhook-secret']);
    expect(fn () => $guard->assertValid('staging'))->toThrow(LogicException::class);

    // Rejects live keys on staging and test keys on production.
    config([
        'services.stripe.key' => 'pk_live_bookly',
        'services.stripe.secret' => 'sk_live_bookly',
        'services.stripe.webhook_secret' => 'whsec_bookly_live',
    ]);
    expect(fn () => $guard->assertValid('staging'))->toThrow(LogicException::class);

    config([
        'services.stripe.key' => 'pk_test_bookly_staging',
        'services.stripe.secret' => 'sk_test_bookly_staging',
    ]);
    expect(fn () => $guard->assertValid('production'))->toThrow(LogicException::class);

    // The rejection messages describe the problem without echoing values.
    config([
        'services.payment.gateway' => 'stripe',
        'services.stripe.key' => 'pk_test_SUPER_SECRET_KEY_VALUE',
        'services.stripe.secret' => 'sk_test_SUPER_SECRET_VALUE',
        'services.stripe.webhook_secret' => 'whsec_SUPER_SECRET_WEBHOOK',
    ]);

    $message = null;
    try {
        $guard->assertValid('production');
        $this->fail('PaymentConfigurationGuard accepted test-mode keys in production.');
    } catch (LogicException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toContain('pk_test_SUPER_SECRET_KEY_VALUE')
        ->not->toContain('sk_test_SUPER_SECRET_VALUE')
        ->not->toContain('whsec_SUPER_SECRET_WEBHOOK');
});

/*
 * 7. Deterministic payment endpoint is unreachable outside local/testing.
 */

it('returns 404 for the deterministic payment endpoint when the app environment is staging or production', function (string $environment) {
    // Tampered configuration must not matter: the environment clamp alone
    // keeps the deterministic adapter unreachable.
    config(['services.payment.gateway' => 'deterministic']);
    app()->instance('env', $environment);

    $traveler = User::factory()->traveler()->create();

    actingAs($traveler)
        ->postJson('/api/public/bookings/BKO-AA22BB/deterministic-payment/confirm', [
            'client_secret' => 'pi_tampered_secret_x',
        ])
        ->assertNotFound();
})->with(['staging', 'production']);

/*
 * 8. Voucher public base URL propagation.
 */

it('propagates the configured voucher public base URL into VoucherService', function () {
    expect(config('services.voucher.public_base_url'))->toBeString()->not->toBe('');

    config(['services.voucher.public_base_url' => 'https://staging.bookly.travel']);

    expect(config('services.voucher.public_base_url'))->toBe('https://staging.bookly.travel')
        ->and(app(VoucherService::class)->verificationUrl('BKO-AA22BB'))->toBe('https://staging.bookly.travel/v/BKO-AA22BB');

    // A trailing slash in the override never leaks into the QR target.
    config(['services.voucher.public_base_url' => 'https://bookly.travel/']);

    expect(app(VoucherService::class)->verificationUrl('BKO-AA22BB'))->toBe('https://bookly.travel/v/BKO-AA22BB');
});
