<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Actions\ProcessRefundAction;
use App\Mail\BookingCancelledMail;
use App\Mail\PartnerBookingCancelledMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CancelBookingAction
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function execute(string $reference, int $travelerId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($reference, $travelerId, $reason) {
            $booking = Booking::where('reference', $reference)->lockForUpdate()->first();

            if (! $booking) {
                throw new NotFoundHttpException('Booking not found.');
            }

            if ($booking->traveler_id !== $travelerId) {
                throw new AccessDeniedHttpException('You do not have access to this booking.');
            }

            // traveler-booking-api.md: 422 "Only confirmed bookings can be
            // cancelled." for already-cancelled/completed — no idempotent
            // 200 early-return.
            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw new UnprocessableEntityHttpException('Only confirmed bookings can be cancelled.');
            }

            if (! $booking->canCancel()) {
                throw new ConflictHttpException(
                    'This booking cannot be cancelled — the cancellation window has passed.'
                );
            }

            $beforeState = $booking->status;

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->processRefund->execute($booking);

            try {
                $booking->load(['traveler', 'tour.partnerRecord.user']);
                if ($booking->traveler?->email) {
                    $travelerEmail = $booking->traveler->email;
                    DB::afterCommit(function () use ($booking, $travelerEmail) {
                        try {
                            Mail::to($travelerEmail)->send(new BookingCancelledMail($booking));
                        } catch (\Throwable $e) {
                            logger()->error('Failed to send cancellation emails: ' . $e->getMessage());
                        }
                    });
                }
                // `tours.partner_id` references `partners.id` (repinned by the
                // fix_tours_partner_id_to_partners_table migration), so the
                // partner user is reached via `partnerRecord`, NOT the legacy
                // `partner` relation (which still points at users.id).
                if ($booking->tour?->partnerRecord?->user?->email) {
                    Mail::to($booking->tour->partnerRecord->user->email)->send(new PartnerBookingCancelledMail($booking));
                }
            } catch (\Exception $e) {
                // Prevent email failures from failing the cancellation transaction
                logger()->error('Failed to send cancellation emails: ' . $e->getMessage());
            }

            $this->audit->log(
                $booking,
                'traveler',
                $booking->traveler_id,
                'cancelled',
                $beforeState,
                Booking::STATUS_CANCELLED,
                $reason ? ['cancellation_reason' => $reason] : null,
            );

            $booking->refresh()->load('tour');

            return BookingResponseDTO::fromBooking($booking);
        });
    }
}
