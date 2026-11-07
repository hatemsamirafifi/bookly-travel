<?php

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Models\AdminRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central admin authorization (Spec 013, extended by Spec 035 T-A02).
 *
 * A user is an active admin when `role = admin` and `status = active`. Their
 * effective permissions are the union of the direct `admin_permissions.flags`
 * map and every permission granted by their assigned `admin_roles` templates.
 * The `super_admin` role is a wildcard that grants every flag.
 */
class AdminAuthorizationService
{
    public const FLAGS = [
        'manage_tours',
        'manage_partners',
        'manage_bookings',
        'moderate_reviews',
        'view_all_analytics',
        'manage_users',
        'manage_settings',
        'manage_cms',
        'manage_blog',
        'view_audit_log',
        'manage_roles',
        'manage_travelers',
        'view_finances',
        'export_financial_reports',
        'manage_media',
        'manage_files',
        'delete_media',
        'manage_translations',
        'manage_revisions',
        'view_system_health',
    ];

    public function can(User $user, string $permission): bool
    {
        if ($user->role !== 'admin' || ($user->status ?? 'active') !== 'active') {
            return false;
        }

        return $this->effectivePermissions($user)[$permission] ?? false;
    }

    /**
     * Effective permission map across all known flags.
     *
     * @return array<string, bool>
     */
    public function effectivePermissions(User $user): array
    {
        $effective = array_fill_keys(self::FLAGS, false);

        $direct = $user->adminPermission->flags ?? [];

        foreach (self::FLAGS as $flag) {
            if (! empty($direct[$flag])) {
                $effective[$flag] = true;
            }
        }

        foreach ($this->rolesFor($user) as $role) {
            if ($role->slug === 'super_admin') {
                return array_fill_keys(self::FLAGS, true);
            }

            $permissions = $role->permissions ?? [];

            foreach (self::FLAGS as $flag) {
                if (! empty($permissions[$flag])) {
                    $effective[$flag] = true;
                }
            }
        }

        return $effective;
    }

    /**
     * A super admin either carries the `super_admin` role or holds every flag.
     */
    public function isSuperAdmin(User $user): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        foreach ($this->rolesFor($user) as $role) {
            if ($role->slug === 'super_admin') {
                return true;
            }
        }

        return ! in_array(false, $this->effectivePermissions($user), true);
    }

    /**
     * @return Collection<int, AdminRole>
     */
    private function rolesFor(User $user): Collection
    {
        $user->loadMissing('adminRoles');

        return $user->adminRoles;
    }
}
