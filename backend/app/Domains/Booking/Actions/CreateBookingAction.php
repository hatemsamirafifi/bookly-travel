<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CreateBookingAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditService $audit,
        private readonly CreatePaymentIntentAction $createPaymentIntent,
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
        // idempotency re-check → availability lock → insert → Payment Intent → audit
        [$booking, $priceChanged, $clientSecret] = DB::transaction(function () use ($dto, $tour) {
            // Re-check idempotency under transaction to close race window
            $existing = Booking::where('idempotency_key', $dto->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return [$existing, false, null];
            }

            $availability = $this->availability->checkAndReserve($tour, $dto->tourDate, $dto->participantCount);

            if (! $availability['available']) {
                throw new ConflictHttpException(
                    "Only {$availability['remaining']} spots remaining for this date."
                );
            }

            $pricePerPerson = $tour->lowestPriceAmount();
            $totalPrice = $pricePerPerson * $dto->participantCount;

            // FR-027: detect price drift between page load and confirmation time
            $priceChanged = $dto->pageLoadPrice !== null && $dto->pageLoadPrice !== $pricePerPerson;

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'traveler_id' => $dto->travelerId,
                'tour_id' => $tour->id,
                'tour_date' => $dto->tourDate,
                'participant_count' => $dto->participantCount,
                'price_per_person' => $pricePerPerson,
                'total_price' => $totalPrice,
                'currency' => $tour->currency(),
                'status' => Booking::STATUS_PENDING_PAYMENT,
                'idempotency_key' => $dto->idempotencyKey,
                'cancellation_policy' => $tour->cancellation_policy ?? 'Free cancellation up to 24 hours before the tour start time.',
                'cancellation_window_hours' => $tour->cancellation_window_hours ?? 24,
                'locale' => $dto->locale,
                'pending_expires_at' => now()->addMinutes(15),
            ]);

            try {
                $clientSecret = $this->createPaymentIntent->execute($booking);

                $booking->update(['stripe_payment_intent_id' => $this->extractIntentId($clientSecret)]);
            } catch (\Throwable $e) {
                throw new HttpException(503, 'Payment service temporarily unavailable. Please try again.');
            }

            $this->audit->log(
                $booking,
                'system',
                null,
                'created',
                null,
                Booking::STATUS_PENDING_PAYMENT,
                ['idempotency_key' => $dto->idempotencyKey],
            );

            return [$booking, $priceChanged, $clientSecret];
        });

        $booking->load('tour.translations');

        return [
            'data' => BookingResponseDTO::fromBooking($booking),
            'is_retry' => false,
            'price_changed' => $priceChanged,
            'payment' => $clientSecret ? [
                'client_secret' => $clientSecret,
                'stripe_publishable_key' => config('services.stripe.key'),
            ] : null,
        ];
    }

    private function extractIntentId(string $clientSecret): string
    {
        return explode('_secret_', $clientSecret)[0];
    }
}
