<?php

namespace App\Domains\Blog\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function create(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function deleteAny(User $user): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function restore(User $user, BlogPost $post): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }

    public function forceDelete(User $user, BlogPost $post): bool
    {
        return $this->authz->can($user, 'manage_blog');
    }
}
