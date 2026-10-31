<?php

namespace App\Domains\Admin\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
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

    public function view(User $user, Category $category): bool
    {
        return $this->can($user);
    }

    public function create(User $user): bool
    {
        return $this->can($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->can($user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->can($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user);
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->can($user);
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $this->can($user);
    }
}
