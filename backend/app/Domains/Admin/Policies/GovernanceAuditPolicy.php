<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\User;

/**
 * Read-only policy for the unified governance audit trail (Spec 013, US6).
 * Viewing requires the `view_audit_log` flag; no create/edit/delete is ever
 * permitted — the trail is append-only (FR-012).
 */
class GovernanceAuditPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'view_audit_log');
    }

    public function view(User $user, GovernanceAuditLog $log): bool
    {
        return $this->authz->can($user, 'view_audit_log');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GovernanceAuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, GovernanceAuditLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
