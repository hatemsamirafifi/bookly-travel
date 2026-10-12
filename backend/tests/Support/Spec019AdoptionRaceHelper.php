<?php

namespace Tests\Support;

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Spec019 T064: strictly necessary adoption-race support (new file).
 *
 * Small static helper ONLY for backend/tests/Feature/Partner/
 * TourItineraryAdoptionTest.php: disposable-database guards, real
 * PHP-subprocess source-edit spawning (via the established
 * Spec019SourceEditWorker.php + Spec019TranslationProcess barriers),
 * exact-backend-pid PostgreSQL lock-wait proof, and ID-scoped fixture
 * cleanup with mandatory default-connection restore/purge.
 *
 * No production logic lives here; no mocks, no credentials, no network.
 * Fixtures commit for real on the named `spec019_owner` default connection
 * (child processes can never see the Pest wrapping transaction), and
 * cleanup deletes ONLY tracked IDs before restoring the default.
 */
final class Spec019AdoptionRaceHelper
{
    public static function useOwnerConnection(?string &$previousDefault): void
    {
        $previousDefault = (string) config('database.default');
        config()->set('database.connections.spec019_owner', config('database.connections.pgsql'));
        DB::setDefaultConnection('spec019_owner');
    }

    /**
     * Disposable-database guards BEFORE any write: the same bookly_test
     * database AND role on both axes, never dev/prod, with bounded waits.
     */
    public static function assertSafeDatabase(): void
    {
        DB::statement("SET statement_timeout = '10000'");
        DB::statement("SET lock_timeout = '5000'");
        expect(DB::selectOne('select current_database() as db')->db)->toBe('bookly_test');
        expect(DB::selectOne('select current_user as usr')->usr)->toBe('bookly_test');
    }

    /**
     * Spawn one owned child source writer through the ACTUAL
     * TourService::updateTour path. Args carry ONLY the fixture tour id,
     * one disjoint patch field/value, and barrier paths.
     *
     * @return array{0: Spec019TranslationProcess, 1: array<string, string>}
     */
    public static function spawnSourceEdit(int $tourId, string $field, string $value, string $barrierDir, string $name): array
    {
        $paths = [
            'arrival' => "{$barrierDir}/{$name}.arrival",
            'release' => "{$barrierDir}/{$name}.release",
            'done' => "{$barrierDir}/{$name}.done",
            'out' => "{$barrierDir}/{$name}.out",
            'err' => "{$barrierDir}/{$name}.err",
        ];

        $proc = Spec019TranslationProcess::spawn(
            __DIR__ . '/Spec019SourceEditWorker.php',
            [(string) $tourId, $field, $value, $paths['arrival'], $paths['release'], $paths['done']],
            $paths['out'],
            $paths['err'],
            "spec019-adopt-{$name}"
        );

        return [$proc, $paths];
    }

    /**
     * Spawn one owned child adoption runner: it bootstraps Laravel
     * standalone and calls the ACTUAL `tours:adopt-itineraries`
     * --after-id=(tourId-1) --limit=1, blocking on the parent-held Tour
     * row lock until the parent commits (given the planned Tour-first
     * lock recheck). Args carry ONLY the fixture tour id and barrier
     * paths.
     *
     * @return array{0: Spec019TranslationProcess, 1: array<string, string>}
     */
    public static function spawnAdoption(int $tourId, string $barrierDir, string $name): array
    {
        $paths = [
            'arrival' => "{$barrierDir}/{$name}.arrival",
            'release' => "{$barrierDir}/{$name}.release",
            'done' => "{$barrierDir}/{$name}.done",
            'out' => "{$barrierDir}/{$name}.out",
            'err' => "{$barrierDir}/{$name}.err",
        ];

        $proc = Spec019TranslationProcess::spawn(
            __DIR__ . '/Spec019AdoptionWorker.php',
            [(string) $tourId, $paths['arrival'], $paths['release'], $paths['done']],
            $paths['out'],
            $paths['err'],
            "spec019-adopt-{$name}"
        );

        return [$proc, $paths];
    }

    /**
     * Parse the real PostgreSQL backend pid a source child wrote into its
     * arrival file. Throws loudly when the file carries no pid.
     */
    public static function backendPid(string $arrivalPath): int
    {
        $content = trim((string) @file_get_contents($arrivalPath));
        if (! preg_match('/\bpg=(\d+)\b/', $content, $matches) || (int) $matches[1] <= 0) {
            throw new \RuntimeException("arrival file carries no pg backend pid: '{$arrivalPath}' content '{$content}'.");
        }

        return (int) $matches[1];
    }

