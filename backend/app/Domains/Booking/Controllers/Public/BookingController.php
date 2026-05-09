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
            'tour_slug' => 'required|string|max:255',
            'tour_date' => 'required|date_format:Y-m-d',
            'participant_count' => 'required|integer|min:1',
            'locale' => 'sometimes|string|in:en,es,it|max:2',
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
        );

        try {
            $result = $action->execute($dto);
            $status = $result['is_retry'] ? 200 : 201;

            return response()->json(['data' => $result['data']], $status);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
