<?php

namespace App\Domains\Wishlist\Actions;

use App\Domains\Wishlist\Models\Wishlist;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoveFromWishlistAction
{
    public function execute(int $userId, mixed $tourId): void
    {
        $wishlist = Wishlist::where('user_id', $userId)
            ->where('tour_id', $tourId)
            ->first();

        if (! $wishlist) {
            throw new NotFoundHttpException('Tour not in wishlist.');
        }

        $wishlist->delete();
    }
}
