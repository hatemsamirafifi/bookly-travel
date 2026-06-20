<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\Tour;
use App\Models\User;

/**
 * Read-only availability oversight policy (Spec 013, US8, FR-014).
 * Viewing requires the `manage_bookings` flag; no mutation is ever permitted
 * (availability is partner-owned).
 */
class AvailabilityPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_bookings');
    }

    public function view(User $user, Tour $tour): bool
    {
        return $this->authz->can($user, 'manage_bookings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tour $tour): bool
    {
        return false;
    }

    public function delete(User $user, Tour $tour): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}