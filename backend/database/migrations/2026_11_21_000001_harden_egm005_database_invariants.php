<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EGM-005 Wave 3A — additive database invariants (D2, D3, D4).
 *
 * 1. Pre-flight data checks fail closed if duplicate `(partner_id, creation_id)`
 *    rows or invalid `tour_departures.capacity < 1` rows exist.
 * 2. Adds dedicated `tour_drafts.creation_payload_hash` (64 chars, nullable) so
 *    first-save payload identity is stored independently of mutable draft JSON.
 * 3. Adds partial unique index `tour_drafts_partner_id_creation_id_unique` on
 *    `(partner_id, creation_id) WHERE creation_id IS NOT NULL` to enforce D2
 *    partner-scoped idempotency at PostgreSQL level under concurrent saves.
 * 4. Enforces `capacity >= 1` CHECK constraint on `tour_departures` (PostgreSQL).
 * 5. Adds partial composite index `bookings_departure_status_expiry_idx` on
 *    `(tour_departure_id, status, pending_expires_at) WHERE tour_departure_id IS NOT NULL`
 *    matching `TourDeparture::reservedCapacity()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicateCreationRows = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM ('
            . 'SELECT partner_id, creation_id '
            . 'FROM tour_drafts '
            . 'WHERE creation_id IS NOT NULL '
            . 'GROUP BY partner_id, creation_id '
            . 'HAVING COUNT(*) > 1'
            . ') AS dups'
        );

        if ((int) ($duplicateCreationRows->cnt ?? 0) > 0) {
            throw new RuntimeException(
                'Cannot enforce D2 uniqueness: duplicate non-null (partner_id, creation_id) rows exist in tour_drafts.'
            );
        }

        $invalidCapacities = DB::table('tour_departures')
            ->where('capacity', '<', 1)
            ->count();

        if ($invalidCapacities > 0) {
            throw new RuntimeException(
                'Cannot enforce capacity >= 1 CHECK constraint: tour_departures contains rows with capacity < 1.'
            );
        }

        Schema::table('tour_drafts', function (Blueprint $table) {
            $table->string('creation_payload_hash', 64)->nullable();
        });

        DB::statement(
            'CREATE UNIQUE INDEX tour_drafts_partner_id_creation_id_unique '
            . 'ON tour_drafts (partner_id, creation_id) '
            . 'WHERE creation_id IS NOT NULL'
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE tour_departures '
                . 'ADD CONSTRAINT tour_departures_capacity_check CHECK (capacity >= 1)'
            );
        }

        DB::statement(
            'CREATE INDEX bookings_departure_status_expiry_idx '
            . 'ON bookings (tour_departure_id, status, pending_expires_at) '
            . 'WHERE tour_departure_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bookings_departure_status_expiry_idx');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE tour_departures DROP CONSTRAINT IF EXISTS tour_departures_capacity_check'
            );
        }

        DB::statement('DROP INDEX IF EXISTS tour_drafts_partner_id_creation_id_unique');

        Schema::table('tour_drafts', function (Blueprint $table) {
            $table->dropColumn('creation_payload_hash');
        });
    }
};
