<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->string('actor_type', 20);
            $table->bigInteger('actor_id')->nullable();
            $table->string('action', 30);
            $table->string('before_state', 20)->nullable();
            $table->string('after_state', 20);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['booking_id', 'created_at'], 'booking_audit_logs_booking_id_created_at_idx');
            $table->index(['actor_type', 'actor_id'], 'booking_audit_logs_actor_type_actor_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_audit_logs');
    }
};
