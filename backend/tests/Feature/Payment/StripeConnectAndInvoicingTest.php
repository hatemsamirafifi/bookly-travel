<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Domains\Payment\Actions\ProcessStripeWebhookAction;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Payment\Services\ApplicationFeeCalculator;
use App\Domains\Payment\Services\StripeConnectService;
use App\Domains\Payment\Services\StripeInvoiceService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Stripe\ApiRequestor;
use Stripe\Event as StripeEvent;
use Stripe\HttpClient\ClientInterface;
use Stripe\Webhook;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

final class RecordingStripeHttpClient implements ClientInterface
{
    public array $requests = [];

    public function __construct(private array $responses) {}

    public function request($method, $absUrl, $headers, $params, $hasFile)
    {
        $this->requests[] = compact('method', 'absUrl', 'headers', 'params');
        $response = array_shift($this->responses);

        if ($response === null) {
            throw new RuntimeException('Unexpected Stripe HTTP request.');
        }

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, ['request-id' => 'req_test']];
    }
}

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_mock']);
    config(['services.stripe.key' => 'pk_test_mock']);
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    config(['services.stripe.platform_commission_percent' => '15.00']);
});

it('routes destination charge and retains platform commission when partner has active Stripe account', function () {
    $category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure']);
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'stripe_account_id' => 'acct_partner_123',
        'stripe_charges_enabled' => true,
        'stripe_payouts_enabled' => true,
        'stripe_onboarding_completed' => true,
        'stripe_details_submitted' => true,
    ]);

    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'connect-tour-' . uniqid(),
        'location' => 'Interlaken, Switzerland',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 10000, // 100.00 EUR
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => 'BKO-' . strtoupper(Str::random(6)),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 10000,
        'total_price' => 20000, // 200.00 EUR
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
    ]);

    $gatewayMock = Mockery::mock(PaymentGateway::class);
    $gatewayMock->shouldReceive('createPaymentIntent')
        ->once()
        ->with(
            20000,
            'EUR',
            $booking->idempotency_key,
            'acct_partner_123',
            3000, // 15% of 20000
            Mockery::subset([
                'booking_reference' => $booking->reference,
                'tour_id' => (string) $tour->id,
                'partner_id' => (string) $partner->id,
            ])
        )
        ->andReturn('pi_destination_test_secret_xyz');

    $action = new CreatePaymentIntentAction($gatewayMock, new ApplicationFeeCalculator);
    $clientSecret = $action->execute($booking);

    expect($clientSecret)->toBe('pi_destination_test_secret_xyz');

    $payment = Payment::where('booking_id', $booking->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->metadata['destination_account_id'])->toBe('acct_partner_123')
        ->and($payment->metadata['application_fee_amount'])->toBe(3000)
        ->and($payment->metadata['partner_id'])->toBe($partner->id);
});

it('updates and governance-audits partner status via account.updated webhook', function () {
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'stripe_account_id' => 'acct_test_sync',
        'stripe_charges_enabled' => false,
        'stripe_payouts_enabled' => false,
        'stripe_details_submitted' => false,
        'stripe_onboarding_completed' => false,
    ]);

    $eventId = 'evt_acc_update_' . uniqid();
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_test_sync',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
            ],
        ],
    ]);

    $mockEvent = StripeEvent::constructFrom(json_decode($payload, true));

    // Mock Webhook::constructEvent
    Mockery::mock('alias:' . Webhook::class)
        ->shouldReceive('constructEvent')
        ->andReturn($mockEvent);

    $action = app(ProcessStripeWebhookAction::class);
    $action->execute($payload, 'sig_dummy');

    $partner->refresh();
    expect($partner->stripe_charges_enabled)->toBeTrue()
        ->and($partner->stripe_payouts_enabled)->toBeTrue()
        ->and($partner->stripe_details_submitted)->toBeTrue()
        ->and($partner->stripe_onboarding_completed)->toBeTrue();

    $audit = GovernanceAuditLog::where('action', 'partner.stripe_account.webhook_synced')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_type)->toBe('system')
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->target_type)->toBe('partner')
        ->and($audit->target_id)->toBe($partner->id)
        ->and($audit->before_state['charges_enabled'])->toBeFalse()
        ->and($audit->after_state['charges_enabled'])->toBeTrue()
        ->and($audit->metadata['stripe_event_id'])->toBe($eventId);
});

