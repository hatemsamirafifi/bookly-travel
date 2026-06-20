<?php

namespace App\Domains\Auth\Services;

use App\Models\GuestIdentity;
use App\Models\User;

class AuthService
{
    /**
     * Find an existing guest identity by email, or create a new one.
     */
    public function findOrCreateGuestIdentity(string $email, string $name, ?string $phone = null): GuestIdentity
    {
        $normalizedEmail = strtolower(trim($email));

        $existing = GuestIdentity::where('email', $normalizedEmail)
            ->whereNull('converted_user_id')
            ->whereNull('anonymized_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return GuestIdentity::create([
            'email' => $normalizedEmail,
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * Check whether a registered account exists for the given email.
     */
    public function checkExistingAccount(string $email): bool
    {
        $normalizedEmail = strtolower(trim($email));

        return User::where('email', $normalizedEmail)->exists();
    }
}
