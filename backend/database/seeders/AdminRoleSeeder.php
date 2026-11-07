<?php

namespace Database\Seeders;

use App\Domains\Admin\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Idempotently seeds or refreshes the canonical Admin 2.0 system roles.
     */
    public function run(): void
    {
        AdminRole::ensureSystemRoles();
    }
}
