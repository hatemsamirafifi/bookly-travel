<?php

namespace App\Domains\Traveler\Controllers\Public;

use App\Domains\Wishlist\Actions\AddToWishlistAction;
use App\Domains\Wishlist\Actions\GetWishlistAction;
use App\Domains\Wishlist\Actions\GetWishlistStatusAction;
use App\Domains\Wishlist\Actions\RemoveFromWishlistAction;
use App\Domains\Wishlist\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WishlistController
{
    public function index(Request $request, GetWishlistAction $action): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
        ]);

        $result = $action->execute(
            userId: (int) $request->user()->id,
            page: (int) ($validated['page'] ?? 1),
            locale: $request->user()->locale ?? 'en',
        );

        return response()->json($result);
    }

    public function store(Request $request, AddToWishlistAction $action): JsonResponse
    {
        try {
            $result = $action->execute(
                userId: (int) $request->user()->id,
                tourId: $request->input('tour_id'),
            );

            return response()->json($result, 201);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $tour_id, RemoveFromWishlistAction $action): JsonResponse
    {
        try {
            $action->execute(
                userId: (int) $request->user()->id,
                tourId: $tour_id,
            );

            return response()->json(null, 204);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function status(Request $request, GetWishlistStatusAction $action): JsonResponse
    {
        $tourIds = $request->input('tour_ids', []);

        $result = $action->execute(
            userId: (int) $request->user()->id,
            tourIds: is_array($tourIds) ? $tourIds : [$tourIds],
        );

        return response()->json($result);
    }

    public function merge(Request $request, AddToWishlistAction $action): JsonResponse
    {
        $validated = $request->validate([
            'tour_ids' => 'required|array|max:200',
            'tour_ids.*' => 'integer|min:1',
        ]);

        $userId = (int) $request->user()->id;
        $mergedCount = 0;

        foreach ($validated['tour_ids'] as $tourId) {
            try {
                $action->execute(
                    userId: $userId,
                    tourId: $tourId,
                );
                $mergedCount++;
            } catch (ConflictHttpException) {
                // Tour is already in the wishlist, deduplicate silently
            } catch (NotFoundHttpException|UnprocessableEntityHttpException) {
                // Ignore missing tours or invalid references gracefully
            }
        }

        $totalWishlistCount = Wishlist::where('user_id', $userId)->count();

        return response()->json([
            'status' => 'success',
            'merged_count' => $mergedCount,
            'total_wishlist_count' => $totalWishlistCount,
        ]);
    }
}
