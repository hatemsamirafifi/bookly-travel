<?php

namespace App\Domains\Booking\Controllers\Partner;

use App\Domains\Booking\Actions\GetPartnerBookingsAction;
use App\Domains\Booking\Actions\TransitionBookingStatusAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PartnerBookingController
{
    public function index(Request $request, GetPartnerBookingsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'tour_slug' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:confirmed,completed,cancelled,no_show',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
            'page' => 'sometimes|integer|min:1',
        ]);

        $result = $action->execute(
            partner: $request->user(),
            tourSlug: $validated['tour_slug'] ?? null,
            status: $validated['status'] ?? null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            page: (int) ($validated['page'] ?? 1),
        );

        return response()->json($result);
    }

    public function updateStatus(Request $request, string $reference, TransitionBookingStatusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:completed,no_show',
        ]);

        try {
            $result = $action->execute(
                reference: $reference,
                partner: $request->user(),
                targetStatus: $validated['status'],
            );

            return response()->json($result);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
