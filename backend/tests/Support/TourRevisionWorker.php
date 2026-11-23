<?php

use App\Domains\Admin\Actions\ApproveTourRevisionAction;
use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Partner\Controllers\TourRevisionController;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Services\TourRevisionService;
use App\Domains\Partner\Services\TourService;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

[, $operation, $actorId, $tourId, $revisionId, $arrival] = $_SERVER['argv'];
@file_put_contents($arrival . '.spawned', 'pid=' . getmypid() . "\n");
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/egm003-worker-' . uniqid() . '.php',
    'DB_CONNECTION' => 'pgsql', 'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432', 'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test', 'DB_PASSWORD' => '', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'database',
    'SCOUT_DRIVER' => 'collection', 'SESSION_DRIVER' => 'array',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('select current_database() as db, current_user as usr');
if ($identity->db !== 'bookly_test' || $identity->usr !== 'bookly_test') {
    throw new RuntimeException('Refusing revision race outside the disposable test database.');
}
DB::statement("SET lock_timeout = '30000'");
DB::statement("SET statement_timeout = '60000'");
$actor = User::findOrFail((int) $actorId);
if (in_array($operation, ['revision approval', 'moderation request', 'moderation correction', 'moderation rejection'], true)) {
    file_put_contents($arrival, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);
    try {
        $service = app(TourRevisionService::class);
        match ($operation) {
            'revision approval' => app(ApproveTourRevisionAction::class)->execute($actor, (int) $tourId, (int) $revisionId),
            'moderation request' => $service->requestChanges((int) $tourId, $actor, 'published_review', 'Clarify source title.', [['path' => 'source.title']]),
            'moderation correction' => $service->proposeCorrection((int) $tourId, $actor, 'Exceptional correction.', [['path' => 'source.title']], ['source' => ['title' => 'Correction proposal']]),
            'moderation rejection' => $service->rejectRevision((int) $tourId, (int) $revisionId, $actor, 'Revise this proposal.'),
        };
        echo 'allowed';
    } catch (AuthorizationException $exception) {
        echo 'refused:403';
    } catch (TourRevisionException $exception) {
        echo 'refused:' . $exception->status;
    } catch (HttpExceptionInterface $exception) {
        echo 'refused:' . $exception->getStatusCode();
    }
    exit(0);
}
if ($operation === 'booking ownership') {
    // Deterministic external gateway only: actual booking/payment domain
    // actions and PostgreSQL transactions are exercised in this process.
    $app->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function createPaymentIntent(int $amount, string $currency, string $idempotencyKey, ?string $destinationAccountId = null, ?int $applicationFeeAmount = null, array $metadata = []): string
        {
            return 'pi_owner_race_secret_local';
        }

        public function refund(
            string $paymentIntentId,
            ?string $idempotencyKey = null,
            bool $reverseTransfer = false,
            bool $refundApplicationFee = false
        ): string {
            throw new RuntimeException('Refund is outside the owner race.');
        }

        public function cancelPaymentIntent(string $intentId): void
        {
            throw new RuntimeException('Unexpected payment compensation in owner race.');
        }
    });
    $bookingTour = Tour::findOrFail((int) $tourId);
    file_put_contents($arrival, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);
    try {
        app(CreateBookingAction::class)->execute(new CreateBookingDTO(
            $bookingTour->slug, now()->addDays(5)->toDateString(), 1, 'en', $revisionId, (int) $actor->id,
        ));
        echo 'allowed';
    } catch (HttpExceptionInterface $exception) {
        echo 'refused:' . $exception->getStatusCode();
    }
    exit(0);
}
$partnerId = (int) $actor->partner->id;
$tours = app(TourService::class);
$observedTour = $tours->requireOwnedTour((int) $tourId, $partnerId);
$request = Request::create('/api/partner/tours/' . $tourId);
$request->attributes->set('partner_id', $partnerId);
$request->setUserResolver(fn () => $actor);
file_put_contents($arrival, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);

try {
    match ($operation) {
        'detail' => $tours->getOwnedTourDetail((int) $tourId, $partnerId),
        'governance' => $tours->ownedContentGovernance((int) $tourId, $partnerId),
        'revision detail' => app(TourRevisionController::class)->show($request, $tourId, $revisionId),
        'revision history' => app(TourRevisionController::class)->index($request, $tourId),
        'operational save' => $tours->updateTour($observedTour, ['price_from' => 73]),
        default => throw new RuntimeException('Unknown revision race operation.'),
    };
    echo 'allowed';
} catch (HttpExceptionInterface $exception) {
    echo 'refused:' . $exception->getStatusCode();
}
