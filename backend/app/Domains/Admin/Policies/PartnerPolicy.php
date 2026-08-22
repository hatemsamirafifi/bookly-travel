<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Partner\Models\Partner;
use App\Models\User;

/**
 * Per-action gate for the partner lifecycle (Spec 013, FR-002).
 * All partner governance actions require the `manage_partners` flag.
 */
class PartnerPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    private function can(User $user): bool
    {
        return $this->authz->can($user, 'manage_partners');
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $this->can($user);
    }

    public function approve(User $user, Partner $partner): bool
    {
        return $this->can($user);
    }

    public function reject(User $user, Partner $partner): bool
    {
        return $this->can($user);
    }

    public function suspend(User $user, Partner $partner): bool
    {
        return $this->can($user);
    }

    public function unsuspend(User $user, Partner $partner): bool
    {
        return $this->can($user);
    }
}
