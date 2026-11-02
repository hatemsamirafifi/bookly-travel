<?php

declare(strict_types=1);

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Media\Actions\UploadMediaAssetAction;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaFolder;
use App\Domains\Media\Models\MediaUsage;
use App\Domains\Media\Services\FileManagerService;
use App\Domains\Media\Services\MediaUsageTrackerService;
use App\Domains\Media\Services\StorageAnalyticsService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());
});

function makeFileManagerAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_media' => true, 'delete_media' => true, 'manage_files' => true]]);

    return $admin->fresh();
}

if (! function_exists('pdfUpload')) {
    function pdfUpload(string $name = 'terms.pdf', string $body = 'default'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$body}\n%%EOF\n");
    }
}

test('createFolder builds canonical paths and breadcrumbs from root to leaf', function () {
    $admin = makeFileManagerAdmin();
    $service = app(FileManagerService::class);

    $marketing = $service->createFolder($admin, 'Marketing');
    $campaigns = $service->createFolder($admin, 'Campaigns', $marketing->id);

    expect($marketing->path)->toBe('/marketing')
        ->and($marketing->slug)->toBe('marketing')
        ->and($campaigns->path)->toBe('/marketing/campaigns')
        ->and(MediaFolder::query()->where('path', '/marketing/campaigns')->exists())->toBeTrue();

    $trail = $service->breadcrumbs($campaigns);

    expect($trail)->toHaveCount(2)
        ->and($trail[0]->id)->toBe($marketing->id)
        ->and($trail[1]->id)->toBe($campaigns->id)
        ->and($service->breadcrumbs(null))->toBe([]);

    // Duplicate slugs under the same parent are rejected.
    expect(fn () => $service->createFolder($admin, 'Campaigns', $marketing->id))
        ->toThrow(ValidationException::class)
        ->and(MediaFolder::where('path', '/marketing')->get()->filter(fn (MediaFolder $f) => $f->slug === 'marketing'))->toHaveCount(1);

    expect(GovernanceAuditLog::query()->where('action', 'file_manager.folder_create')->where('target_id', $campaigns->id)->exists())->toBeTrue();
});

test('moving a folder into its own descendant is rejected (cycle guard)', function () {
    $admin = makeFileManagerAdmin();
    $service = app(FileManagerService::class);

    $marketing = $service->createFolder($admin, 'Marketing');
    $campaigns = $service->createFolder($admin, 'Campaigns', $marketing->id);

    // Moving /marketing under /marketing/campaigns creates a cycle.
    expect(fn () => $service->moveFolder($admin, $marketing, $campaigns->id))
        ->toThrow(ValidationException::class);

    // Self-parenting is equally rejected.
    expect(fn () => $service->moveFolder($admin, $marketing, $marketing->id))
        ->toThrow(ValidationException::class);

    // A valid move rewrites descendant paths.
    $rootSecond = $service->createFolder($admin, 'Archive');
    $moved = $service->moveFolder($admin, $marketing, $rootSecond->id);

    $campaigns->refresh();

    expect($moved->path)->toBe('/archive/marketing')
        ->and($campaigns->path)->toBe('/archive/marketing/campaigns');
});

test('non-empty folders cannot be deleted; empty deletion is audited', function () {
    $admin = makeFileManagerAdmin();
    $service = app(FileManagerService::class);

    $folder = $service->createFolder($admin, 'ToDelete');
    $service->createFolder($admin, 'Child', $folder->id);

    expect(fn () => $service->deleteFolder($admin, $folder))
        ->toThrow(fn (ValidationException $e) => str_contains(
            (string) collect($e->errors()['folder'] ?? [])->first(),
            'not empty',
        ))
        ->and($folder->refresh()->exists)->toBeTrue();

    // Delete the child, then the parent succeeds.
    $child = MediaFolder::query()->where('path', '/todelete/child')->firstOrFail();
    $service->deleteFolder($admin, $child);
    $service->deleteFolder($admin, $folder->refresh());

    expect(MediaFolder::query()->where('path', '/todelete')->exists())->toBeFalse()
        ->and(GovernanceAuditLog::query()
            ->where('action', 'file_manager.folder_delete')
            ->where('target_type', 'media_folder')
            ->where('target_id', $folder->id)
            ->exists())->toBeTrue();
});

