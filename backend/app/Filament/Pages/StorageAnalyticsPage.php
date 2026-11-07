<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Media\Services\StorageAnalyticsService;
use App\Models\User;
use Filament\Pages\Page;

/**
 * Storage Analytics for the Media & Storage console (T-F02).
 *
 * Surfaces StorageAnalyticsService::overview(): headline counters, MIME
 * breakdown, folder usage, duplicate checksum groups with wasted bytes,
 * unused-asset count and the largest uploads. Access requires
 * `manage_files`, `manage_media` OR `view_all_analytics`.
 */
class StorageAnalyticsPage extends Page
{
    protected static string $view = 'filament.pages.storage-analytics-page';

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Media & Storage';

    protected static ?string $navigationLabel = 'Storage Analytics';

    protected static ?string $slug = 'storage-analytics';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed> */
    public array $overview = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_files')
            || $authz->can($user, 'manage_media')
            || $authz->can($user, 'view_all_analytics');
    }

    public function mount(): void
    {
        $this->loadOverview();
    }

    public function loadOverview(): void
    {
        $this->overview = app(StorageAnalyticsService::class)->overview();
    }

    public function humanSize(?int $bytes): string
    {
        $bytes = $bytes ?? 0;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 1) . ' GB';
    }
}
