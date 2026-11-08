<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Domains\Media\Actions\UploadMediaAssetAction;
use App\Domains\Media\Jobs\ProcessMediaAssetJob;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Services\MediaStorageService;
use App\Domains\Media\Services\MediaUsageTrackerService;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\TourMedia;
use App\Filament\Components\MediaPicker;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\AdminE2EFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('fixture seeder creates deterministic physical file with matching dimensions and checksum', function () {
    Config::set('app.allow_admin_e2e_fixtures', true);

    $seeder = new AdminE2EFixtureSeeder;
    $seeder->run();

    $asset = MediaAsset::query()->where('storage_key', 'media/2026/08/e2e-amalfi-hero.jpg')->first();

    expect($asset)->not->toBeNull()
        ->and($asset->processing_status)->toBe('ready')
        ->and($asset->mime_type)->toBe('image/jpeg')
        ->and($asset->width)->toBe(1600)
        ->and($asset->height)->toBe(900)
        ->and($asset->fileExists())->toBeTrue()
        ->and($asset->isUsable())->toBeTrue();

    Storage::disk('public')->assertExists('media/2026/08/e2e-amalfi-hero.jpg');

    $realPath = Storage::disk('public')->path('media/2026/08/e2e-amalfi-hero.jpg');
    expect(hash_file('sha256', $realPath))->toBe($asset->checksum_sha256)
        ->and(filesize($realPath))->toBe($asset->size_bytes);
});

test('missing image detection identifies nonexistent files and marks usability false', function () {
    $uuid = (string) Str::uuid();
    $asset = MediaAsset::create([
        'uuid' => $uuid,
        'disk' => 'public',
        'storage_key' => 'uploads/missing-' . $uuid . '.jpg',
        'original_filename' => 'missing.jpg',
        'filename' => 'missing.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 12345,
        'width' => 800,
        'height' => 600,
        'checksum_sha256' => str_repeat('b', 64),
        'processing_status' => MediaAsset::STATUS_READY,
        'visibility' => 'public',
    ]);

    expect($asset->fileExists())->toBeFalse()
        ->and($asset->isUsable())->toBeFalse()
        ->and($asset->previewUrl())->toBe(MediaAsset::missingImagePlaceholderUrl());
});

test('ProcessMediaAssetJob marks missing assets as failed and does not falsely mark ready', function () {
    $uuid = (string) Str::uuid();
    $asset = MediaAsset::create([
        'uuid' => $uuid,
        'disk' => 'public',
        'storage_key' => 'uploads/nonexistent-' . $uuid . '.jpg',
        'original_filename' => 'nonexistent.jpg',
        'filename' => 'nonexistent.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 12345,
        'checksum_sha256' => str_repeat('c', 64),
        'processing_status' => MediaAsset::STATUS_PENDING,
        'visibility' => 'public',
    ]);

    (new ProcessMediaAssetJob($asset->id))->handle();

    $asset->refresh();
    expect($asset->processing_status)->toBe(MediaAsset::STATUS_FAILED);
});

test('valid reconstructed temporary upload file is accepted by pipeline and job generates thumbnails', function () {
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_media' => true]]);

    // Simulate server-side reconstructed file from trusted temporary storage
    $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_');
    $img = imagecreatetruecolor(800, 600);
    $bg = imagecolorallocate($img, 10, 20, 30);
    imagefill($img, 0, 0, $bg);
    imagejpeg($img, $tempPath, 90);
    imagedestroy($img);

    try {
        $upload = new UploadedFile($tempPath, 'vacation.jpg', null, null, true);
        $action = app(UploadMediaAssetAction::class);
        $asset = $action->perform($upload, $admin, null, [
            'alt_text' => ['en' => 'Vacation photo'],
            'title' => ['en' => 'Vacation'],
        ]);

        expect($asset->exists)->toBeTrue()
            ->and($asset->mime_type)->toBe('image/jpeg')
            ->and($asset->width)->toBe(800)
            ->and($asset->height)->toBe(600)
            ->and($asset->fileExists())->toBeTrue();

        // Process thumbnail job
        (new ProcessMediaAssetJob($asset->id))->handle();

        $asset->refresh();
        expect($asset->processing_status)->toBe(MediaAsset::STATUS_READY)
            ->and($asset->variants)->toHaveKey('thumbnail');

        $thumbKey = $asset->variants['thumbnail']['key'];
        Storage::disk('public')->assertExists($thumbKey);
    } finally {
        @unlink($tempPath);
    }
});

test('MediaStorageService validates JPG PNG WebP AVIF and PDF properly', function () {
    $service = app(MediaStorageService::class);

    // PNG
    $pngFile = UploadedFile::fake()->image('picture.png', 100, 100);
    $pngMeta = $service->validate($pngFile);
    expect($pngMeta['mime_type'])->toBe('image/png')
        ->and($pngMeta['extension'])->toBe('png')
        ->and($pngMeta['width'])->toBe(100);

    // PDF
    $pdfContent = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $pdfFile = UploadedFile::fake()->createWithContent('document.pdf', $pdfContent);
    $pdfMeta = $service->validate($pdfFile);
    expect($pdfMeta['mime_type'])->toBe('application/pdf')
        ->and($pdfMeta['extension'])->toBe('pdf')
        ->and($pdfMeta['width'])->toBeNull();

    // Spoofed file with forbidden extension sequence
    $spoofed = UploadedFile::fake()->create('exploit.php.jpg', 100, 'image/jpeg');
    expect(fn () => $service->validate($spoofed))->toThrow(ValidationException::class);
});

