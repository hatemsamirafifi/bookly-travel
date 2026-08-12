<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\Tour;
use App\Models\User;

/**
 * Per-action gate for tour moderation (Spec 013, FR-002).
 *
 * Filament resolves these methods from the resource action names. All tour
 * governance actions require the `manage_tours` flag; an admin without it
 * sees the resource and its actions hidden (FR-002 scenario 4 — hidden, not
 * server-rejected).
 */
class TourPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    private function can(User $user): bool
    {
        return $this->authz->can($user, 'manage_tours');
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user);
    }

    public function view(User $user, Tour $tour): bool
    {
        return $this->can($user);
    }

    public function publish(User $user, Tour $tour): bool
    {
        return $this->can($user);
    }

    public function reject(User $user, Tour $tour): bool
    {
        return $this->can($user);
    }

    public function unpublish(User $user, Tour $tour): bool
    {
        return $this->can($user);
    }

    public function bulkPublish(User $user): bool
    {
        return $this->can($user);
    }

    public function bulkReject(User $user): bool
    {
        return $this->can($user);
    }
}
