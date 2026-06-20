<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Models\User;

/**
 * Per-action gate for booking oversight (Spec 013, FR-002).
 * Booking status transitions require the `manage_bookings` flag.
 */
class BookingPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz)
    {
    }

    private function can(User $user): bool
    {
        return $this->authz->can($user, 'manage_bookings');
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->can($user);
    }

    public function transition(User $user, Booking $booking): bool
    {
        return $this->can($user);
    }
}