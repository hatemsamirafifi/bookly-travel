<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix tours.partner_id to reference the partners table (not users).
     *
     * The original migration constrained tours.partner_id to users.id, but the
     * Partner domain uses a separate `partners` table whose primary key is
     * the value PartnerRoleMiddleware injects as `partner_id` in request
     * attributes. This migration updates the foreign key to match the
     * application code (TourService, controllers) which pass the Partner's id.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreign('partner_id')
                ->references('id')
                ->on('partners');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreign('partner_id')
                ->references('id')
                ->on('users');
        });
    }
};
