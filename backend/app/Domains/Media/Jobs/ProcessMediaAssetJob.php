<?php

namespace App\Domains\Media\Jobs;

use App\Domains\Media\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Post-upload processing for media assets (Spec 035, T-D02).
 *
 * Idempotent and retry-safe: re-running the job on an already `ready`
 * asset is a no-op, and each run re-computes dimensions/variants from
 * the stored source file before marking the asset ready.
 */
class ProcessMediaAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $mediaAssetId) {}

    public function handle(): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if (! $asset || $asset->processing_status === MediaAsset::STATUS_READY) {
            return;
        }

        if (! Storage::disk($asset->disk)->exists($asset->storage_key)) {
            $asset->update([
                'processing_status' => MediaAsset::STATUS_FAILED,
            ]);

            return;
        }

        $variantData = $this->buildVariants($asset);

        $asset->update(array_merge($variantData, [
            'processing_status' => MediaAsset::STATUS_READY,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVariants(MediaAsset $asset): array
    {
        $variants = $asset->variants ?? [];
        $dimensions = $this->resolveDimensions($asset);

        $payload = [];

        if ($dimensions !== null) {
            $payload['width'] = $dimensions[0];
            $payload['height'] = $dimensions[1];
        }

        if (! $asset->isImage() || $dimensions === null) {
            return ['variants' => $variants] + $payload;
        }

        foreach (['thumbnail' => 320, 'medium' => 1280] as $variantName => $maxWidth) {
            if (isset($variants[$variantName]['key'])) {
                continue;
            }

            $thumbKey = $this->variantStorageKey($asset, $variantName);

            if ($this->generateThumbnailWithGd($asset, $thumbKey, $maxWidth)) {
                $variants[$variantName] = [
                    'key' => $thumbKey,
                    'url' => Storage::disk($asset->disk)->url($thumbKey),
                ];
            }
        }

        return ['variants' => $variants] + $payload;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function resolveDimensions(MediaAsset $asset): ?array
    {
        if ($asset->width && $asset->height) {
            return [(int) $asset->width, (int) $asset->height];
        }

        if (! $asset->isImage()) {
            return null;
        }

        $image = @getimagesize(Storage::disk($asset->disk)->path($asset->storage_key));

        if ($image === false) {
            return null;
        }

        [$width, $height] = $image;

        if (! ((int) $width > 0 && (int) $height > 0)) {
            return null;
        }

        return [(int) $width, (int) $height];
    }

    private function variantStorageKey(MediaAsset $asset, string $variantName): string
    {
        $directory = dirname($asset->storage_key);
        $basename = pathinfo($asset->storage_key, PATHINFO_FILENAME);

        return ($directory !== '.' ? $directory . '/' : '') . $basename . '_' . $variantName . '.' . $asset->extension;
    }

    private function generateThumbnailWithGd(MediaAsset $asset, string $thumbKey, int $maxWidth): bool
    {
        try {
            $disk = Storage::disk($asset->disk);
            $fullThumbPath = $disk->path($thumbKey);
            $thumbDir = dirname($fullThumbPath);
            if (! is_dir($thumbDir)) {
                @mkdir($thumbDir, 0775, true);
            }

            $image = match ($asset->mime_type) {
                'image/jpeg' => @imagecreatefromjpeg($disk->path($asset->storage_key)),
                'image/png' => @imagecreatefrompng($disk->path($asset->storage_key)),
                'image/webp' => @imagecreatefromwebp($disk->path($asset->storage_key)),
                default => false,
            };

            if ($image === false) {
                return false;
            }

            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);

            if ($sourceWidth <= $maxWidth) {
                imagedestroy($image);

                return false;
            }

            $targetHeight = (int) max(1, round($sourceHeight * ($maxWidth / $sourceWidth)));
            $thumbnail = imagecreatetruecolor($maxWidth, $targetHeight);

            if (in_array($asset->mime_type, ['image/png', 'image/webp'], true)) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }

            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $maxWidth, $targetHeight, $sourceWidth, $sourceHeight);

            $result = match ($asset->mime_type) {
                'image/jpeg' => imagejpeg($thumbnail, $disk->path($thumbKey), 82),
                'image/png' => imagepng($thumbnail, $disk->path($thumbKey), 6),
                'image/webp' => imagewebp($thumbnail, $disk->path($thumbKey), 82),
                default => false,
            };

            imagedestroy($image);
            imagedestroy($thumbnail);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }
}
