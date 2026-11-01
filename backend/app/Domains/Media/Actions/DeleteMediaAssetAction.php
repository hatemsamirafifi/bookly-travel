<?php

namespace App\Domains\Media\Actions;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Services\MediaUsageTrackerService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Delete-safety for the media library (Spec 035, T-D03).
 *
 * Assets referenced by content records cannot be deleted without the
 * force flag, and forcing requires the `delete_media` (or `manage_media`)
 * governance permission. Every branch writes an immutable audit entry.
 */
class DeleteMediaAssetAction
{
    public function __construct(
        private readonly MediaUsageTrackerService $tracker,
        private readonly AdminAuthorizationService $authz,
        private readonly GovernanceAuditService $audit,
    ) {}

    /**
     * @param  Collection<int, \App\Domains\Media\Models\MediaUsage>|null  $usages  Pre-loaded usage details.
     */
    public function perform(
        MediaAsset $asset,
        ?User $actor = null,
        bool $force = false,
        bool $permanent = false,
        ?Collection $usages = null,
    ): void {
        $usageCount = $this->tracker->usageCount($asset);

        if ($usageCount > 0 && $force === false) {
            throw ValidationException::withMessages([
                'media_asset' => [
                    "Cannot delete media asset while it is referenced by {$usageCount} content record(s).",
                ],
            ]);
        }

        if ($force) {
            $allowed = $actor !== null
                && ($this->authz->can($actor, 'delete_media') || $this->authz->can($actor, 'manage_media'));

            if (! $allowed) {
                throw new AuthorizationException('You do not have permission to force-delete media assets.');
            }
        }

        $references = ($usages ?? $this->tracker->usagesFor($asset))
            ->map(fn ($usage) => $usage->only(['usable_type', 'usable_id', 'field_name']))
            ->values()
            ->all();

        if ($force) {
            $this->removeUsages($asset);
        }

        if ($permanent) {
            $asset->forceDelete();
        } else {
            $asset->delete();
        }

        $action = $force || $permanent ? 'media.force_delete' : 'media.delete';

        $metadata = [
            'storage_key' => $asset->storage_key,
            'mime_type' => $asset->mime_type,
            'usage_count' => $usageCount,
            'references' => $references,
            'permanent' => $permanent,
        ];

        if ($actor !== null) {
            $this->audit->log($actor, $action, $asset, null, $metadata, $metadata);
        } else {
            $this->audit->logSystem($action, $asset, null, $metadata, $metadata);
        }
    }

    private function removeUsages(MediaAsset $asset): void
    {
        $asset->usages()->delete();
    }
}
