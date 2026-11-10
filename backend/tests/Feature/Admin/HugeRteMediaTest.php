<?php

use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('rejects anonymous image-library access to the editor', function () {
    $this->getJson('/admin/hugerte/media')->assertUnauthorized();
});

it('denies admins without blog, cms, or media permissions', function () {
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => []]);

    $this->actingAs($admin)
        ->getJson('/admin/hugerte/media')
        ->assertForbidden();
});

it('only exposes ready public images to authorized editors', function () {
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_blog' => true]]);

    $create = static function (string $filename, string $mime, string $visibility, string $status, bool $writePhysicalFile = true): MediaAsset {
        $uuid = (string) Str::uuid();
        $storageKey = 'uploads/' . $uuid . '.png';

        if ($writePhysicalFile) {
            Storage::disk('public')->put($storageKey, 'dummy image content');
        }

        return MediaAsset::create([
            'uuid' => $uuid,
            'storage_key' => $storageKey,
            'disk' => 'public',
            'original_filename' => $filename,
            'filename' => $filename,
            'extension' => 'png',
            'mime_type' => $mime,
            'size_bytes' => 64,
            'checksum_sha256' => str_repeat('a', 64),
            'processing_status' => $status,
            'visibility' => $visibility,
            'alt_text' => ['en' => 'A safe image'],
        ]);
    };

    $create('ready.png', 'image/png', 'public', MediaAsset::STATUS_READY, true);
    $create('missing.png', 'image/png', 'public', MediaAsset::STATUS_READY, false);
    $create('private.png', 'image/png', 'private', MediaAsset::STATUS_READY, true);
    $create('pending.png', 'image/png', 'public', MediaAsset::STATUS_PENDING, true);
    $create('document.pdf', 'application/pdf', 'public', MediaAsset::STATUS_READY, true);

    $this->actingAs($admin)->getJson('/admin/hugerte/media')
        ->assertOk()
        ->assertJsonCount(1, 'assets')
        ->assertJsonPath('assets.0.label', 'ready.png')
        ->assertJsonPath('assets.0.alt', 'A safe image');
});
