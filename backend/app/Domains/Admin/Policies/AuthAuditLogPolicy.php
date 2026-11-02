<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\AuthAuditLog;
use App\Models\User;

/**
 * Read-only policy for authentication audit logs.
 * Viewing requires the `view_audit_log` flag.
 */
class AuthAuditLogPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'view_audit_log');
    }

    public function view(User $user, AuthAuditLog $log): bool
    {
        return $this->authz->can($user, 'view_audit_log');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuthAuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuthAuditLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