test('MediaPicker filters out missing images and only exposes existing ready assets', function () {
    $uuid1 = (string) Str::uuid();
    $storageKey1 = 'uploads/' . $uuid1 . '.png';
    Storage::disk('public')->put($storageKey1, 'valid image bytes');

    $valid = MediaAsset::create([
        'uuid' => $uuid1,
        'storage_key' => $storageKey1,
        'disk' => 'public',
        'original_filename' => 'valid-hero.png',
        'filename' => 'valid-hero.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size_bytes' => 100,
        'checksum_sha256' => str_repeat('1', 64),
        'processing_status' => MediaAsset::STATUS_READY,
        'visibility' => 'public',
    ]);

    $uuid2 = (string) Str::uuid();
    $missing = MediaAsset::create([
        'uuid' => $uuid2,
        'storage_key' => 'uploads/' . $uuid2 . '.png', // not written to disk
        'disk' => 'public',
        'original_filename' => 'missing-hero.png',
        'filename' => 'missing-hero.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size_bytes' => 100,
        'checksum_sha256' => str_repeat('2', 64),
        'processing_status' => MediaAsset::STATUS_READY,
        'visibility' => 'public',
    ]);

    $picker = MediaPicker::make('hero_image');
    $options = $picker->getOptions();

    expect($options)->toHaveKey($valid->storage_key)
        ->and($options)->not->toHaveKey($missing->storage_key);
});

test('HugeRTE image insertion sanitizes safely and synchronizes media usage tracking', function () {
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_blog' => true, 'manage_media' => true]]);

    $storageKey = 'uploads/tuscany-hills.jpg';
    Storage::disk('public')->put($storageKey, 'image data');

    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'storage_key' => $storageKey,
        'disk' => 'public',
        'original_filename' => 'tuscany-hills.jpg',
        'filename' => 'tuscany-hills.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 500,
        'checksum_sha256' => str_repeat('d', 64),
        'processing_status' => MediaAsset::STATUS_READY,
        'visibility' => 'public',
        'alt_text' => ['en' => 'Tuscany Rolling Hills'],
    ]);

    // Admin endpoint exposes the valid asset
    $response = $this->actingAs($admin)->getJson('/admin/hugerte/media');
    $response->assertOk()
        ->assertJsonPath('assets.0.label', 'tuscany-hills.jpg')
        ->assertJsonPath('assets.0.alt', 'Tuscany Rolling Hills');

    $insertedUrl = $response->json('assets.0.url');
    expect($insertedUrl)->toBe('/storage/' . $storageKey);

    // HugeRTE HTML content containing inserted image and malicious payload
    $unsafeHtml = sprintf(
        '<p>Visit Tuscany:</p><img src="%s" alt="Tuscany Rolling Hills" width="800" height="600"><script>alert("xss")</script>',
        $insertedUrl,
    );

    $sanitizer = app(HtmlSanitizerService::class);
    $safeHtml = $sanitizer->sanitize($unsafeHtml);

    expect($safeHtml)->toContain('<img')
        ->and($safeHtml)->toContain('src="' . $insertedUrl . '"')
        ->and($safeHtml)->toContain('alt="Tuscany Rolling Hills"')
        ->and($safeHtml)->not->toContain('<script>');

    // Create and save blog post with this safe HTML
    $category = BlogCategory::create([
        'slug' => 'italy-guides',
        'name' => 'Italy Guides',
        'is_active' => true,
    ]);

    $post = BlogPost::create([
        'author_id' => $admin->id,
        'blog_category_id' => $category->id,
        'slug' => 'tuscany-guide',
        'status' => BlogPost::STATUS_PUBLISHED,
        'published_at' => now(),
        'title' => ['en' => 'Tuscany Guide'],
        'body' => ['en' => $safeHtml],
    ]);

    // Track usage
    $tracker = app(MediaUsageTrackerService::class);
    $tracker->syncFromHtml($post, 'body', $post->body['en']);

    expect($tracker->usageCount($asset))->toBe(1);

    // Reload content and confirm image persists
    $reloaded = BlogPost::find($post->id);
    expect($reloaded->body['en'])->toContain($insertedUrl);
});

test('Partner cannot access Admin media endpoint and receives strictly owned tour media', function () {
    $partnerUser = User::factory()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'company_name' => 'Alps Travel Co',
        'contact_name' => 'Marco',
        'contact_phone' => '+39012345678',
        'business_registration' => 'REG-ALPS',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $cat = Category::firstOrCreate(['slug' => 'alps-cat'], ['name' => 'Alps Category']);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $cat->id,
        'slug' => 'alps-trek',
        'location' => 'Dolomites',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 20000,
        'status' => 'published',
    ]);

    $media = TourMedia::create([
        'tour_id' => $tour->id,
        'type' => 'image',
        'url' => 'https://cdn.bookly.test/uploads/dolomites.jpg',
        'alt_text' => 'Dolomites Peaks',
        'sort_order' => 1,
    ]);

    // Partner is denied access to admin media
    $this->actingAs($partnerUser)->getJson('/admin/hugerte/media')->assertForbidden();

    // Partner can access partner media with Sanctum
    Sanctum::actingAs($partnerUser, ['partner']);
    $res = $this->getJson('/api/partner/hugerte/media');
    $res->assertOk()
        ->assertJsonCount(1, 'assets')
        ->assertJsonPath('assets.0.id', $media->id)
        ->assertJsonPath('assets.0.url', 'https://cdn.bookly.test/uploads/dolomites.jpg')
        ->assertJsonPath('assets.0.alt', 'Dolomites Peaks');
});
