<?php

declare(strict_types=1);

use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Spec019 T034: standalone child source writer for the real overlapping-edit race.
 *
 * Invoked ONLY by backend/tests/Feature/Partner/TourTranslationConcurrencyTest.php
 * via proc_open:
 *
 *   php Spec019SourceEditWorker.php <tourId> <field> <value> <arrivalPath>
 *       <releasePath> <donePath>
 *
 * Child args carry ONLY the fixture tour id, one disjoint patch field
 * (title|meeting_point), a short safe value, and barrier paths: no
 * credentials, no source personal data. The child bootstraps Laravel
 * standalone (vendor autoload + bootstrap/app.php): it never loads
 * tests/bootstrap.php, so there is no global flock, no second Pest runner,
 * no RefreshDatabase handling and no TEST_TOKEN semantics here. The parent
 * Pest process remains the sole suite owner.
 *
 * Barrier semantics (deadlock-free by construction): the parent holds the
 * Tour row lock in its own transaction BEFORE spawning; the child writes
 * arrival (bootstrapped, with its real pg backend pid + application_name),
 * then writes release (attempting its DB write), then IMMEDIATELY calls the
 * ACTUAL TourService::updateTour (blocking on the parent-held row lock until
 * the parent commits). The child never waits on a parent file before
 * attempting: gating the attempt on a release file would make the required
 * pg_stat_activity/pg_locks waiting proof impossible. The parent's lock
 * release is its holding-transaction commit, observed between the release
 * files and the done files. Exit 0 with a done file on success; any write
 * failure throws/reports a bounded diagnostic (exception class + message
 * only, never credentials or full bodies) with a done error file, a stderr
 * message and a non-zero exit.
 */
$argv = $_SERVER['argv'] ?? [];
if (count($argv) !== 7) {
    fwrite(STDERR, "usage: Spec019SourceEditWorker.php <tourId> <field> <value> <arrivalPath> <releasePath> <donePath>\n");
    exit(2);
}

[, $tourIdArg, $field, $value, $arrivalPath, $releasePath, $donePath] = $argv;

$fail = static function (string $message, int $code): never {
    fwrite(STDERR, $message . "\n");
    exit($code);
};

if (! ctype_digit((string) $tourIdArg) || (int) $tourIdArg <= 0) {
    $fail('tourId must be a positive integer.', 2);
}
$tourId = (int) $tourIdArg;

if (! in_array($field, ['title', 'meeting_point'], true)) {
    $fail('field must be title or meeting_point.', 2);
}

if (! is_string($value) || ! preg_match('/^[A-Za-z0-9 _.,-]{1,80}$/', $value)) {
    $fail('value must be 1-80 chars of [A-Za-z0-9 _.,-].', 2);
}

foreach (['arrival' => $arrivalPath, 'release' => $releasePath, 'done' => $donePath] as $kind => $path) {
    if (! is_string($path) || ! is_dir(dirname($path)) || ! preg_match('/^[A-Za-z0-9_.-]+$/', basename($path))) {
        $fail("{$kind} path must live inside an existing barrier dir with a safe name.", 2);
    }
}
if (count(array_unique([$arrivalPath, $releasePath, $donePath])) !== 3) {
    $fail('arrival/release/done paths must be distinct.', 2);
}

$backendRoot = dirname(__DIR__, 2);

// Environment BEFORE vendor/Dotenv/app boot. Mirrors tests/bootstrap.php so the
// child reaches the same disposable database with collection Scout, array
// cache and the sync queue, and a blank GEMINI_API_KEY (real service path,
// never secrets or live HTTP).
$childEnv = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    // Unique and nonexistent: never load or write a shared config cache.
    'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/spec019-child-config-' . uniqid('', true) . '.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test',
    'DB_PASSWORD' => '',
    'DB_URL' => '',
    'GEMINI_API_KEY' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'SCOUT_DRIVER' => 'collection',
    'SCOUT_PREFIX' => 'bookly_test_',
    'SCOUT_QUEUE_CONNECTION' => 'sync',
    'SCOUT_QUEUE' => 'default',
];
foreach ($childEnv as $name => $envValue) {
    putenv($name . '=' . $envValue);
    $_ENV[$name] = $_SERVER[$name] = $envValue;
}

require $backendRoot . '/vendor/autoload.php';

/** @var Application $app */
$app = require $backendRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = DB::connection();
// Bounded waits: a stuck child must fail loudly, never wedge the suite. The
// lock timeout is wider than the translation worker's because this child
// MUST block on the parent-held Tour row lock while the parent proves the
// wait via pg_stat_activity/pg_locks; it is still bounded.
$db->statement("SET statement_timeout = '60000'");
$db->statement("SET lock_timeout = '30000'");

// Guard BEFORE any write: the disposable database on both axes, never dev/prod.
$actualDb = (string) (($db->selectOne('select current_database() as db')->db) ?? '');
$actualUser = (string) (($db->selectOne('select current_user as usr')->usr) ?? '');
if ($actualDb !== 'bookly_test' || $actualUser !== 'bookly_test') {
    $fail("refusing writes: current_database=[{$actualDb}] current_user=[{$actualUser}], want bookly_test/bookly_test.", 3);
}

// Swallow post-commit index/translation dispatches; the parent asserts rows.
Queue::fake();

// Identifiable backend for the parent's exact-pid lock proof.
$appName = 'spec019-src-' . $field;
$db->statement('SET application_name = \'' . $appName . '\'');
$backendPid = (int) (($db->selectOne('select pg_backend_pid() as pid')->pid) ?? 0);
if ($backendPid <= 0) {
    $fail('child failed to read its pg backend pid.', 3);
}

if (@file_put_contents($arrivalPath, "field={$field} pg={$backendPid} app={$appName}\n") === false) {
    $fail('child failed to write arrival signal.', 1);
}

// Release-into-attempt: the DB write starts immediately after this file, so
// the parent's pg lock observation window sits between release and done.
if (@file_put_contents($releasePath, "attempting field={$field} pg={$backendPid}\n") === false) {
    $fail('child failed to write release signal.', 1);
}

try {
    $tour = Tour::findOrFail($tourId);
    $app->make(TourService::class)->updateTour($tour, [$field => $value]);
} catch (Throwable $e) {
    // Bounded diagnostic only: class + message, never credentials or bodies.
    @file_put_contents($donePath, 'error ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    $fail('child source edit failed: ' . get_class($e) . ': ' . $e->getMessage(), 1);
}

if (@file_put_contents($donePath, "ok field={$field} pg={$backendPid}\n") === false) {
    $fail('child failed to write done signal.', 1);
}

exit(0);
