<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Media\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class HugeRteMediaController extends Controller
{
    public function __invoke(Request $request, AdminAuthorizationService $authz): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null
            && ($authz->can($user, 'manage_blog') || $authz->can($user, 'manage_cms') || $authz->can($user, 'manage_media')), 403);

        $assets = MediaAsset::query()
            ->where('processing_status', MediaAsset::STATUS_READY)
            ->where('visibility', 'public')
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->limit(100)
            ->get(['id', 'disk', 'storage_key', 'original_filename', 'alt_text'])
            ->filter(fn (MediaAsset $asset): bool => $asset->fileExists());

        return response()->json(['assets' => $assets->map(fn (MediaAsset $asset): array => [
            'id' => $asset->id,
            'url' => $asset->publicUrl(),
            'label' => $asset->original_filename,
            'alt' => (string) ($asset->alt_text['en'] ?? collect((array) $asset->alt_text)->first(fn ($val) => filled($val)) ?? ''),
        ])->values()->all()]);
    }
}
