<?php

namespace App\Domains\Wishlist\Actions;

use App\Domains\Wishlist\Models\Wishlist;

class GetWishlistStatusAction
{
    /**
     * @param  array<int|string>  $tourIds
     * @return array{data: array<int, bool>}
     */
    public function execute(int $userId, array $tourIds): array
    {
        if (empty($tourIds)) {
            return ['data' => []];
        }

        $tourIds = array_map('intval', $tourIds);

        $existing = Wishlist::where('user_id', $userId)
            ->whereIn('tour_id', $tourIds)
            ->pluck('tour_id')
            ->toArray();

        $result = [];
        foreach ($tourIds as $tourId) {
            $result[(string) $tourId] = in_array($tourId, $existing, true);
        }

        return [
            'data' => $result,
        ];
    }
}
