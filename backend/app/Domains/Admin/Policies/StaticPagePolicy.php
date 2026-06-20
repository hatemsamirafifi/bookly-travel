<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\User;

/**
 * Static-page / CMS governance policy (Spec 013, US9, FR-015, ST-013-012/013).
 * All management actions require the `manage_cms` flag.
 */
class StaticPagePolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }

    public function view(User $user, StaticPage $page): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }

    public function create(User $user): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }

    public function update(User $user, StaticPage $page): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }

    public function delete(User $user, StaticPage $page): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }

    public function deleteAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_cms');
    }
}