it('governance-audits Stripe account creation by a partner', function () {
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create(['user_id' => $partnerUser->id]);
    $httpClient = new RecordingStripeHttpClient([[
        'id' => 'acct_audited_create',
        'object' => 'account',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
    ]]);
    ApiRequestor::setHttpClient($httpClient);

    try {
        $accountId = app(StripeConnectService::class)->createExpressAccount($partner, 'EG');
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    $audit = GovernanceAuditLog::where('action', 'partner.stripe_account.created')->first();
    expect($accountId)->toBe('acct_audited_create')
        ->and($partner->fresh()->stripe_account_id)->toBe('acct_audited_create')
        ->and($audit)->not->toBeNull()
        ->and($audit->actor_type)->toBe('partner')
        ->and($audit->actor_id)->toBe($partner->id)
        ->and($audit->target_id)->toBe($partner->id)
        ->and($audit->before_state['account_id'])->toBeNull()
        ->and($audit->after_state['account_id'])->toBe('acct_audited_create')
        ->and($audit->metadata['source'])->toBe('partner_request')
        ->and($audit->metadata['requesting_user_id'])->toBe($partnerUser->id);
});

it('reuses the same Stripe invoice when invoice creation is retried', function () {
    $category = Category::firstOrCreate(['slug' => 'invoice-idempotency'], ['name' => 'Invoice Idempotency']);
    $partner = Partner::create(['user_id' => User::factory()->partner()->create()->id]);
    $traveler = User::factory()->traveler()->create();
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'invoice-idempotency-' . uniqid(),
        'location' => 'Cairo, Egypt',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 12345,
        'status' => 'published',
    ]);
    $booking = Booking::create([
        'reference' => 'BKO-' . strtoupper(Str::random(6)),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addWeek()->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 12345,
        'total_price' => 12345,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
    ]);

    $responses = [
        ['object' => 'list', 'data' => [], 'has_more' => false, 'url' => '/v1/customers'],
        ['id' => 'cus_retry_test', 'object' => 'customer', 'email' => $traveler->email],
        ['id' => 'ii_retry_test', 'object' => 'invoiceitem', 'amount' => 12345, 'currency' => 'eur'],
        ['id' => 'in_retry_test', 'object' => 'invoice', 'status' => 'draft', 'amount_due' => 12345, 'currency' => 'eur'],
        [
            'id' => 'in_retry_test',
            'object' => 'invoice',
            'status' => 'open',
            'amount_due' => 12345,
            'currency' => 'eur',
            'hosted_invoice_url' => 'https://invoice.stripe.test/in_retry_test',
            'invoice_pdf' => 'https://invoice.stripe.test/in_retry_test.pdf',
        ],
        [
            'id' => 'in_retry_test',
            'object' => 'invoice',
            'status' => 'open',
            'amount_due' => 12345,
            'currency' => 'eur',
            'hosted_invoice_url' => 'https://invoice.stripe.test/in_retry_test',
            'invoice_pdf' => 'https://invoice.stripe.test/in_retry_test.pdf',
        ],
    ];

    $httpClient = new RecordingStripeHttpClient($responses);

    ApiRequestor::setHttpClient($httpClient);

    try {
        $service = app(StripeInvoiceService::class);
        $first = $service->createInvoiceForBooking($booking);
        $second = $service->createInvoiceForBooking($booking->fresh());
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    expect($first['invoice_id'])->toBe('in_retry_test')
        ->and($second['invoice_id'])->toBe('in_retry_test')
        ->and($booking->fresh()->stripe_invoice_id)->toBe('in_retry_test')
        ->and($httpClient->requests)->toHaveCount(6);

    $postRequests = array_values(array_filter(
        $httpClient->requests,
        fn (array $request): bool => $request['method'] === 'post',
    ));

    expect($postRequests)->toHaveCount(4);
    foreach ($postRequests as $request) {
        expect(array_filter(
            $request['headers'],
            fn (string $header): bool => str_starts_with(strtolower($header), 'idempotency-key:'),
        ))->not->toBeEmpty();
    }
});

it('rejects unauthenticated access to Stripe partner endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'status' => ['GET', '/api/partner/stripe/status'],
    'onboard' => ['POST', '/api/partner/stripe/onboard'],
    'dashboard' => ['GET', '/api/partner/stripe/dashboard'],
]);

it('conceals Stripe partner endpoints from authenticated travelers', function (string $method, string $uri) {
    Sanctum::actingAs(User::factory()->traveler()->create(), ['traveler']);

    $this->json($method, $uri)->assertNotFound();
})->with([
    'status' => ['GET', '/api/partner/stripe/status'],
    'onboard' => ['POST', '/api/partner/stripe/onboard'],
    'dashboard' => ['GET', '/api/partner/stripe/dashboard'],
]);

