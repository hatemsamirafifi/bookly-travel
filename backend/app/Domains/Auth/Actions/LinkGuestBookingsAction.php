<?php

namespace App\Domains\Auth\Actions;

use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinkGuestBookingsAction
{
    /**
     * Link guest bookings to the newly registered user.
     *
     * @param User $user The newly registered user
     * @return void
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Find all guest identities matching the user's email
            $guestIdentities = GuestIdentity::where('email', $user->email)->get();

            if ($guestIdentities->isEmpty()) {
                return;
            }

            // Update guest_identities to mark them as converted
            GuestIdentity::where('email', $user->email)
                ->update(['converted_user_id' => $user->id]);

            // If the bookings table exists, link the bookings to the new user
            if (Schema::hasTable('bookings')) {
                DB::table('bookings')
                    ->whereIn('guest_id', $guestIdentities->pluck('id'))
                    ->update(['user_id' => $user->id]);
            }
        });
    }
}