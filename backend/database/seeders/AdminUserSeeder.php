<?php

namespace Database\Seeders;

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
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
                'locale' => 'en',
            ]
        );

        // 'role' is not in User::$fillable; set it explicitly to avoid silent
        // mass-assignment discard (User::canAccessPanel checks role === 'admin').
        $admin->role = 'admin';
        $admin->save();

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
