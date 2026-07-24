<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingController
{
    public function store(Request $request, CreateBookingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'tour_slug' => 'required|string|max:255',
            'tour_date' => 'required|date_format:Y-m-d',
            'participant_count' => 'required|integer|min:1',
            // L1: locale is required by booking-api.md (en|es|it).
            'locale' => 'required|string|in:en,es,it',
            // FR-027: optional price the traveler saw on the tour detail page (cents)
            'page_load_price' => 'sometimes|integer|min:0',
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
            locale: $validated['locale'],
            idempotencyKey: $idempotencyKey,
            travelerId: (int) $request->user()->id,
            pageLoadPrice: isset($validated['page_load_price']) ? (int) $validated['page_load_price'] : null,
        );

        // L4: Symfony HttpExceptions and ValidationException thrown by the
        // action are rendered as JSON ({message}, plus {errors} for validation)
        // by Laravel's exception handler for api-grouped requests — no need to
        // re-catch and re-map them here.
        $result = $action->execute($dto);
        $status = $result['is_retry'] ? 200 : 201;

        $body = ['data' => $result['data']];

        // Forward the payment block (client_secret + stripe_publishable_key) so
        // the frontend can mount Stripe Elements. Absent for non-pending
        // bookings (confirmed/completed) and retries that already hold a secret.
        if ($result['payment'] ?? null) {
            $body['payment'] = $result['payment'];
        }

        // FR-027: surface price_changed so the frontend can prompt the traveler
        // to re-confirm at the new price before proceeding. Emitted as an
        // explicit boolean on every response (per payment-api.md) so the
        // contract shape stays stable for the frontend regardless of outcome.
        $body['price_changed'] = ! $result['is_retry'] && ($result['price_changed'] ?? false);

        return response()->json($body, $status);
    }
}
