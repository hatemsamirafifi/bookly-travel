<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default admin user with full governance permission flags.
     *
     * In non-local / non-testing environments (staging, production), this seeder
     * strictly refuses to execute unless a secure ADMIN_PASSWORD (minimum 12 chars,
     * not the default 'password') and a valid ADMIN_EMAIL (not ending in '@bookly.test')
     * are explicitly supplied via environment variables.
     */
    public function run(): void
    {
        $isLocalOrTesting = app()->environment('local', 'testing');
        $email = env('ADMIN_EMAIL', 'admin@bookly.test');
        $password = env('ADMIN_PASSWORD');

        if (! $isLocalOrTesting) {
            if (empty($password) || $password === 'password' || strlen($password) < 12) {
                throw new RuntimeException(
                    'AdminUserSeeder refused: Cannot seed admin user in non-local environment without a secure ADMIN_PASSWORD (minimum 12 characters, not "password").'
                );
            }
            if (empty($email) || str_ends_with($email, '@bookly.test')) {
                throw new RuntimeException(
                    'AdminUserSeeder refused: Cannot seed admin user in non-local environment with a test domain (@bookly.test). Please configure a production ADMIN_EMAIL.'
                );
            }
        } else {
            $password = $password ?: 'password';
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Bookly Admin'),
                'password' => Hash::make($password),
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
            'manage_blog' => true,
            'view_audit_log' => true,
        ];

        $admin->adminPermission()->updateOrCreate([], ['flags' => $flags]);
    }
}
