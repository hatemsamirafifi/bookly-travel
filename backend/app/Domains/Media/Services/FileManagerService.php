<?php

namespace App\Domains\Media\Services;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Media\Jobs\ProcessMediaAssetJob;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaFolder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Folder tree and asset lifecycle operations for the File Manager (T-F01).
 *
 * Paths are canonical slugs with a leading slash (e.g. `/marketing/campaigns`).
 * Every mutation — create, rename, move, delete, copy, restore — writes a
 * `file_manager.*` entry into the governance audit log.
 */
class FileManagerService
{
    public function __construct(
        private readonly GovernanceAuditService $audit,
    ) {}

    public function createFolder(User $actor, string $name, ?int $parentId = null): MediaFolder
    {
        $name = $this->sanitizeFolderName($name);
        $slug = (string) Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages(['folder' => ['A valid folder name is required.']]);
        }

        $parent = $parentId !== null ? MediaFolder::query()->findOrFail($parentId) : null;
        $path = ($parent?->path ?? '') . '/' . $slug;

        $exists = MediaFolder::query()
            ->where('parent_id', $parent?->getKey())
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['folder' => ["A folder named '{$slug}' already exists here."]]);
        }

        $folder = MediaFolder::create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->log($actor, 'file_manager.folder_create', $folder, null, [
            'name' => $folder->name,
            'path' => $folder->path,
            'parent_id' => $folder->parent_id,
        ]);

        return $folder;
    }

    public function renameFolder(User $actor, MediaFolder $folder, string $newName): MediaFolder
    {
        $name = $this->sanitizeFolderName($newName);
        $slug = (string) Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages(['folder' => ['A valid folder name is required.']]);
        }

        $taken = MediaFolder::query()
            ->where('parent_id', $folder->parent_id)
            ->where('slug', $slug)
            ->whereKeyNot($folder->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['folder' => ["A folder named '{$slug}' already exists here."]]);
        }

        $before = ['name' => $folder->name, 'slug' => $folder->slug, 'path' => $folder->path];

        $oldPath = $folder->path;
        $newParentPath = $this->parentPath($folder);

        $descendantPaths = $this->descendantPaths($folder);

        $folder->update(['name' => $name, 'slug' => $slug, 'path' => $newParentPath . '/' . $slug]);

        $this->rewriteDescendantPaths($descendantPaths, $oldPath, $folder->path);

        $this->audit->log($actor, 'file_manager.folder_rename', $folder, $before, [
            'name' => $folder->name,
            'slug' => $folder->slug,
            'path' => $folder->path,
        ]);

        return $folder->refresh();
    }

    public function moveFolder(User $actor, MediaFolder $folder, ?int $newParentId): MediaFolder
    {
        $newParent = $newParentId !== null ? MediaFolder::query()->findOrFail($newParentId) : null;

        if ($newParent !== null) {
            if ((int) $newParent->getKey() === (int) $folder->getKey()
                || str_starts_with($newParent->path . '/', $folder->path . '/')
            ) {
                throw ValidationException::withMessages([
                    'folder' => ['A folder cannot be moved into itself or one of its descendants.'],
                ]);
            }
        }

        $before = ['parent_id' => $folder->parent_id, 'path' => $folder->path];

        $oldPath = $folder->path;
        $newPath = ($newParent?->path ?? '') . '/' . $folder->slug;

        $descendantPaths = $this->descendantPaths($folder);

        $folder->update(['parent_id' => $newParent?->getKey(), 'path' => $newPath]);

        $this->rewriteDescendantPaths($descendantPaths, $oldPath, $folder->path);

        $this->audit->log($actor, 'file_manager.folder_move', $folder, $before, [
            'parent_id' => $folder->parent_id,
            'path' => $folder->path,
        ]);

        return $folder->refresh();
    }

    public function deleteFolder(User $actor, MediaFolder $folder): void
    {
        if ($folder->children()->exists() || $folder->assets()->exists()) {
            throw ValidationException::withMessages([
                'folder' => ["The folder '{$folder->path}' is not empty. Remove or move its contents first."],
            ]);
        }

        $snapshot = ['path' => $folder->path, 'name' => $folder->name];

        $folder->delete();

        $this->audit->log($actor, 'file_manager.folder_delete', $folder, $snapshot, null, [], 'media_folder');
    }

    public function moveAsset(User $actor, MediaAsset $asset, ?int $targetFolderId): MediaAsset
    {
        $target = $targetFolderId !== null
            ? MediaFolder::query()->findOrFail($targetFolderId)
            : null;

        $before = ['folder_id' => $asset->folder_id, 'storage_key' => $asset->storage_key];
        $targetId = $target?->getKey();

        $asset->forceFill([
            'folder_id' => $targetId,
            'disk' => $asset->disk,
            'storage_key' => $asset->storage_key,
        ])->save();

        $this->audit->log($actor, 'file_manager.asset_move', $asset, $before, [
            'folder_id' => $targetId,
            'storage_key' => $asset->storage_key,
        ]);

        return $asset->refresh();
    }

    public function copyAsset(User $actor, MediaAsset $asset, ?int $targetFolderId = null): MediaAsset
    {
        $target = $targetFolderId !== null
            ? MediaFolder::query()->findOrFail($targetFolderId)
            : $asset->folder;

        $disk = Storage::disk($asset->disk);

        $uuid = (string) Str::uuid();
        $targetId = $target?->getKey();
        $storageKey = (($target?->path ?? '/uploads') . '/' . $uuid . '.' . $asset->extension);
        $storageKey = '/' . ltrim(str_replace('\\', '/', $storageKey), '/');

        $disk->copy($asset->storage_key, $storageKey);

        $copy = MediaAsset::create([
            'uuid' => $uuid,
            'folder_id' => $targetId,
            'disk' => $asset->disk,
            'storage_key' => $storageKey,
            'original_filename' => $asset->original_filename,
            'filename' => $asset->filename,
            'extension' => $asset->extension,
            'mime_type' => $asset->mime_type,
            'size_bytes' => (int) $disk->size($storageKey),
            'width' => $asset->width,
            'height' => $asset->height,
            'checksum_sha256' => $asset->checksum_sha256,
            'alt_text' => $asset->alt_text,
            'title' => $asset->title,
            'caption' => $asset->caption,
            'focal_point' => $asset->focal_point,
            'visibility' => $asset->visibility,
            'processing_status' => MediaAsset::STATUS_PENDING,
            'uploaded_by' => $actor->getKey(),
        ]);

        ProcessMediaAssetJob::dispatch($copy->getKey());

        $this->audit->log($actor, 'file_manager.asset_copy', $copy, null, [
            'source_asset_id' => $asset->getKey(),
            'source_storage_key' => $asset->storage_key,
            'storage_key' => $copy->storage_key,
        ]);

        return $copy;
    }

    public function restoreAsset(User $actor, int $assetId): MediaAsset
    {
        $asset = MediaAsset::query()->onlyTrashed()->findOrFail($assetId);

        $asset->restore();

        $this->audit->log($actor, 'file_manager.asset_restore', $asset, null, [
            'restored' => true,
        ]);

        return $asset->refresh();
    }

    /**
     * Ordered ancestor folders from the tree root to (and including) $folder.
     *
     * @return array<int, MediaFolder>
     */
    public function breadcrumbs(?MediaFolder $folder): array
    {
        if ($folder === null) {
            return [];
        }

        $chain = collect([$folder]);
        $visited = [$folder->getKey()];

        $current = $folder->load('parent')->parent;

        while ($current instanceof MediaFolder && ! isset($visited[$current->getKey()])) {
            $chain->push($current);
            $visited[] = $current->getKey();
            $current = $current->load('parent')->parent;
        }

        return $chain->reverse()->values()->all();
    }

    private function sanitizeFolderName(string $name): string
    {
        $name = str_replace(['/', '\\', '..'], '', trim($name));
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages(['folder' => ['A valid folder name is required.']]);
        }

        return $name;
    }

    /**
     * @return array<int, string>
     */
    private function descendantPaths(MediaFolder $folder): array
    {
        return MediaFolder::query()
            ->where('path', 'like', $folder->path . '/%')
            ->get(['id', 'path'])
            ->map(fn (MediaFolder $child) => $child->path)
            ->values()
            ->all();
    }

    private function rewriteDescendantPaths(array $originalPaths, string $oldPrefix, string $newPrefix): void
    {
        $byPath = [];

        foreach ($originalPaths as $path) {
            $byPath[$path] = $newPrefix . Str::after($path, $oldPrefix);
        }

        foreach ($byPath as $old => $new) {
            MediaFolder::query()->where('path', $old)->update(['path' => $new]);
        }
    }
}
