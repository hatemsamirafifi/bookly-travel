<?php

use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

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

    $create = static function (string $filename, string $mime, string $visibility, string $status): MediaAsset {
        $uuid = (string) Str::uuid();

        return MediaAsset::create([
            'uuid' => $uuid,
            'storage_key' => 'uploads/' . $uuid . '.png',
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

    $create('ready.png', 'image/png', 'public', MediaAsset::STATUS_READY);
    $create('private.png', 'image/png', 'private', MediaAsset::STATUS_READY);
    $create('pending.png', 'image/png', 'public', MediaAsset::STATUS_PENDING);
    $create('document.pdf', 'application/pdf', 'public', MediaAsset::STATUS_READY);

    $this->actingAs($admin)->getJson('/admin/hugerte/media')
        ->assertOk()
        ->assertJsonCount(1, 'assets')
        ->assertJsonPath('assets.0.label', 'ready.png')
        ->assertJsonPath('assets.0.alt', 'A safe image');
});
