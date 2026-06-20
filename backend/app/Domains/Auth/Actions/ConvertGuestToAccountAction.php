<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\GuestConvertedToAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ConvertGuestToAccountAction
{
    /**
     * Convert a guest identity into a full traveler account.
     *
     * @return array ['user' => User, 'token' => string, 'linked_bookings_count' => int]
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Step 1: Create User
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->locale = $data['locale'] ?? 'en';
            $user->save();

            // Step 2: Link all guest bookings by email
            $linkedCount = app(LinkGuestBookingsAction::class)->execute(
                $user,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null
            );

            // Step 3: Dispatch conversion event
            event(new GuestConvertedToAccount($user));

            // Step 4: Queue verification email after commit
            DB::afterCommit(function () use ($user) {
                app(SendVerificationEmailAction::class)->execute($user);
            });

            // Step 5: Create Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'linked_bookings_count' => $linkedCount,
            ];
        });
    }
}
