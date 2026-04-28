<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\TravelerRegistered;
use App\Models\User;

class RegisterTravelerAction
{
    /**
     * Register a new traveler account.
     *
     * @param array $data Validated registration data (name, email, password, locale)
     * @return array ['user' => User, 'token' => string]
     */
    public function execute(array $data): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            // Step 1: Create User with role defaults to 'traveler'
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
            ]);

            // Step 2: Link guest bookings to the new user
            app(LinkGuestBookingsAction::class)->execute($user);

            // Step 3: Dispatch TravelerRegistered event for audit logging
            event(new TravelerRegistered($user));

            // Step 4: Create and return Sanctum token
            $tokenResult = $user->createToken('auth-token');
            $plainTextToken = $tokenResult->plainTextToken;

            // Step 5: Send verification email after successful transaction commit
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($user) {
                app(SendVerificationEmailAction::class)->execute($user);
            });

            return [
                'user' => $user,
                'token' => $plainTextToken,
            ];
        });
    }
}