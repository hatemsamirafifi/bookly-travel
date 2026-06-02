<?php

namespace App\Domains\Wishlist\Actions;

use App\Domains\Wishlist\Models\Wishlist;
use App\Models\Tour;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AddToWishlistAction
{
    public function execute(int $userId, mixed $tourId): array
    {
        if (! is_numeric($tourId)) {
            throw new UnprocessableEntityHttpException('Invalid tour_id.');
        }

        $tour = Tour::find($tourId);

        if (! $tour) {
            throw new NotFoundHttpException('Tour not found.');
        }

        $existing = Wishlist::where('user_id', $userId)
            ->where('tour_id', $tourId)
            ->first();

        if ($existing) {
            throw new ConflictHttpException('Tour already in wishlist.');
        }

        $wishlist = Wishlist::create([
            'user_id' => $userId,
            'tour_id' => $tourId,
        ]);

        return [
            'data' => [
                'id' => $wishlist->id,
                'tour_id' => $wishlist->tour_id,
                'added_at' => $wishlist->created_at->toIso8601String(),
            ],
        ];
    }
}