test('assets can be moved, copied, soft-deleted, and restored with a stable public URL', function () {
    $admin = makeFileManagerAdmin();
    $service = app(FileManagerService::class);
    $asset = app(UploadMediaAssetAction::class)->perform(pdfUpload('report.pdf'), $admin);

    $destination = $service->createFolder($admin, 'Docs');
    $publicUrlBefore = $asset->publicUrl();

    $moved = $service->moveAsset($admin, $asset, $destination->id);

    expect($moved->folder_id)->toBe($destination->id)
        ->and($moved->storage_key)->toBe($asset->storage_key)
        ->and($moved->publicUrl())->toBe($publicUrlBefore);

    Storage::disk($asset->disk)->assertExists($asset->storage_key);

    // Copy leaves the original intact and creates an independent asset.
    $copy = $service->copyAsset($admin, $moved);

    expect($copy->id)->not->toBe($moved->id)
        ->and($copy->uuid)->not->toBe($moved->uuid)
        ->and($copy->storage_key)->not->toBe($moved->storage_key)
        ->and($copy->checksum_sha256)->toBe($moved->checksum_sha256);

    Storage::disk($copy->disk)->assertExists($copy->storage_key);

    // Soft delete then restore.
    $moved->delete();

    expect($moved->fresh()->deleted_at)->not->toBeNull();

    $restored = $service->restoreAsset($admin, $moved->id);

    expect($restored->exists)->toBeTrue()
        ->and($restored->deleted_at)->toBeNull()
        ->and(MediaAsset::query()->whereKey($moved->id)->exists())->toBeTrue();

    expect(GovernanceAuditLog::query()
        ->whereIn('action', ['file_manager.asset_move', 'file_manager.asset_copy', 'file_manager.asset_restore'])
        ->pluck('action')
        ->unique()
        ->values()
        ->all())->toHaveCount(3);
});

test('storage analytics reports bytes, mime mix, unused assets and duplicates', function () {
    $admin = makeFileManagerAdmin();
    $folder = app(FileManagerService::class)->createFolder($admin, 'Marketing');

    $dupOne = app(UploadMediaAssetAction::class)->perform(pdfUpload('one.pdf', 'IDENTICAL'), $admin);
    $dupTwo = app(UploadMediaAssetAction::class)->perform(pdfUpload('two.pdf', 'IDENTICAL'), $admin, $folder);
    $used = app(UploadMediaAssetAction::class)->perform(pdfUpload('used.pdf', 'UNIQUE-BODY'), $admin);

    app(MediaUsageTrackerService::class)->syncUsage(makeBlogPost(), 'hero_media_id', [$used->id]);

    $dupTwo->update(['processing_status' => 'ready']);
    $used->update(['processing_status' => 'ready']);

    $overview = app(StorageAnalyticsService::class)->overview();

    expect($overview['total_assets_count'])->toBe(3)
        ->and($overview['total_bytes'])->toBe($dupOne->size_bytes + $dupTwo->size_bytes + $used->size_bytes)
        ->and($overview['trashed_assets_count'])->toBe(0)
        ->and($overview['unused_assets_count'])->toBe(2);

    $mimeType = collect($overview['by_mime_type'])->firstWhere('mime_type', 'application/pdf');

    expect($mimeType)->not->toBeNull()
        ->and($mimeType['count'])->toBe(3)
        ->and($mimeType['bytes'])->toBe($overview['total_bytes']);

    $folderRow = collect($overview['by_folder'])->firstWhere('folder_path', '/marketing');

    expect($folderRow)->not->toBeNull()
        ->and($folderRow['count'])->toBe(1)
        ->and($folderRow['bytes'])->toBe($dupTwo->size_bytes);

    $duplicates = $overview['duplicate_checksums'];

    expect($duplicates)->toHaveCount(1)
        ->and($duplicates[0]['count'])->toBe(2)
        ->and($duplicates[0]['bytes'])->toBe($dupOne->size_bytes + $dupTwo->size_bytes)
        ->and($duplicates[0]['wasted_bytes'])->toBe(min($dupOne->size_bytes, $dupTwo->size_bytes))
        ->and($duplicates[0]['checksum_sha256'])->toBe($dupOne->checksum_sha256)
        ->and(MediaAsset::query()->count())->toBe(3)
        ->and(count($overview['largest_assets']))->toBe(3)
        ->and($overview['largest_assets'][0]['size_bytes'])->toBe(max($dupOne->size_bytes, $dupTwo->size_bytes, $used->size_bytes));
});
