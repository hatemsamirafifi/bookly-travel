<?php

declare(strict_types=1);

/**
 * Disposable PostgreSQL migration rehearsal (UP -> full-batch DOWN -> UP)
 * against representative pre-EGM-005 existing data.
 *
 * Guarantees:
 *   1. Refuses to run against any non-test database (current_database() MUST
 *      start with `bookly_test`).
 *   2. Lands pre-EGM-005 schema in Batch 1 (up through 2026_11_14_000001).
 *   3. Seeds representative existing partner, traveler, category, tour,
 *      translation, tour option, pricing tier, and confirmed booking rows.
 *   4. Applies all 4 EGM-005 migrations (2026_11_17_000001..000003 &
 *      2026_11_21_000001) together in Batch 2 and verifies PostgreSQL
 *      constraints/indexes plus zero data loss.
 *   5. Rolls back the ENTIRE Batch 2 with `migrate:rollback --force` (NO
 *      `--step=1`!) and verifies all 4 migrations rolled back while every
 *      historical pre-EGM-005 row remained intact.
 *   6. Re-applies Batch 2 (`migrate --force`) cleanly and re-verifies all
 *      historical rows and PostgreSQL constraints/indexes.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$backendRoot = dirname(__DIR__);

require $backendRoot . '/vendor/autoload.php';

/** @var Application $app */
$app = require $backendRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fail = static function (string $message): never {
    fwrite(STDERR, "[rehearse-migrations] FAIL: {$message}\n");
    exit(1);
};

$runArtisan = static function (string $command, array $params = []) use ($fail): string {
    $exit = Artisan::call($command, $params);
    $output = trim(Artisan::output());
    if ($exit !== 0) {
        $fail("Artisan `{$command}` exited with code {$exit}.\n{$output}");
    }

    return $output;
};

