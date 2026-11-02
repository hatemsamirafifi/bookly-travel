<?php

declare(strict_types=1);

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Media\Actions\DeleteMediaAssetAction;
use App\Domains\Media\Actions\UploadMediaAssetAction;
use App\Domains\Media\Jobs\ProcessMediaAssetJob;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaUsage;
use App\Domains\Media\Services\MediaUsageTrackerService;
use App\Filament\Resources\MediaAssetResource\Pages\ListMediaAssets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makeMediaAdmin(array $flags = ['manage_media' => true, 'delete_media' => true]): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh();
}

if (! function_exists('pdfUpload')) {
    function pdfUpload(string $name = 'terms.pdf', string $body = '1 0 obj << /Type /Catalog >> endobj'): UploadedFile
    {
        $content = "%PDF-1.4\n{$body}\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}

test('a valid PNG upload stores the file, checksum, job, and audit trail', function () {
    $admin = makeMediaAdmin();
    Queue::fake();

    $asset = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('cover.png', 800, 600),
        $admin,
    );

    expect($asset->exists)->toBeTrue()
        ->and($asset->processing_status)->toBe(MediaAsset::STATUS_PENDING)
        ->and($asset->mime_type)->toBe('image/png')
        ->and($asset->extension)->toBe('png')
        ->and($asset->width)->toBe(800)
        ->and($asset->height)->toBe(600)
        ->and($asset->size_bytes)->toBeGreaterThan(0)
        ->and($asset->uploaded_by)->toBe($admin->id)
        ->and(str_contains($asset->storage_key, $asset->uuid . '.' . $asset->extension))->toBeTrue();

    Storage::disk($asset->disk)->assertExists($asset->storage_key);

    $stored = (string) Storage::disk($asset->disk)->get($asset->storage_key);
    expect(hash('sha256', $stored))->toBe($asset->checksum_sha256)
        ->and(strlen($asset->checksum_sha256))->toBe(64);

    Queue::assertPushed(ProcessMediaAssetJob::class, fn (ProcessMediaAssetJob $job) => $job->mediaAssetId === $asset->id);

    expect(GovernanceAuditLog::query()
        ->where('action', 'media.upload')
        ->where('target_type', 'media_asset')
        ->where('target_id', $asset->id)
        ->exists())->toBeTrue();

    // Run the deferred job manually; a second run proves idempotency.
    (new ProcessMediaAssetJob($asset->id))->handle();
    (new ProcessMediaAssetJob($asset->id))->handle();

    $asset->refresh();

    $thumbnail = $asset->variants['thumbnail']['key'] ?? null;

    expect($asset->processing_status)->toBe(MediaAsset::STATUS_READY)
        ->and($thumbnail)->not->toBeNull();

    Storage::disk($asset->disk)->assertExists($thumbnail);
});

test('PNG uploads keep real dimensions and PDFs carry none', function () {
    $admin = makeMediaAdmin();

    $png = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('photo.png', 64, 32),
        $admin,
    );
    $pdf = app(UploadMediaAssetAction::class)->perform(pdfUpload(), $admin);

    expect($png->mime_type)->toBe('image/png')
        ->and((int) $png->width)->toBe(64)
        ->and((int) $png->height)->toBe(32)
        ->and($pdf->mime_type)->toBe('application/pdf')
        ->and($pdf->width)->toBeNull()
        ->and($pdf->height)->toBeNull();
});

test('spoofed and double-extension uploads are rejected', function () {
    $admin = makeMediaAdmin();
    $action = app(UploadMediaAssetAction::class);

    $evil = UploadedFile::fake()->create('evil.php.jpg', 10);
    $svg = UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml');
    $html = UploadedFile::fake()->create('payload.html', 10, 'text/html');
    $shell = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

    expect(fn () => $action->perform($evil, $admin))->toThrow(ValidationException::class)
        ->and(fn () => $action->perform($svg, $admin))->toThrow(ValidationException::class)
        ->and(fn () => $action->perform($html, $admin))->toThrow(ValidationException::class)
        ->and(fn () => $action->perform($shell, $admin))->toThrow(ValidationException::class)
        ->and(MediaAsset::count())->toBe(0);
});

