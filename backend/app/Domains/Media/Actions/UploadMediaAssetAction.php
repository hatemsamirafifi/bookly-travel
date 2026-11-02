<?php

namespace App\Domains\Media\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Media\Jobs\ProcessMediaAssetJob;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaFolder;
use App\Domains\Media\Services\MediaStorageService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadMediaAssetAction
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly GovernanceAuditService $audit,
    ) {}

    /**
     * Validate, store, and register a media asset upload.
     *
     * @param  array<string, mixed>  $attributes  Extra fillable attributes
     *                                            (alt_text, title, caption,
     *                                            visibility, focal_point).
     */
    public function perform(
        UploadedFile $file,
        ?User $actor = null,
        ?MediaFolder $folder = null,
        array $attributes = [],
    ): MediaAsset {
        $meta = $this->storage->validate($file);

        $uuid = (string) Str::uuid();
        $disk = (string) config('filesystems.media_disk', 'public');
        $directory = $folder->path ?? 'uploads';
        $extension = $meta['extension'];
        $storageKey = "{$directory}/{$uuid}.{$extension}";

        Storage::disk($disk)->put(
            $storageKey,
            (string) file_get_contents($file->getRealPath()),
        );

        $asset = MediaAsset::create(array_merge([
            'uuid' => $uuid,
            'folder_id' => $folder?->getKey(),
            'disk' => $disk,
            'storage_key' => $storageKey,
            'original_filename' => $meta['original_filename'],
            'filename' => $meta['original_name_safe'],
            'extension' => $extension,
            'mime_type' => $meta['mime_type'],
            'size_bytes' => (int) $file->getSize(),
            'width' => $meta['width'],
            'height' => $meta['height'],
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'visibility' => 'public',
            'processing_status' => 'pending',
            'uploaded_by' => $actor?->getKey(),
        ], $attributes));

        ProcessMediaAssetJob::dispatch($asset->getKey());

        if ($actor !== null) {
            $this->audit->log($actor, 'media.upload', $asset, null, [
                'storage_key' => $asset->storage_key,
                'mime_type' => $asset->mime_type,
                'size_bytes' => $asset->size_bytes,
                'checksum_sha256' => $asset->checksum_sha256,
            ]);
        } else {
            $this->audit->logSystem('media.upload', $asset, null, [
                'storage_key' => $asset->storage_key,
                'mime_type' => $asset->mime_type,
                'size_bytes' => $asset->size_bytes,
                'checksum_sha256' => $asset->checksum_sha256,
            ]);
        }

        return $asset;
    }
}