    /**
     * Bounded proof that THOSE EXACT child backends are waiting on the real
     * PostgreSQL lock: one poll instant where every listed pid shows
     * wait_event_type='Lock' in pg_stat_activity, corroborated by at least
     * one ungranted pg_locks row among them. Sleep timing or process
     * aliveness alone is never accepted as proof.
     *
     * @param  array<int, int>  $backendPids
     */
    public static function awaitPgLockWaits(array $backendPids, float $timeoutSeconds): string
    {
        $pids = array_values(array_unique(array_map('intval', $backendPids)));
        foreach ($pids as $pid) {
            if ($pid <= 0) {
                throw new \RuntimeException('pg lock proof requires positive backend pids.');
            }
        }
        $list = implode(',', $pids);

        $deadline = microtime(true) + $timeoutSeconds;
        $lastSnapshot = 'none';
        while (true) {
            DB::select('select pg_stat_clear_snapshot()');
            $activity = DB::select(
                "select pid, application_name, wait_event_type, wait_event from pg_stat_activity where pid in ({$list})"
            );
            $byPid = [];
            foreach ($activity as $row) {
                $byPid[(int) $row->pid] = $row;
            }
            $waiting = [];
            foreach ($pids as $pid) {
                if (isset($byPid[$pid]) && (($byPid[$pid]->wait_event_type ?? '') === 'Lock')) {
                    $waiting[] = $pid;
                }
            }
            $locks = DB::select(
                "select pid, locktype, mode from pg_locks where pid in ({$list}) and not granted"
            );
            $lastSnapshot = substr((string) json_encode(['activity' => $activity, 'ungranted' => $locks]), 0, 2000);

            $blockedPids = array_unique(array_map(static fn ($lock): int => (int) $lock->pid, $locks));
            if (count($waiting) === count($pids) && count(array_intersect($pids, $blockedPids)) === count($pids)) {
                return 'writers lock-waiting on pids ' . $list
                    . '; waiting=' . substr((string) json_encode($waiting), 0, 100)
                    . '; ungranted_locks=' . count($locks)
                    . '; snapshot=' . $lastSnapshot;
            }

            if (microtime(true) >= $deadline) {
                throw new \RuntimeException(
                    "pg lock-wait proof timed out after {$timeoutSeconds}s for backend pids {$list}."
                    . " Last snapshot: {$lastSnapshot}"
                );
            }
            usleep(100000);
        }
    }

    /**
     * Scoped cleanup: stop ONLY our child processes, delete ONLY our
     * fixture rows by id (never shared rows, broad deletes, or Redis
     * flushes), remove ONLY our barrier files, then ALWAYS restore the
     * default named connection and purge the owner connection.
     *
     * When the DB identity guard failed ($guardPassed false) NO fixture
     * writes run at all: only process stops, own file removal, and the
     * mandatory restore/purge. A cleanup error is rethrown only after
     * restore/purge have been attempted.
     *
     * @param  array<int, Spec019TranslationProcess>  $procs
     * @param  array<int, string>  $files
     * @param  array{userIds?: array<int, int>, partnerIds?: array<int, int>, categoryIds?: array<int, int>, tourIds?: array<int, int>}  $owned
     */
    public static function cleanup(array $procs, array $files, string $barrierDir, array $owned, ?string $previousDefault, bool $guardPassed): void
    {
        $firstError = null;
        try {
            foreach ($procs as $proc) {
                $proc->terminate();
            }
            foreach ($procs as $proc) {
                $proc->close();
            }

            if ($guardPassed) {
                foreach ($owned['tourIds'] ?? [] as $tourId) {
                    TourTranslationState::where('tour_id', $tourId)->delete();
                    TourTranslation::where('tour_id', $tourId)->delete();
                    Tour::where('id', $tourId)->delete();
                }
                foreach ($owned['partnerIds'] ?? [] as $id) {
                    Partner::where('id', $id)->delete();
                }
                foreach ($owned['userIds'] ?? [] as $id) {
                    User::where('id', $id)->delete();
                }
                foreach ($owned['categoryIds'] ?? [] as $id) {
                    Category::where('id', $id)->delete();
                }
            }

            foreach ($files as $file) {
                if (is_string($file) && $file !== '' && is_file($file)) {
                    @unlink($file);
                }
            }
            if ($barrierDir !== '' && is_dir($barrierDir)) {
                @rmdir($barrierDir);
            }
        } catch (\Throwable $e) {
            $firstError = $e;
        }

        try {
            if (is_string($previousDefault) && $previousDefault !== '') {
                DB::setDefaultConnection($previousDefault);
            }
        } catch (\Throwable $e) {
            $firstError ??= $e;
        }
        try {
            DB::purge('spec019_owner');
        } catch (\Throwable $e) {
            $firstError ??= $e;
        }

        if ($firstError !== null) {
            throw $firstError;
        }
    }
}
