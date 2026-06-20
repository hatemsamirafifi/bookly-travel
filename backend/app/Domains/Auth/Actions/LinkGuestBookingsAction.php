<?php

namespace App\Domains\Auth\Actions;

use App\Models\AuthAuditLog;
use App\Models\GuestIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinkGuestBookingsAction
{
    /**
     * Link guest bookings to the newly registered user.
     *
     * @param  User  $user  The newly registered user
     * @return int Number of bookings linked
     */
    public function execute(User $user, ?string $ipAddress = null, ?string $userAgent = null): int
    {
        return DB::transaction(function () use ($user, $ipAddress, $userAgent) {
            // Find all guest identities matching the user's email that are NOT yet converted
            $guestIdentities = GuestIdentity::where('email', $user->email)
                ->whereNull('converted_user_id')
                ->get();

            if ($guestIdentities->isEmpty()) {
                return 0;
            }

            $guestIdentityIds = $guestIdentities->pluck('id')->toArray();

            // Mark identities as converted (guards re-linking)
            GuestIdentity::whereIn('id', $guestIdentityIds)
                ->update(['converted_user_id' => $user->id]);

            $linkedCount = 0;
            $linkedBookingIds = [];

            // If the bookings table exists, link the bookings idempotently
            if (Schema::hasTable('bookings')) {
                $linkedBookingIds = DB::table('bookings')
                    ->whereIn('guest_identity_id', $guestIdentityIds)
                    ->whereNull('traveler_id')
                    ->pluck('id')
                    ->toArray();

                if (! empty($linkedBookingIds)) {
                    $linkedCount = DB::table('bookings')
                        ->whereIn('id', $linkedBookingIds)
                        ->update(['traveler_id' => $user->id]);
                }
            }

            // Emit audit log
            AuthAuditLog::create([
                'user_id' => $user->id,
                'event_type' => 'guest_bookings_linked',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => [
                    'guest_identity_ids' => $guestIdentityIds,
                    'linked_booking_ids' => $linkedBookingIds,
                ],
            ]);

            return $linkedCount;
        });
    }
}
