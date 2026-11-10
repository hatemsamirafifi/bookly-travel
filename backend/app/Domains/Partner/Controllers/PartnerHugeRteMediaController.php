<?php

declare(strict_types=1);

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\TourMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PartnerHugeRteMediaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        abort_unless($partnerId > 0, 403, 'Partner authorization required.');

        // Expose only authorized tour media belonging to THIS partner
        $media = TourMedia::query()
            ->whereHas('tour', fn ($query) => $query->where('partner_id', $partnerId))
            ->whereIn('type', ['image', 'cover', 'gallery'])
            ->latest('id')
            ->limit(50)
            ->get(['id', 'url', 'alt_text']);

        $assets = $media->map(fn (TourMedia $item): array => [
            'id' => $item->id,
            'url' => $item->url,
            'label' => basename(parse_url($item->url, PHP_URL_PATH) ?? 'image.jpg'),
            'alt' => (string) ($item->alt_text ?? ''),
        ])->all();

        return response()->json(['assets' => $assets]);
    }
}
