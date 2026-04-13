<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_identities', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('converted_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_identities');
    }
};