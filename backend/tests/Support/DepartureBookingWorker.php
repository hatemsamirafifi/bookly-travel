<?php

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Payment\Contracts\PaymentGateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

[
    ,
    $tourSlug,
    $tourOptionId,
    $departureId,
    $tourDate,
    $travelerId,
    $idempotencyKey,
    $participantCount,
    $readyFile,
    $goFile,
] = $_SERVER['argv'];

// Observable OS-level startup heartbeat BEFORE Composer/Laravel bootstrap.
@file_put_contents($readyFile . '.spawned', 'pid=' . getmypid() . "\n");

foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'ALLOW_ADMIN_E2E_FIXTURES' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/egm005-dep-worker-' . uniqid() . '.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test',
    'DB_PASSWORD' => '',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SCOUT_DRIVER' => 'collection',
    'SESSION_DRIVER' => 'array',
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
    throw new RuntimeException('Refusing departure race outside the disposable test database.');
}

DB::statement("SET lock_timeout = '30000'");
DB::statement("SET statement_timeout = '60000'");

$app->instance(PaymentGateway::class, new class implements PaymentGateway
{
    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $destinationAccountId = null,
        ?int $applicationFeeAmount = null,
        array $metadata = []
    ): string {
        return 'pi_dep_race_' . uniqid() . '_secret_local';
    }

    public function refund(
        string $paymentIntentId,
        ?string $idempotencyKey = null,
        bool $reverseTransfer = false,
        bool $refundApplicationFee = false
    ): string {
        return 're_dep_race_' . uniqid();
    }

    public function cancelPaymentIntent(string $intentId): void {}
});

file_put_contents($readyFile, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);

$deadline = microtime(true) + 120.0;
while (true) {
    clearstatcache(true, $goFile);
    if (is_file($goFile)) {
        break;
    }
    if (microtime(true) >= $deadline) {
        echo 'error:Timeout:Timed out waiting for go barrier';
        exit(0);
    }
    usleep(10_000);
}

try {
    $result = app(CreateBookingAction::class)->execute(new CreateBookingDTO(
        tourSlug: $tourSlug,
        tourDate: $tourDate,
        participantCount: (int) $participantCount,
        locale: 'en',
        idempotencyKey: $idempotencyKey,
        travelerId: (int) $travelerId,
        tourOptionId: (int) $tourOptionId,
        tourDepartureId: (int) $departureId,
    ));
    echo 'booked:' . ($result['data']['reference'] ?? 'unknown');
} catch (ConflictHttpException) {
    echo 'conflict:409';
} catch (Throwable $e) {
    echo 'error:' . $e::class . ':' . $e->getMessage();
}

exit(0);
