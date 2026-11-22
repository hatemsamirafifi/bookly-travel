<?php

declare(strict_types=1);

use App\Domains\Partner\Exceptions\TourDraftConflictException;
use App\Domains\Partner\Services\TourService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

[
    ,
    $partnerId,
    $payloadJsonFile,
    $readyFile,
    $goFile,
] = $_SERVER['argv'];

// Observable OS-level startup heartbeat BEFORE Composer/Laravel bootstrap.
@file_put_contents($readyFile . '.spawned', 'pid=' . getmypid() . "\n");

foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'ALLOW_ADMIN_E2E_FIXTURES' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/egm005-create-worker-' . uniqid('', true) . '.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test',
    'DB_PASSWORD' => '',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'null',
    'SCOUT_DRIVER' => 'collection',
    'SESSION_DRIVER' => 'array',
    'GEMINI_API_KEY' => '',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Queue::fake();

$identity = DB::selectOne('select current_database() as db, current_user as usr');
if ($identity->db !== 'bookly_test' || $identity->usr !== 'bookly_test') {
    throw new RuntimeException('Refusing D2 idempotency race outside the disposable test database.');
}

DB::statement("SET lock_timeout = '30000'");
DB::statement("SET statement_timeout = '60000'");

$rawPayload = file_get_contents($payloadJsonFile);
$payload = is_string($rawPayload) ? json_decode($rawPayload, true) : null;
if (! is_array($payload)) {
    throw new RuntimeException('Invalid payload JSON file.');
}

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
    $tour = app(TourService::class)->createTour((int) $partnerId, $payload);
    echo ($tour->wasRecentlyCreated ? 'created:' : 'replayed:') . $tour->id;
} catch (TourDraftConflictException $e) {
    echo 'conflict:409:' . $e->errorCode;
} catch (Throwable $e) {
    echo 'error:' . $e::class . ':' . $e->getMessage();
}

exit(0);