try {
    // 0. Guard: ONLY run against disposable test databases (`bookly_test*`).
    $connection = DB::connection();
    $driver = $connection->getDriverName();
    if ($driver !== 'pgsql') {
        $fail("Expected pgsql driver, got [{$driver}].");
    }

    $actualDb = (string) ($connection->selectOne('select current_database() as db')->db ?? '');
    if (! str_starts_with($actualDb, 'bookly_test')) {
        $fail("Refusing to wipe/rehearse non-test database [{$actualDb}]. Database name must start with 'bookly_test'.");
    }

    echo "[rehearse-migrations] Connected to disposable PostgreSQL database [{$actualDb}].\n";

    $egm005Migrations = [
        '2026_11_17_000001_add_egm005_draft_enhancements_table',
        '2026_11_17_000002_create_tour_departures_table',
        '2026_11_17_000003_add_egm005_fields_to_pricing_tiers_and_bookings',
        '2026_11_21_000001_harden_egm005_database_invariants',
    ];

    // 1. Build clean pre-EGM-005 baseline (Batch 1 up through 2026_11_14_000001).
    $runArtisan('db:wipe', ['--force' => true]);
    $runArtisan('migrate', ['--force' => true]);
    $runArtisan('migrate:rollback', ['--step' => count($egm005Migrations), '--force' => true]);

    if (Schema::hasTable('tour_departures')) {
        $fail('Expected tour_departures to be absent in pre-EGM-005 baseline.');
    }

    foreach ($egm005Migrations as $migration) {
        if (DB::table('migrations')->where('migration', $migration)->exists()) {
            $fail("Expected migration [{$migration}] to be rolled back in baseline.");
        }
    }

    echo "[rehearse-migrations] Pre-EGM-005 baseline schema ready.\n";

    // 2. Seed representative existing pre-EGM-005 records.
    $now = now()->toDateTimeString();

    $partnerUserId = DB::table('users')->insertGetId([
        'name' => 'Rehearsal Partner',
        'email' => 'rehearsal-partner@bookly.test',
        'password' => bcrypt('Password123!'),
        'role' => 'partner',
        'locale' => 'en',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $travelerUserId = DB::table('users')->insertGetId([
        'name' => 'Rehearsal Traveler',
        'email' => 'rehearsal-traveler@bookly.test',
        'password' => bcrypt('Password123!'),
        'role' => 'traveler',
        'locale' => 'en',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $partnerId = DB::table('partners')->insertGetId([
        'user_id' => $partnerUserId,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'slug' => 'rehearsal-walking',
        'name' => 'Rehearsal Walking',
        'is_active' => true,
        'display_order' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tourId = DB::table('tours')->insertGetId([
        'partner_id' => $partnerId,
        'category_id' => $categoryId,
        'slug' => 'rehearsal-existing-tour',
        'location' => 'Rome, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 12,
        'price_amount' => 6000,
        'status' => 'published',
        'source_locale' => 'en',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('tour_translations')->insert([
        'tour_id' => $tourId,
        'locale' => 'en',
        'title' => 'Historical Rome Walking Tour',
        'description' => 'Existing pre-EGM-005 published tour description.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $optionId = DB::table('tour_options')->insertGetId([
        'tour_id' => $tourId,
        'title' => 'Morning Option',
        'price_amount' => 6000,
        'group_size_max' => 12,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tierId = DB::table('pricing_tiers')->insertGetId([
        'tour_id' => $tourId,
        'name' => 'Standard',
        'min_participants' => 1,
        'max_participants' => 12,
        'price' => '60.00',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $draftId = DB::table('tour_drafts')->insertGetId([
        'partner_id' => $partnerId,
        'tour_id' => null,
        'status' => 'draft',
        'payload' => json_encode(['title' => 'Historical Draft Tour'], JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $bookingReference = 'BKO-REH001';
    $bookingId = DB::table('bookings')->insertGetId([
        'reference' => $bookingReference,
        'traveler_id' => $travelerUserId,
        'tour_id' => $tourId,
        'tour_option_id' => $optionId,
        'tour_date' => now()->addDays(10)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 6000,
        'total_price' => 12000,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => (string) Str::uuid(),
        'cancellation_policy' => 'Free cancellation up to 24 hours before start.',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
        'payment_confirmed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $assertExistingDataIntact = static function (string $stage) use ($fail, $tourId, $optionId, $tierId, $draftId, $bookingId, $bookingReference): void {
        $tour = DB::table('tours')->where('id', $tourId)->first();
        $option = DB::table('tour_options')->where('id', $optionId)->first();
        $tier = DB::table('pricing_tiers')->where('id', $tierId)->first();
        $draft = DB::table('tour_drafts')->where('id', $draftId)->first();
        $booking = DB::table('bookings')->where('id', $bookingId)->first();

        if (! $tour || $tour->slug !== 'rehearsal-existing-tour' || (int) $tour->price_amount !== 6000) {
            $fail("[{$stage}] Historical tour row missing or altered.");
        }
        if (! $option || $option->title !== 'Morning Option') {
            $fail("[{$stage}] Historical tour_option row missing or altered.");
        }
        if (! $tier || (string) $tier->price !== '60.00') {
            $fail("[{$stage}] Historical pricing_tier row missing or altered.");
        }
        if (! $draft || $draft->status !== 'draft') {
            $fail("[{$stage}] Historical tour_drafts row missing or altered.");
        }
        if (! $booking || $booking->reference !== $bookingReference || (int) $booking->total_price !== 12000 || $booking->status !== 'confirmed') {
            $fail("[{$stage}] Historical confirmed booking row missing or altered.");
        }
    };

    $assertEgm005Invariants = static function (string $stage) use ($fail, $egm005Migrations): void {
        if (! Schema::hasTable('tour_departures')) {
            $fail("[{$stage}] Expected tour_departures table to exist.");
        }

        foreach ($egm005Migrations as $migration) {
            if (! DB::table('migrations')->where('migration', $migration)->exists()) {
                $fail("[{$stage}] Expected migration [{$migration}] to be applied.");
            }
        }

        $indexes = collect(DB::select(
            "select indexname from pg_indexes where schemaname = current_schema() and tablename in ('tour_drafts', 'bookings', 'tour_departures')"
        ))->pluck('indexname')->all();

        if (! in_array('tour_drafts_partner_id_creation_id_unique', $indexes, true)) {
            $fail("[{$stage}] Missing PostgreSQL unique index tour_drafts_partner_id_creation_id_unique on tour_drafts.");
        }
        if (! in_array('bookings_departure_status_expiry_idx', $indexes, true)) {
            $fail("[{$stage}] Missing PostgreSQL index bookings_departure_status_expiry_idx on bookings.");
        }

        $capacityCheck = DB::selectOne(
            "select conname from pg_constraint where conrelid = 'tour_departures'::regclass and contype = 'c' and conname = 'tour_departures_capacity_check'"
        );
        if (! $capacityCheck) {
            $fail("[{$stage}] Missing PostgreSQL constraint tour_departures_capacity_check on tour_departures.");
        }
    };

    // 3. UP #1: apply full EGM-005 batch on top of existing data.
    $runArtisan('migrate', ['--force' => true]);
    $assertExistingDataIntact('UP #1');
    $assertEgm005Invariants('UP #1');
    echo "[rehearse-migrations] UP #1 passed — existing data preserved and all EGM-005 PostgreSQL invariants present.\n";

    // 4. Full-batch DOWN: roll back ENTIRE Batch 2 (NO --step=1!).
    $runArtisan('migrate:rollback', ['--force' => true]);

    if (Schema::hasTable('tour_departures')) {
        $fail('[DOWN] Expected tour_departures table to be dropped after full-batch rollback.');
    }
    foreach ($egm005Migrations as $migration) {
        if (DB::table('migrations')->where('migration', $migration)->exists()) {
            $fail("[DOWN] Migration [{$migration}] was NOT rolled back by full-batch migrate:rollback.");
        }
    }
    $assertExistingDataIntact('DOWN');
    echo "[rehearse-migrations] Full-batch DOWN passed — all 4 EGM-005 migrations rolled back with zero historical data loss.\n";

    // 5. UP #2: re-apply full EGM-005 batch cleanly.
    $runArtisan('migrate', ['--force' => true]);
    $assertExistingDataIntact('UP #2');
    $assertEgm005Invariants('UP #2');
    echo "[rehearse-migrations] UP #2 passed — migration rehearsal succeeded completely.\n";
} catch (Throwable $e) {
    $fail($e::class . ': ' . $e->getMessage());
}

exit(0);
