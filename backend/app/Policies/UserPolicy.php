<?php

namespace App\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the given user can view their own account.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can update their own account.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can delete their own account.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can manage their own sessions.
     */
    public function manageSessions(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can change their own password.
     */
    public function changePassword(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Check if the user has the given role.
     */
    public function hasRole(User $user, string $role): bool
    {
        return $user->role === $role;
    }

    /**
     * Check if the user has the given permission.
     *
     * Admins: delegated to AdminAuthorizationService, which resolves the
     * per-action flags persisted on the admin_permissions row (Spec 013,
     * data-model.md §2). Travelers/partners keep the static role map.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        if ($user->role === 'admin') {
            return app(AdminAuthorizationService::class)->can($user, $permission);
        }

        $rolePermissions = [
            'traveler' => [
                'book_tour',
                'cancel_booking',
                'write_review',
                'manage_wishlist',
                'view_own_profile',
                'update_own_profile',
            ],
            'partner' => [
                'create_tour',
                'update_tour',
                'view_partner_bookings',
                'view_partner_analytics',
                'respond_to_review',
            ],
        ];

        $permissions = $rolePermissions[$user->role] ?? [];

        return in_array($permission, $permissions, true);
    }
}