test('usage tracker records HTML and field usages and blocks non-forced deletion', function () {
    $admin = makeMediaAdmin();

    $asset = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('hero.png'),
        $admin,
    );

    $blog = makeBlogPost();
    $html = sprintf(
        '<p>A wonder of Tuscany.</p><img src="/storage/%s" alt="Tuscany">',
        $asset->storage_key,
    );
    $blog->update(['body' => ['en' => $html]]);

    $tour = makeSearchableTour();

    $tracker = app(MediaUsageTrackerService::class);
    $tracker->syncFromHtml($blog, 'body', $blog->body['en']);
    $tracker->syncUsage($tour, 'hero_media_id', [$asset->id]);

    expect($tracker->usageCount($asset))->toBe(2)
        ->and($tracker->usagesFor($asset)->count())->toBe(2)
        ->and(MediaUsage::query()->where('usable_type', 'blog_post')->where('usable_id', $blog->id)->where('field_name', 'body')->exists())->toBeTrue()
        ->and(MediaUsage::query()->where('usable_type', 'tour')->where('usable_id', $tour->id)->where('field_name', 'hero_media_id')->exists())->toBeTrue();

    // Non-forced deletion is blocked while referenced.
    $delete = app(DeleteMediaAssetAction::class);

    expect(fn () => $delete->perform($asset, $admin))
        ->toThrow(fn (ValidationException $exception) => str_contains(
            (string) collect($exception->errors()['media_asset'] ?? [])->first(),
            'referenced by 2 content record(s)',
        ));

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue()
        ->and($asset->refresh()->deleted_at)->toBeNull();

    // Re-syncing one field with an empty list clears only that field.
    $tracker->syncFromHtml($blog, 'body', null);

    expect($tracker->usageCount($asset))->toBe(1);
});

test('forced deletion requires permission, clears usages, and writes the audit log', function () {
    $admin = makeMediaAdmin(['manage_media' => true, 'delete_media' => true]);
    $editor = makeMediaAdmin(['manage_blog' => true]);

    $asset = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('hero.png'),
        $admin,
    );

    $tour = makeSearchableTour();
    app(MediaUsageTrackerService::class)->syncUsage($tour, 'hero_media_id', [$asset->id]);

    $delete = app(DeleteMediaAssetAction::class);

    expect(MediaAsset::count())->toBe(1);

    // An admin without media permissions may not force-delete.
    expect(fn () => $delete->perform($asset, $editor, force: true))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class)
        ->and(MediaAsset::count())->toBe(1);

    $delete->perform($asset, $admin, force: true);

    $asset->refresh();

    $forceLog = GovernanceAuditLog::query()
        ->where('action', 'media.force_delete')
        ->where('target_type', 'media_asset')
        ->where('target_id', $asset->id)
        ->first();

    expect($asset->deleted_at)->not->toBeNull()
        ->and(MediaUsage::query()->where('media_asset_id', $asset->id)->count())->toBe(0)
        ->and($forceLog)->not->toBeNull()
        ->and(count($forceLog->metadata['references'] ?? []))->toBe(1);
});

test('permanent force deletion removes the row entirely', function () {
    $admin = makeMediaAdmin();

    $asset = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('hero.png'),
        $admin,
    );

    app(DeleteMediaAssetAction::class)->perform($asset, $admin, force: true, permanent: true);

    expect(MediaAsset::withTrashed()->count())->toBe(0)
        ->and(GovernanceAuditLog::query()
            ->where('action', 'media.force_delete')
            ->where('target_type', 'media_asset')
            ->where('target_id', $asset->id)
            ->where('metadata->permanent', true)
            ->exists())->toBeTrue();
});

test('the Media Asset Resource enforces media permissions and renders', function () {
    $mediaAdmin = makeMediaAdmin();
    $traveler = User::factory()->traveler()->create();

    $asset = app(UploadMediaAssetAction::class)->perform(
        UploadedFile::fake()->image('gallery.png'),
        $mediaAdmin,
    );

    expect($traveler->can('viewAny', MediaAsset::class))->toBeFalse()
        ->and($mediaAdmin->can('viewAny', MediaAsset::class))->toBeTrue()
        ->and($traveler->can('create', MediaAsset::class))->toBeFalse()
        ->and($mediaAdmin->can('create', MediaAsset::class))->toBeTrue();

    actingAs($traveler);
    Livewire::test(ListMediaAssets::class)->assertForbidden();

    actingAs($mediaAdmin);

    Livewire::test(ListMediaAssets::class)
        ->assertOk()
        ->assertSee($asset->original_filename);
});
