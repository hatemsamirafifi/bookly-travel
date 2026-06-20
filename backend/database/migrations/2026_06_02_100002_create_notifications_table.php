<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')
                ->constrained('partners')
                ->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 255);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'read_at'], 'notifications_partner_read_idx');
            $table->index(['partner_id', 'created_at'], 'notifications_partner_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
