<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('guest_identity_id')
                ->nullable()
                ->after('traveler_id')
                ->constrained('guest_identities')
                ->nullOnDelete();

            $table->index('guest_identity_id', 'bookings_guest_identity_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['guest_identity_id']);
            $table->dropIndex('bookings_guest_identity_id_idx');
            $table->dropColumn('guest_identity_id');
        });
    }
};
