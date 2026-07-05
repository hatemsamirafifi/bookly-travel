<?php

namespace Database\Seeders;

use App\Domains\Admin\Models\AdminPermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default admin user with full governance permission flags.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@bookly.test'],
            [
                'name' => 'Bookly Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'locale' => 'en',
            ]
        );

        $flags = [
            'manage_tours' => true,
            'manage_partners' => true,
            'manage_bookings' => true,
            'moderate_reviews' => true,
            'view_all_analytics' => true,
            'manage_users' => true,
            'manage_settings' => true,
            'manage_cms' => true,
            'view_audit_log' => true,
        ];

        $admin->adminPermission()->updateOrCreate([], ['flags' => $flags]);
    }
}