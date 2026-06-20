<?php

namespace App\Domains\Admin\Services;

use App\Models\User;

/**
 * Resolves per-action admin permissions against persisted flags (Spec 013,
 * data-model.md §2).
 *
 * `can()` returns true only for users whose role is `admin` AND whose
 * AdminPermission row has the requested flag set true. Non-admins always
 * resolve false here; the role-based traveler/partner permissions remain
 * handled by UserPolicy::hasPermission() which delegates to this service for
 * the admin branch.
 */
class AdminAuthorizationService
{
    /**
     * The canonical per-admin permission flag inventory (data-model.md §2).
     */
    public const FLAGS = [
        'manage_tours',
        'manage_partners',
        'manage_bookings',
        'moderate_reviews',
        'view_all_analytics',
        'manage_users',
        'manage_settings',
        'manage_cms',
        'view_audit_log',
    ];

    public function can(User $user, string $permission): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        $flags = $user->adminPermission?->flags ?? [];

        return (bool) ($flags[$permission] ?? false);
    }
}