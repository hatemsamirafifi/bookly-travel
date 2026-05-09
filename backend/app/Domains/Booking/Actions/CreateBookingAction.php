<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Booking\Services\AvailabilityService;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CreateBookingAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditService $audit,
    ) {}

    public function execute(CreateBookingDTO $dto): array
    {
        // Quick pre-check outside transaction — returns early for known duplicates
        $existing = Booking::where('idempotency_key', $dto->idempotencyKey)->first();
        if ($existing) {
            $existing->load('tour');

            return [
                'data' => BookingResponseDTO::fromBooking($existing),
                'is_retry' => true,
            ];
        }

        $tour = Tour::where('slug', $dto->tourSlug)->first();
        if (! $tour || $tour->status !== 'published') {
            throw new NotFoundHttpException('Tour not found.');
        }

        if ($dto->tourDate <= now()->toDateString()) {
            throw new UnprocessableEntityHttpException('Tour date must be in the future.');
        }

        $min = max(1, (int) ($tour->group_size_min ?? 1));
        $max = (int) ($tour->group_size_max ?? 10);

        if ($dto->participantCount < $min || $dto->participantCount > $max) {
            throw new UnprocessableEntityHttpException(
                "Participant count must be between {$min} and {$max}."
            );
        }

        // Everything below runs inside a single transaction:
        // idempotency re-check → availability lock → insert → audit
        $booking = DB::transaction(function () use ($dto, $tour) {
            // Re-check idempotency under transaction to close race window
            $existing = Booking::where('idempotency_key', $dto->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $availability = $this->availability->checkAndReserve($tour, $dto->tourDate, $dto->participantCount);

            if (! $availability['available']) {
                throw new ConflictHttpException(
                    "Only {$availability['remaining']} spots remaining for this date."
                );
            }

            $pricePerPerson = $tour->lowestPriceAmount();
            $totalPrice = $pricePerPerson * $dto->participantCount;

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'traveler_id' => $dto->travelerId,
                'tour_id' => $tour->id,
                'tour_date' => $dto->tourDate,
                'participant_count' => $dto->participantCount,
                'price_per_person' => $pricePerPerson,
                'total_price' => $totalPrice,
                'currency' => $tour->currency(),
                'status' => Booking::STATUS_CONFIRMED,
                'idempotency_key' => $dto->idempotencyKey,
                'cancellation_policy' => $tour->cancellation_policy ?? 'Free cancellation up to 24 hours before the tour start time.',
                'cancellation_window_hours' => $tour->cancellation_window_hours ?? 24,
                'locale' => $dto->locale,
            ]);

            $this->audit->log(
                $booking,
                'system',
                null,
                'created',
                null,
                Booking::STATUS_CONFIRMED,
                ['idempotency_key' => $dto->idempotencyKey],
            );

            SendBookingConfirmationEmail::dispatch($booking);

            return $booking;
        });

        $booking->load('tour');

        return [
            'data' => BookingResponseDTO::fromBooking($booking),
            'is_retry' => false,
        ];
    }
}
