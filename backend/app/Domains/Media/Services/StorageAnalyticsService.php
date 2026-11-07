<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\MediaAsset;
use Illuminate\Support\Facades\DB;

/**
 * Storage reporting for the Media & Storage console (T-F01).
 *
 * All numbers derive from `media_assets`; folder usage is joined through the
 * canonical folder path. Only soft-deleted rows are excluded from the
 * active aggregates; the trash has its own counters.
 */
class StorageAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'total_assets_count' => MediaAsset::query()->count(),
            'trashed_assets_count' => MediaAsset::query()->onlyTrashed()->count(),
            'total_bytes' => (int) MediaAsset::query()->sum('size_bytes'),
            'trashed_bytes' => (int) MediaAsset::query()->onlyTrashed()->sum('size_bytes'),
            'by_mime_type' => $this->byMimeType(),
            'by_folder' => $this->byFolder(),
            'unused_assets_count' => $this->unusedAssetsCount(),
            'duplicate_checksums' => $this->duplicateChecksums(),
            'largest_assets' => $this->largestAssets(),
        ];
    }

    /**
     * @return array<int, array{mime_type: string, count: int, bytes: int}>
     */
    private function byMimeType(): array
    {
        return MediaAsset::query()
            ->select('mime_type', DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM(size_bytes), 0) AS bytes'))
            ->groupBy('mime_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'mime_type' => (string) $row->mime_type,
                'count' => (int) $row->count,
                'bytes' => (int) $row->bytes,
            ])
            ->all();
    }

    /**
     * @return array<int, array{folder_path: string, count: int, bytes: int}>
     */
    private function byFolder(): array
    {
        return MediaAsset::query()
            ->leftJoin('media_folders', 'media_assets.folder_id', '=', 'media_folders.id')
            ->select('media_folders.path AS folder_path', DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM(media_assets.size_bytes), 0) AS bytes'))
            ->groupBy('media_folders.path')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'folder_path' => $row->folder_path ?? 'uploads',
                'count' => (int) $row->count,
                'bytes' => (int) $row->bytes,
            ])
            ->all();
    }

    private function unusedAssetsCount(): int
    {
        return MediaAsset::query()
            ->doesntHave('usages')
            ->count();
    }

    /**
     * Checksum groups of duplicated files with wasted unique bytes.
     *
     * @return array<int, array{checksum_sha256: string, count: int, bytes: int, wasted_bytes: int}>
     */
    private function duplicateChecksums(): array
    {
        return MediaAsset::query()
            ->select('checksum_sha256', DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM(size_bytes), 0) AS bytes'), DB::raw('MAX(size_bytes) AS largest'))
            ->groupBy('checksum_sha256')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'checksum_sha256' => (string) $row->checksum_sha256,
                'count' => (int) $row->count,
                'bytes' => (int) $row->bytes,
                'wasted_bytes' => ((int) $row->bytes) - ((int) $row->largest),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, original_filename: string, mime_type: string, size_bytes: int}>
     */
    private function largestAssets(): array
    {
        return MediaAsset::query()
            ->orderByDesc('size_bytes')
            ->limit(10)
            ->get(['id', 'original_filename', 'mime_type', 'size_bytes'])
            ->map(fn (MediaAsset $asset): array => [
                'id' => (int) $asset->id,
                'original_filename' => $asset->original_filename,
                'mime_type' => $asset->mime_type,
                'size_bytes' => (int) $asset->size_bytes,
            ])
            ->all();
    }
}
