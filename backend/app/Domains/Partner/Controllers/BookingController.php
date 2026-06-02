<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Requests\UpdateBookingStatusRequest;
use App\Domains\Partner\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController
{
    public function __construct(
        private readonly BookingService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        $filters = $request->validate([
            'status' => 'sometimes|string|in:confirmed,completed,cancelled,cancellation_requested',
            'tour_id' => 'sometimes|integer',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $bookings = $this->service->listForPartner($partnerId, $filters);

        return response()->json($bookings);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $booking = $this->service->getForPartner($reference, $partnerId);

        if (! $booking) {
            abort(404, 'Booking not found.');
        }

        return response()->json($booking);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, string $reference): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validated();

        try {
            if ($data['status'] === 'completed') {
                $this->service->markAsCompleted($reference, $partnerId);
            } elseif ($data['status'] === 'cancellation_requested') {
                $this->service->requestCancellation(
                    $reference,
                    $partnerId,
                    $data['cancellation_reason'] ?? '',
                    $data['evidence'] ?? [],
                );
            }
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            abort(404, 'Booking not found.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['message' => 'Status updated.']);
    }

    public function requestCancellation(Request $request, string $reference): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'string|url',
        ]);

        try {
            $this->service->requestCancellation(
                $reference,
                $partnerId,
                $data['reason'],
                $data['evidence'] ?? [],
            );
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            abort(404, 'Booking not found.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json([
            'message' => 'Cancellation request submitted.',
            'reference' => $reference,
        ]);
    }
}