<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('company_name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->foreignId('invited_by_admin_id')->constrained('users');
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->timestamps();

            $table->index(['email', 'status'], 'partner_invitations_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_invitations');
    }
};
