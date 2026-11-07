<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\CMS\Models\ContentRevision;
use App\Models\User;

/**
 * Content revision history access policy (Admin 2.0 Phase G, T-G03).
 *
 * Reading history requires any of the governance flags: `manage_revisions`,
 * `manage_cms`, or `manage_blog`.
 */
class ContentRevisionPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_revisions')
            || $this->authz->can($user, 'manage_cms')
            || $this->authz->can($user, 'manage_blog');
    }

    public function view(User $user, ContentRevision $revision): bool
    {
        return $this->viewAny($user);
    }
}
