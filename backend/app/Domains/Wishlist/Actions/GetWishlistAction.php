<?php

namespace App\Domains\Wishlist\Actions;

use App\Domains\Wishlist\DTOs\WishlistItemDTO;
use App\Domains\Wishlist\Models\Wishlist;
use Illuminate\Pagination\LengthAwarePaginator;

class GetWishlistAction
{
    public function execute(int $userId, int $page = 1, string $locale = 'en'): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = Wishlist::with(['tour.translations'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(12, ['*'], 'page', $page);

        $data = collect($paginator->items())
            ->map(fn (Wishlist $item) => WishlistItemDTO::fromWishlist($item, $locale))
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
