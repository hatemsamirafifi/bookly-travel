<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Domains\Media\Actions\UploadMediaAssetAction;
use App\Domains\Media\Models\MediaFolder;
use App\Filament\Resources\MediaAssetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    /**
     * The Filament FileUpload stores pending files on the media disk;
     * route each one through UploadMediaAssetAction so checksums,
     * processing jobs, and the `media.upload` audit entry are recorded.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $paths = Arr::wrap($data['file'] ?? null);
        unset($data['file'], $data['tags']);
        $folder = filled($data['folder_id'] ?? null)
            ? MediaFolder::query()->find((int) $data['folder_id'])
            : null;

        $asset = null;

        foreach ($paths as $path) {
            if (! filled($path)) {
                continue;
            }

            $asset = $this->storeFromPendingPath((string) $path, $data, $folder);
        }

        return $asset ?? parent::handleRecordCreation($data);
    }

    private function storeFromPendingPath(string $path, array $data, ?MediaFolder $folder): Model
    {
        $diskName = (string) config('filesystems.media_disk', 'public');
        $disk = Storage::disk($diskName);

        $tempPath = tempnam(sys_get_temp_dir(), 'media_asset_');
        file_put_contents($tempPath, (string) $disk->get($path));

        try {
            $upload = new UploadedFile($tempPath, basename($path));

            $asset = app(UploadMediaAssetAction::class)->perform(
                $upload,
                $this->getUser(),
                $folder,
                Arr::only($data, ['alt_text', 'title', 'caption', 'visibility', 'focal_point']),
            );
        } finally {
            @unlink($tempPath);
            $disk->delete($path);
        }

        return $asset;
    }
}
