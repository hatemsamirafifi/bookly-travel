<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Actions\CancelBookingAction;
use App\Domains\Booking\Actions\GetTravelerBookingsAction;
use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TravelerBookingController
{
    public function summary(Request $request): JsonResponse
    {
        $counts = Booking::where('traveler_id', (int) $request->user()->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'total' => $counts->sum(),
                'confirmed' => (int) ($counts['confirmed'] ?? 0),
                'completed' => (int) ($counts['completed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
                'no_show' => (int) ($counts['no_show'] ?? 0),
            ],
        ]);
    }

    public function index(Request $request, GetTravelerBookingsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:confirmed,completed,cancelled,no_show',
        ]);

        $result = $action->execute(
            travelerId: (int) $request->user()->id,
            page: (int) ($validated['page'] ?? 1),
            status: $validated['status'] ?? null,
        );

        return response()->json($result);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $booking = Booking::with(['tour.translations', 'payment'])
            ->where('reference', $reference)
            ->first();

        if (! $booking) {
            throw new NotFoundHttpException('Booking not found.');
        }

        if ($booking->traveler_id !== (int) $request->user()->id) {
            throw new AccessDeniedHttpException('You do not have access to this booking.');
        }

        return response()->json([
            'data' => BookingResponseDTO::fromBooking($booking),
        ]);
    }

    public function cancel(Request $request, string $reference, CancelBookingAction $action): JsonResponse
    {
        // L4: HttpExceptions thrown by the action are rendered as JSON by
        // Laravel's handler for api-grouped requests.
        $data = $action->execute(
            reference: $reference,
            travelerId: (int) $request->user()->id,
            reason: $request->input('reason'),
        );

        return response()->json([
            'data' => $data,
            'message' => 'Booking cancelled. Refund is being processed.',
        ]);
    }
}
