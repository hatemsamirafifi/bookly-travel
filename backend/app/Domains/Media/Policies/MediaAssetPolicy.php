<?php

namespace App\Domains\Media\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    private function grants(User $user): bool
    {
        return $this->authz->can($user, 'manage_media')
            || $this->authz->can($user, 'manage_cms')
            || $this->authz->can($user, 'manage_blog');
    }

    public function viewAny(User $user): bool
    {
        return $this->grants($user);
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->grants($user);
    }

    public function create(User $user): bool
    {
        return $this->grants($user);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $this->grants($user);
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $this->grants($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->grants($user);
    }

    public function restore(User $user, MediaAsset $asset): bool
    {
        return $this->authz->can($user, 'manage_media');
    }

    public function forceDelete(User $user, MediaAsset $asset): bool
    {
        return $this->authz->can($user, 'manage_media') || $this->authz->can($user, 'delete_media');
    }
}
