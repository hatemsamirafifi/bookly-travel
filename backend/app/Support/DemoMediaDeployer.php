<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DemoMediaDeployer
{
    /**
     * Locate source fixture directory where demo image assets reside.
     */
    public static function getSourceDirectory(): ?string
    {
        $candidates = [
            '/var/cdn',
            database_path('seeders/fixtures/cdn'),
            database_path('seeders/fixtures'),
            base_path('../docker/cdn'),
            base_path('docker/cdn'),
        ];

        foreach ($candidates as $candidate) {
            if (File::isDirectory($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Deploy all demo media to public storage.
     */
    public static function deployAll(): void
    {
        self::ensureStorageLink();
        self::deployCategories();
        self::deployBlogCategories();
        self::deployTours();
        self::deployBlogCovers();
    }

    /**
     * Deploy tour category images (e.g., categories/walking.jpg) to storage/app/public/categories/.
     */
    public static function deployCategories(): void
    {
        $sourceDir = self::getSourceDirectory();
        if (! $sourceDir || ! File::isDirectory("{$sourceDir}/categories")) {
            return;
        }

        $destDir = Storage::disk('public')->path('categories');
        File::ensureDirectoryExists($destDir);

        foreach (File::files("{$sourceDir}/categories") as $file) {
            $destFile = "{$destDir}/" . $file->getFilename();
            if (! File::exists($destFile) || File::size($destFile) === 0) {
                File::copy($file->getRealPath(), $destFile);
            }
        }
    }

    /**
     * Deploy blog category images to storage/app/public/blog-categories/.
     */
    public static function deployBlogCategories(): void
    {
        $sourceDir = self::getSourceDirectory();
        if (! $sourceDir) {
            return;
        }

        $destDir = Storage::disk('public')->path('blog-categories');
        File::ensureDirectoryExists($destDir);

        $blogCategorySlugs = [
            'travel-tips',
            'adventure',
            'culture-history',
            'city-guides',
            'food-and-wine',
        ];

        $candidates = [
            "{$sourceDir}/blog-categories",
            "{$sourceDir}/categories",
        ];

        foreach ($candidates as $cand) {
            if (File::isDirectory($cand)) {
                foreach (File::files($cand) as $file) {
                    $slug = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    if (in_array($slug, $blogCategorySlugs, true)) {
                        $destFile = "{$destDir}/" . $file->getFilename();
                        if (! File::exists($destFile) || File::size($destFile) === 0) {
                            File::copy($file->getRealPath(), $destFile);
                        }
                    }
                }
            }
        }
    }

    /**
     * Deploy tour cover & gallery images to storage/app/public/tours/.
     */
    public static function deployTours(): void
    {
        $sourceDir = self::getSourceDirectory();
        if (! $sourceDir || ! File::isDirectory("{$sourceDir}/tours")) {
            return;
        }

        $destDir = Storage::disk('public')->path('tours');
        File::ensureDirectoryExists($destDir);

        foreach (File::files("{$sourceDir}/tours") as $file) {
            $destFile = "{$destDir}/" . $file->getFilename();
            if (! File::exists($destFile) || File::size($destFile) === 0) {
                File::copy($file->getRealPath(), $destFile);
            }
        }
    }

    /**
     * Deploy blog cover images to storage/app/public/blog/covers/.
     */
    public static function deployBlogCovers(): void
    {
        $sourceDir = self::getSourceDirectory();
        if (! $sourceDir || ! File::isDirectory("{$sourceDir}/blog")) {
            return;
        }

        $destDir = Storage::disk('public')->path('blog/covers');
        File::ensureDirectoryExists($destDir);

        // Also ensure legacy/flat storage/app/public/blog/ is populated if needed
        $legacyDestDir = Storage::disk('public')->path('blog');
        File::ensureDirectoryExists($legacyDestDir);

        foreach (File::files("{$sourceDir}/blog") as $file) {
            $destFile = "{$destDir}/" . $file->getFilename();
            if (! File::exists($destFile) || File::size($destFile) === 0) {
                File::copy($file->getRealPath(), $destFile);
            }

            $legacyFile = "{$legacyDestDir}/" . $file->getFilename();
            if (! File::exists($legacyFile) || File::size($legacyFile) === 0) {
                File::copy($file->getRealPath(), $legacyFile);
            }
        }
    }

    /**
     * Ensure the storage:link symlink exists and is valid.
     */
    public static function ensureStorageLink(): void
    {
        $publicStoragePath = public_path('storage');
        if (! file_exists($publicStoragePath) && ! is_link($publicStoragePath)) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable $e) {
                Log::warning('DemoMediaDeployer: Could not execute storage:link', ['error' => $e->getMessage()]);
            }
        }
    }
}
