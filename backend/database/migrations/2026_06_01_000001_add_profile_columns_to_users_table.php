<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable()->after('name');
            $table->string('last_name', 255)->nullable()->after('first_name');
            $table->string('phone', 50)->nullable()->after('last_name');
            $table->string('preferred_currency', 3)->default('EUR')->after('locale');
            $table->boolean('marketing_emails')->default(false)->after('preferred_currency');
            $table->string('avatar_url', 2048)->nullable()->after('marketing_emails');

            $table->index(['first_name', 'last_name'], 'users_name_idx');
        });

        $this->backfillNames();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_idx');
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'preferred_currency',
                'marketing_emails',
                'avatar_url',
            ]);
        });
    }

    private function backfillNames(): void
    {
        $users = DB::table('users')
            ->whereNull('first_name')
            ->whereNull('last_name')
            ->select(['id', 'name'])
            ->get();

        foreach ($users as $user) {
            $name = trim((string) $user->name);
            $firstName = Str::beforeLast($name, ' ');
            $lastName = Str::afterLast($name, ' ');

            if ($firstName === $lastName) {
                $lastName = null;
            }

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
        }
    }
};
