<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    public function up(): void
    {
        if (! $this->isSqlite()) {
            DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_traveler_id_foreign');
            DB::statement('ALTER TABLE reviews ALTER COLUMN traveler_id DROP NOT NULL');
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_traveler_id_foreign FOREIGN KEY (traveler_id) REFERENCES users(id) ON DELETE SET NULL');

            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_traveler_id_foreign');
            DB::statement('ALTER TABLE bookings ALTER COLUMN traveler_id DROP NOT NULL');
            DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_traveler_id_foreign FOREIGN KEY (traveler_id) REFERENCES users(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (! $this->isSqlite()) {
            DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_traveler_id_foreign');
            DB::statement('ALTER TABLE reviews ALTER COLUMN traveler_id SET NOT NULL');
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_traveler_id_foreign FOREIGN KEY (traveler_id) REFERENCES users(id)');

            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_traveler_id_foreign');
            DB::statement('ALTER TABLE bookings ALTER COLUMN traveler_id SET NOT NULL');
            DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_traveler_id_foreign FOREIGN KEY (traveler_id) REFERENCES users(id)');
        }
    }
};
