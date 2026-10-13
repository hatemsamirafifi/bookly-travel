<?php

declare(strict_types=1);

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Services\PermanentTranslationException;
use App\Domains\Partner\Services\TourTranslationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Spec019 T034 (small slice): standalone child worker for real process races.
 *
 * Invoked ONLY by backend/tests/Feature/Partner/TourTranslationConcurrencyTest.php
 * via proc_open:
 *
 *   php Spec019TranslationWorker.php <tourId> <locale> <sourceHash> <arrivalPath>
 *       <releasePath> <donePath> <marker>
 *
 * Child args carry ONLY fixture IDs, the fake expected hash, barrier paths and
 * a short fake-output marker: no credentials, no source personal data. The
 * child bootstraps Laravel standalone (vendor autoload + bootstrap/app.php):
 * it never loads tests/bootstrap.php, so there is no global flock, no second
 * Pest runner, no RefreshDatabase handling and no TEST_TOKEN semantics here.
 * The parent Pest process remains the sole suite owner.
 *
 * Protocol: run the ACTUAL GenerateTourTranslationJob::handle against the real
 * shared bookly_test rows with a fake provider that signals arrival, waits for
 * the parent release, then returns harmless valid texts suffixed with the
 * marker (structure/integers/nulls/order preserved, FIRST vs SECOND stays
 * distinguishable). The reserved marker FAIL instead throws the harmless fixed
 * PermanentTranslationException('translation_provider_rejected') after release
 * so the child exercises the ACTUAL job failure path (markFailed scoped by
 * source_hash and non-ready status); the job catches it internally, so the
 * child still exits 0 with a done file. Writes arrival then done barrier
 * files; exit 0 on completion (including superseded no-ops and scoped
 * failures), non-zero with a stderr message otherwise.
 */
$argv = $_SERVER['argv'] ?? [];
if (count($argv) !== 8) {
    fwrite(STDERR, "usage: Spec019TranslationWorker.php <tourId> <locale> <sourceHash> <arrivalPath> <releasePath> <donePath> <marker>\n");
    exit(2);
}

[, $tourIdArg, $locale, $sourceHash, $arrivalPath, $releasePath, $donePath, $marker] = $argv;

$fail = static function (string $message, int $code): never {
    fwrite(STDERR, $message . "\n");
    exit($code);
};

if (! ctype_digit((string) $tourIdArg) || (int) $tourIdArg <= 0) {
    $fail('tourId must be a positive integer.', 2);
}
$tourId = (int) $tourIdArg;

if (! in_array($locale, ['es', 'it'], true)) {
    $fail('locale must be es or it.', 2);
}

if (! is_string($sourceHash) || ! preg_match('/^[0-9a-f]{64}$/', $sourceHash)) {
    $fail('sourceHash must be a 64-char hex sha256.', 2);
}

if (! is_string($marker) || ! preg_match('/^[A-Za-z0-9_-]{1,16}$/', $marker)) {
    $fail('marker must be 1-16 chars of [A-Za-z0-9_-].', 2);
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
// cache and the sync queue, and a blank GEMINI_API_KEY (fake provider only,
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
foreach ($childEnv as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require $backendRoot . '/vendor/autoload.php';

/** @var Application $app */
$app = require $backendRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = DB::connection();
// Bounded waits: a stuck child must fail loudly, never wedge the suite.
$db->statement("SET statement_timeout = '10000'");
$db->statement("SET lock_timeout = '5000'");

// Guard BEFORE any write: the disposable database on both axes, never dev/prod.
$actualDb = (string) (($db->selectOne('select current_database() as db')->db) ?? '');
$actualUser = (string) (($db->selectOne('select current_user as usr')->usr) ?? '');
if ($actualDb !== 'bookly_test' || $actualUser !== 'bookly_test') {
    $fail("refusing writes: current_database=[{$actualDb}] current_user=[{$actualUser}], want bookly_test/bookly_test.", 3);
}

// Swallow post-commit index dispatches; the parent asserts rows, not the queue.
Queue::fake();

$translator = new class($arrivalPath, $releasePath, $locale, $marker) implements TourContentTranslator
{
    public function __construct(
        private readonly string $arrivalPath,
        private readonly string $releasePath,
        private readonly string $locale,
        private readonly string $marker,
    ) {}

    public function translate(array $strings, string $locale): array
    {
        // Arrival first: the parent mutates shared rows only after observing
        // this file, so the job precheck above provably ran against older state.
        if (file_put_contents($this->arrivalPath, $this->marker . "\n") === false) {
            throw new RuntimeException('child failed to write arrival signal.');
        }

        // Bounded 120s release wait: deliberately longer than the parent's 60s
        // barrier deadlines so the parent always times out first with the
        // child stderr tail attached; the child still fails loudly (done
        // error file + stderr + non-zero exit) instead of hanging forever.
        $deadline = microtime(true) + 120.0;
        while (! is_file($this->releasePath)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('child timed out waiting for parent release.');
            }
            usleep(50000);
            clearstatcache(true, $this->releasePath);
        }

        // Reserved FAIL marker: exercise the ACTUAL job failure path with the
        // harmless fixed terminal error. The job catches this internally and
        // scopes markFailed to the same source_hash/non-ready rows, so an
        // overlapping success (ready) or a newer source edit (new hash) must
        // stand unchanged afterwards.
        if ($this->marker === 'FAIL') {
            throw new PermanentTranslationException('translation_provider_rejected');
        }

        // Harmless valid texts: mapping shape preserved, integers/nulls/order
        // untouched, every string suffixed so markers stay distinguishable.
        $translated = [];
        foreach ($strings as $path => $value) {
            $translated[$path] = $value . " ({$this->locale} {$this->marker})";
        }

        return $translated;
    }
};

$app->singleton(
    TourContentTranslator::class,
    static fn (): TourContentTranslator => $translator
);

try {
    (new GenerateTourTranslationJob($tourId, $locale, $sourceHash))->handle(
        $app->make(TourTranslationService::class),
        $app->make(TourContentTranslator::class)
    );
} catch (Throwable $e) {
    @file_put_contents($donePath, 'error ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    $fail('child job failed: ' . get_class($e) . ': ' . $e->getMessage(), 1);
}

if (@file_put_contents($donePath, "ok marker={$marker}\n") === false) {
    $fail('child failed to write done signal.', 1);
}

exit(0);
