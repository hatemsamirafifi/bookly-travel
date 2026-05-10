<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingController
{
    public function store(Request $request, CreateBookingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'tour_slug'        => 'required|string|max:255',
            'tour_date'        => 'required|date_format:Y-m-d',
            'participant_count' => 'required|integer|min:1',
            'locale'           => 'sometimes|string|in:en,es,it|max:2',
            // FR-027: optional price the traveler saw on the tour detail page (cents)
            'page_load_price'  => 'sometimes|integer|min:0',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            throw new UnprocessableEntityHttpException('Idempotency-Key header is required.');
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $idempotencyKey)) {
            throw new UnprocessableEntityHttpException('Idempotency-Key must be a valid UUID v4.');
        }

        $dto = new CreateBookingDTO(
            tourSlug: $validated['tour_slug'],
            tourDate: $validated['tour_date'],
            participantCount: (int) $validated['participant_count'],
            locale: $validated['locale'] ?? 'en',
            idempotencyKey: $idempotencyKey,
            travelerId: (int) $request->user()->id,
            pageLoadPrice: isset($validated['page_load_price']) ? (int) $validated['page_load_price'] : null,
        );

        try {
            $result = $action->execute($dto);
            $status = $result['is_retry'] ? 200 : 201;

            $body = ['data' => $result['data']];

            // FR-027: surface price_changed so the frontend can prompt the traveler
            // to re-confirm at the new price before proceeding
            if (! $result['is_retry'] && ($result['price_changed'] ?? false)) {
                $body['price_changed'] = true;
            }

            return response()->json($body, $status);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
