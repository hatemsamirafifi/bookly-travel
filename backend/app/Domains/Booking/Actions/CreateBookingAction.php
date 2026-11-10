<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateBookingAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditService $audit,
        private readonly CreatePaymentIntentAction $createPaymentIntent,
        private readonly TourTranslationService $translations,
    ) {}

    public function execute(CreateBookingDTO $dto): array
    {
        // Resolve the tour outside the transaction only for the early
        // 404 / draft checks — it is re-fetched locked inside the txn below.
        $tour = Tour::where('slug', $dto->tourSlug)->first();
        if (! $tour || $tour->status !== 'published') {
            throw new NotFoundHttpException('Tour not found.');
        }

        if ($dto->tourDate <= now()->toDateString()) {
            throw ValidationException::withMessages([
                'tour_date' => ['Tour date must be in the future.'],
            ]);
        }

        $date = Carbon::parse($dto->tourDate);

        // Phase 1 — DB transaction ONLY (no Stripe I/O). Creates the booking,
        // reserves availability, and writes the `created` audit entry. The
        // PaymentIntent is created after this commits (Phase 2) so a Stripe
        // outage can never leave an open DB transaction or an orphan intent
        // tied to an uncommitted booking.
        [$booking, $priceChanged, $isRetry] = DB::transaction(function () use ($dto, $tour, $date) {
            // F1 + F4: lock + refresh the tour row inside the txn. Concurrent
            // booking transactions for the same tour serialize on this row
            // (fixing the overbooking race), and price/capacity are read fresh
            // (fixing the stale-value window). Rules/exceptions are eager-loaded
            // (read-only) for the operating-schedule + start-time decisions.
            $tour = Tour::with(['availabilityRules', 'availabilityExceptions', 'translations', 'translationStates'])
                ->where('id', $tour->id)
                ->lockForUpdate()
                ->firstOrFail();

            // F3: idempotency re-check inside the txn (after the tour lock). A
            // concurrent request may have just committed this booking; if so,
            // return it as a retry instead of re-reserving capacity or
            // inserting. (A plain read, not lockForUpdate — the unique index
            // + the savepoint catch below close the true concurrent race.)
            $existing = Booking::where('idempotency_key', $dto->idempotencyKey)->first();
            if ($existing) {
                $this->assertSameBookingRequest($existing, $tour, $dto);
                $existing->load('tour.translations');

                return [$existing, false, true];
            }

            abort_unless($tour->status === 'published', 404, 'Tour not found.');

            // F9: the tour must actually operate on the requested date.
            if (! $tour->operatesOnDate($date)) {
                throw ValidationException::withMessages([
                    'tour_date' => ['This tour does not operate on the selected date.'],
                ]);
            }

            // Authoritative option re-read after the tour lock: price, capacity,
            // status and weekday availability must be current at confirmation
            // time, not whatever the page loaded.
            $option = $this->resolveOption($tour, $dto->tourOptionId);
            $this->validateParticipantCount($tour, $option, $dto->participantCount);

            // Snapshot the option EXACTLY as currently served in the requested
            // booking locale: the derivative texts when that derivative is
            // genuinely current, otherwise the authored source texts, with the
            // text's actual locale. A later rename, retire or regeneration
            // never rewrites what this traveler bought.
            $optionData = null;
            if ($option !== null) {
                $text = $this->translations->localizedOptionText($tour, $option, $dto->locale);
                $optionData = [
                    'id' => $option->id,
                    'title' => $text['title'],
                    'pickup_instructions' => $text['pickup_instructions'],
                    'content_locale' => $text['content_locale'],
                ];
            }

            if ($option !== null && ! $option->coversWeekday($date)) {
                throw ValidationException::withMessages([
                    'tour_option_id' => ['The selected option does not operate on the selected date.'],
                ]);
            }

            $availability = $this->availability->checkAndReserve($tour, $dto->tourDate, $dto->participantCount, $option);

            if (! $availability['available']) {
                throw new ConflictHttpException(
                    "Only {$availability['remaining']} spots remaining for this date."
                );
            }

            $pricePerPerson = $option->price_amount ?? $tour->lowestPriceAmount();
            $totalPrice = $pricePerPerson * $dto->participantCount;

            // FR-027: detect price drift between page load and confirmation time
            $priceChanged = $dto->pageLoadPrice !== null && $dto->pageLoadPrice !== $pricePerPerson;

            // F5: snapshot the start time — the selected option's own start time
            // wins, otherwise the operating availability rule's earliest start
            // (or the configured default) — so cancellation/no_show cutoffs
            // anchor to the actual start, not tour_date midnight.
            $startTime = $option?->startTimeString()
                ?? $tour->startTimeForDate($date)
                ?? config('bookings.default_start_time', '09:00');

            // F3 + F8: rely on the unique indexes (idempotency_key, reference)
            // as the source of truth for the concurrent same-key race. The
            // insert runs in its own savepoint so a unique-constraint failure
            // rolls back only the failed insert (Postgres otherwise aborts the
            // whole transaction), leaving the outer txn healthy for the re-read.
            // On 23505: if a row with this idempotency key now exists a peer
            // won the race — return it as a retry; otherwise it was a
            // reference collision and we regenerate and retry.
            $booking = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $booking = DB::transaction(fn () => Booking::create([
                        'reference' => Booking::generateReference(),
                        'traveler_id' => $dto->travelerId,
                        'tour_id' => $tour->id,
                        'tour_option_id' => $optionData['id'] ?? null,
                        // Snapshot: a later rename/retire/regeneration of the
                        // option must not rewrite what this traveler booked.
                        'tour_option_title' => $optionData['title'] ?? null,
                        'tour_option_pickup_instructions' => $optionData['pickup_instructions'] ?? null,
                        'tour_option_content_locale' => $optionData['content_locale'] ?? null,
                        'tour_date' => $dto->tourDate,
                        'start_time' => $startTime,
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
                    ]), 1);
                    break;
                } catch (QueryException $e) {
                    if (($e->errorInfo[0] ?? null) !== '23505') {
                        throw $e;
                    }

                    $existing = Booking::where('idempotency_key', $dto->idempotencyKey)->first();
                    if ($existing) {
                        $this->assertSameBookingRequest($existing, $tour, $dto);
                        $existing->load('tour.translations');

                        return [$existing, false, true];
                    }

                    // Reference collision — regenerate on the next attempt.
                }
            }

            if (! $booking) {
                throw new HttpException(503, 'Unable to allocate a unique booking reference. Please try again.');
            }

            $this->audit->log(
                $booking,
                'system',
                null,
                'created',
                null,
                Booking::STATUS_PENDING_PAYMENT,
                array_filter([
                    'idempotency_key' => $dto->idempotencyKey,
                    'tour_option_id' => $option?->id,
                ]),
            );

            $booking->load('tour.translations');

            return [$booking, $priceChanged, false];
        });

        // A booking that has already left pending_payment needs no PaymentIntent.
        if ($booking->status === Booking::STATUS_EXPIRED) {
            throw new HttpException(410, 'This booking attempt has expired. Please start a new booking.');
        }

        if (in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true)) {
            return [
                'data' => BookingResponseDTO::fromBooking($booking),
                'is_retry' => $isRetry,
                'price_changed' => $priceChanged,
                'payment' => null,
            ];
        }

        // Phase 2 — create the PaymentIntent AFTER the DB transaction commits.
        // CreatePaymentIntentAction reuses a stored client_secret on retry
        // (no duplicate intent, no Stripe call); only a fresh (or never-
        // completed) pending_payment booking hits Stripe.
        try {
            $clientSecret = $this->createPaymentIntent->execute($booking);
        } catch (\Throwable $e) {
            Log::error('PaymentIntent creation failed; marking booking expired', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference,
                'error' => $e->getMessage(),
            ]);

            $this->markExpiredAfterPaymentFailure($booking);

            throw new HttpException(503, 'Payment service temporarily unavailable. Please try again.');
        }

        return [
            'data' => BookingResponseDTO::fromBooking($booking),
            'is_retry' => $isRetry,
            'price_changed' => $priceChanged,
            'payment' => [
                'client_secret' => $clientSecret,
                'stripe_publishable_key' => config('services.payment.gateway', 'stripe') === 'stripe' ? config('services.stripe.key') : null,
                'gateway' => config('services.payment.gateway', 'stripe'),
            ],
        ];
    }

    /**
     * Resolve the option the traveler asked for, or null for a tour that
     * offers none.
     *
     * Rejections (all 422 on `tour_option_id`, never a silent fallback):
     *  - unknown id, or an id belonging to another tour (forged/foreign);
     *  - a retired option;
     *  - no selection on a tour that uses explicit options.
     *
     * Tours with no options at all keep the legacy option-less booking path.
     */
    private function resolveOption(Tour $tour, ?int $optionId): ?TourOption
    {
        if ($optionId !== null) {
            $option = TourOption::query()
                ->where('tour_id', $tour->id)
                ->whereKey($optionId)
                ->first();

            if (! $option) {
                throw ValidationException::withMessages([
                    'tour_option_id' => ['The selected option is not available for this tour.'],
                ]);
            }

            if (! $option->isActive()) {
                throw ValidationException::withMessages([
                    'tour_option_id' => ['The selected option is no longer available.'],
                ]);
            }

            return $option;
        }

        if (TourOption::query()->where('tour_id', $tour->id)->exists()) {
            throw ValidationException::withMessages([
                'tour_option_id' => ['Please select an option for this tour.'],
            ]);
        }

        return null;
    }

    private function validateParticipantCount(Tour $tour, ?TourOption $option, int $count): void
    {
        $min = max(1, (int) ($tour->group_size_min ?? 1));
        $max = $option?->effectiveCapacity($tour) ?? (int) ($tour->group_size_max ?? 10);

        if ($count < $min || $count > $max) {
            throw ValidationException::withMessages([
                'participant_count' => ["Participant count must be between {$min} and {$max}."],
            ]);
        }
    }

    private function assertSameBookingRequest(Booking $booking, Tour $tour, CreateBookingDTO $dto): void
    {
        if ((int) $booking->traveler_id !== $dto->travelerId
            || (int) $booking->tour_id !== $tour->id
            || $booking->tour_option_id !== $dto->tourOptionId
            || $booking->tour_date->toDateString() !== $dto->tourDate
            || $booking->participant_count !== $dto->participantCount) {
            throw new ConflictHttpException('This Idempotency-Key is already in use.');
        }
    }

    /**
     * Mark a pending_payment booking expired after PaymentIntent creation
     * failed. Moving the status out of the counted set
     * (pending_payment|confirmed|completed) releases the reserved availability
     * immediately, and the audit entry records why.
     */
    private function markExpiredAfterPaymentFailure(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $locked = Booking::lockForUpdate()->find($booking->id);
            if (! $locked || $locked->status !== Booking::STATUS_PENDING_PAYMENT) {
                return;
            }

            $beforeState = $locked->status;
            $locked->update(['status' => Booking::STATUS_EXPIRED]);

            $this->audit->log(
                $locked,
                'system',
                null,
                'payment_initialization_failed',
                $beforeState,
                Booking::STATUS_EXPIRED,
                ['reason' => 'payment_intent_creation_failed'],
            );
        });
    }
}
