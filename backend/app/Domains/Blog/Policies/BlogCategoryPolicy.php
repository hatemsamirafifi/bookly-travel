<?php

namespace App\Domains\Blog\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Blog\Models\BlogCategory;
use App\Models\User;

class BlogCategoryPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function view(User $user, BlogCategory $category): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function create(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function update(User $user, BlogCategory $category): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function delete(User $user, BlogCategory $category): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function deleteAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function restore(User $user, BlogCategory $category): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function forceDelete(User $user, BlogCategory $category): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }
}
