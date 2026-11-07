<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Records where each media asset is referenced (Spec 035, T-D03).
 *
 * Usage rows are the delete-safety ledger: `DeleteMediaAssetAction`
 * refuses to remove an asset that still appears on a content record.
 */
class MediaUsageTrackerService
{
    /**
     * Reconcile the usage rows for one morphable model field.
     *
     * @param  array<int, int>  $mediaAssetIds
     */
    public function syncUsage(Model $usable, string $fieldName, array $mediaAssetIds): void
    {
        $usableType = $usable->getMorphClass();
        $usableId = (int) $usable->getKey();

        $assetIds = collect($mediaAssetIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        MediaUsage::query()
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId)
            ->where('field_name', $fieldName)
            ->when($assetIds->isNotEmpty(), fn ($query) => $query->whereNotIn('media_asset_id', $assetIds->all()))
            ->delete();

        $existing = MediaUsage::query()
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId)
            ->where('field_name', $fieldName)
            ->whereIn('media_asset_id', $assetIds->all())
            ->pluck('media_asset_id');

        foreach ($assetIds->diff($existing) as $assetId) {
            MediaUsage::create([
                'media_asset_id' => $assetId,
                'usable_type' => $usableType,
                'usable_id' => $usableId,
                'field_name' => $fieldName,
            ]);
        }
    }

    /**
     * Harvest `<img src="...">` references to media assets from HTML,
     * and sync usage rows for the harvested set (replaces prior ids).
     */
    public function syncFromHtml(Model $usable, string $fieldName, ?string $html): void
    {
        if (! filled($html)) {
            $this->syncUsage($usable, $fieldName, []);

            return;
        }

        $assetIds = collect();
        $pattern = '/<img\b[^>]*?\bsrc=["\']([^"\']+)["\']/i';

        if (preg_match_all($pattern, (string) $html, $matches)) {
            $assetIds = collect($matches[1])
                ->flatMap(fn (string $src) => $this->resolveAssetIdsForSource($src))
                ->unique();
        }

        $this->syncUsage($usable, $fieldName, $assetIds->all());
    }

    public function usageCount(MediaAsset $asset): int
    {
        return MediaUsage::query()
            ->where('media_asset_id', $asset->getKey())
            ->count();
    }

    /**
     * @return Collection<int, MediaUsage>
     */
    public function usagesFor(MediaAsset $asset): Collection
    {
        return MediaUsage::query()
            ->where('media_asset_id', $asset->getKey())
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function resolveAssetIdsForSource(string $src): array
    {
        $candidates = [$this->stripQueryAndFragment(trim($src))];

        $referenced = [];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $basename = pathinfo(parse_url($candidate, PHP_URL_PATH) ?: $candidate, PATHINFO_BASENAME);
            $uuidCandidate = pathinfo($basename, PATHINFO_FILENAME);

            $assets = MediaAsset::query()
                ->where(function ($query) use ($candidate, $basename, $uuidCandidate): void {
                    // Directory-tail match: /storage/uploads/<uuid>.<ext>
                    $query->whereRaw('? LIKE CONCAT(\'%\', storage_key)', [$candidate])
                        ->orWhere('storage_key', $candidate)
                        ->orWhere('filename', $basename);

                    if (Str::isUuid($basename)) {
                        $query->orWhere('uuid', $basename);
                    } elseif (Str::isUuid($uuidCandidate)) {
                        $query->orWhere('uuid', $uuidCandidate);
                    }
                })
                ->limit(20)
                ->get();

            foreach ($assets as $asset) {
                if ($this->sourceReferences($candidate, $asset)) {
                    $referenced[] = $asset->getKey();
                }
            }
        }

        return array_values(array_unique($referenced));
    }

    private function sourceReferences(string $source, MediaAsset $asset): bool
    {
        $basename = pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_BASENAME);

        return $basename === ($asset->uuid . '.' . $asset->extension)
            || basename($asset->storage_key) === $basename
            || $source === $asset->storage_key
            || str_ends_with($source, '/' . $asset->storage_key)
            || str_ends_with($source, $asset->storage_key);
    }

    private function stripQueryAndFragment(string $src): string
    {
        $position = strcspn($src, '#?');

        return $position === strlen($src) ? $src : substr($src, 0, $position);
    }
}