it('confirms booking and creates ledger charge on invoice.paid webhook', function () {
    $category = Category::firstOrCreate(['slug' => 'tours'], ['name' => 'Tours']);
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create(['user_id' => $partnerUser->id]);
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'invoice-tour-' . uniqid(),
        'location' => 'Paris, France',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 50000,
        'status' => 'published',
    ]);

    $booking = Booking::create([
        'reference' => 'BKO-' . strtoupper(Str::random(6)),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(10)->toDateString(),
        'participant_count' => 5,
        'price_per_person' => 10000,
        'total_price' => 50000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
    ]);

    $payload = json_encode([
        'id' => 'evt_inv_paid_' . uniqid(),
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'id' => 'in_test_123',
                'amount_paid' => 50000,
                'currency' => 'eur',
                'payment_intent' => 'pi_invoice_paid_123',
                'hosted_invoice_url' => 'https://invoice.stripe.com/test_123',
                'invoice_pdf' => 'https://invoice.stripe.com/test_123/pdf',
                'metadata' => [
                    'booking_reference' => $booking->reference,
                ],
            ],
        ],
    ]);

    $mockEvent = StripeEvent::constructFrom(json_decode($payload, true));

    Mockery::mock('alias:' . Webhook::class)
        ->shouldReceive('constructEvent')
        ->andReturn($mockEvent);

    $action = app(ProcessStripeWebhookAction::class);
    $action->execute($payload, 'sig_dummy');

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);

    $payment = Payment::where('booking_id', $booking->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('succeeded')
        ->and($payment->amount)->toBe(50000)
        ->and($payment->metadata['invoice_id'])->toBe('in_test_123');

    $ledgerEntry = FinancialLedgerEntry::where('booking_id', $booking->id)
        ->where('entry_type', 'debit')
        ->first();
    expect($ledgerEntry)->not->toBeNull()
        ->and($ledgerEntry->amount)->toBe(50000);
});

it('provides partner Stripe status via API', function () {
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'stripe_account_id' => 'acct_api_test',
        'stripe_charges_enabled' => true,
        'stripe_payouts_enabled' => true,
        'stripe_details_submitted' => true,
        'stripe_onboarding_completed' => true,
    ]);

    $connectMock = Mockery::mock(StripeConnectService::class);
    $connectMock->shouldReceive('syncAccountStatus')
        ->once()
        ->with(Mockery::on(fn ($p) => $p->id === $partner->id))
        ->andReturn([
            'connected' => true,
            'stripe_account_id' => 'acct_api_test',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'onboarding_completed' => true,
        ]);

    app()->instance(StripeConnectService::class, $connectMock);

    Sanctum::actingAs($partnerUser, ['partner']);

    $this->getJson('/api/partner/stripe/status')
        ->assertOk()
        ->assertJsonPath('data.connected', true)
        ->assertJsonPath('data.stripe_account_id', 'acct_api_test')
        ->assertJsonPath('data.payouts_enabled', true);
});

it('generates an onboarding link for a partner via API', function () {
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'stripe_account_id' => 'acct_onboard_test',
    ]);

    $connectMock = Mockery::mock(StripeConnectService::class);
    $connectMock->shouldReceive('createAccountLink')
        ->once()
        ->with(
            Mockery::on(fn ($p) => $p->id === $partner->id),
            'http://localhost:3000/partner/settings/payouts?status=success',
            'http://localhost:3000/partner/settings/payouts?status=refresh',
        )
        ->andReturn('https://connect.stripe.com/setup/s/test_link');

    app()->instance(StripeConnectService::class, $connectMock);

    Sanctum::actingAs($partnerUser, ['partner']);

    $this->postJson('/api/partner/stripe/onboard')
        ->assertOk()
        ->assertJsonPath('url', 'https://connect.stripe.com/setup/s/test_link');
});

it('generates a dashboard login link for a partner with connected account', function () {
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'stripe_account_id' => 'acct_dash_test',
        'stripe_charges_enabled' => true,
    ]);

    $connectMock = Mockery::mock(StripeConnectService::class);
    $connectMock->shouldReceive('createLoginLink')
        ->once()
        ->with(Mockery::on(fn ($p) => $p->id === $partner->id))
        ->andReturn('https://connect.stripe.com/express/test_dash_link');

    app()->instance(StripeConnectService::class, $connectMock);

    Sanctum::actingAs($partnerUser, ['partner']);

    $this->getJson('/api/partner/stripe/dashboard')
        ->assertOk()
        ->assertJsonPath('url', 'https://connect.stripe.com/express/test_dash_link');
});
