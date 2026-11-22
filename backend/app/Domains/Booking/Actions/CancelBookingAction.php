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

    /**
     * The cancellation transaction contains ONLY database work: validation,
     * the status transition, the audit log, and after-commit scheduling of
     * the cancellation emails. External I/O (Stripe refund, email transport,
     * Slack alerts) runs after commit, so a third-party outage can never
     * roll back — or hold locks across — the cancellation itself.
     */
    public function execute(string $reference, int $travelerId, ?string $reason = null): array
    {
        $booking = DB::transaction(function () use ($reference, $travelerId, $reason): Booking {
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

            // Cancellation emails are scheduled after commit: a rollback must
            // never send mail for a booking that stays active, and a mail
            // transport failure (caught below) must never fail the committed
            // cancellation.
            DB::afterCommit(function () use ($booking): void {
                $this->sendCancellationEmails($booking);
            });

            $this->audit->log(
                $booking,
                'traveler',
                $booking->traveler_id,
                'cancelled',
                $beforeState,
                Booking::STATUS_CANCELLED,
                $reason ? ['cancellation_reason' => $reason] : null,
            );

            return $booking;
        });

        // External Stripe refund I/O runs OUTSIDE the committed cancellation
        // transaction. A Stripe outage is contained inside ProcessRefundAction
        // (best-effort, alert-only), so the cancellation above stays durable
        // and no row locks are held across the network call.
        $this->processRefund->execute($booking);

        $booking->refresh()->load('tour');

        return BookingResponseDTO::fromBooking($booking);
    }

    private function sendCancellationEmails(Booking $booking): void
    {
        try {
            $booking->load(['traveler', 'tour.partnerRecord.user']);
        } catch (\Throwable $e) {
            logger()->error('Failed to send cancellation emails: ' . $e->getMessage());

            return;
        }

        if ($booking->traveler?->email) {
            try {
                Mail::to($booking->traveler->email)->send(new BookingCancelledMail($booking));
            } catch (\Throwable $e) {
                logger()->error('Failed to send cancellation emails: ' . $e->getMessage());
            }
        }

        // `tours.partner_id` references `partners.id` (repinned by the
        // fix_tours_partner_id_to_partners_table migration), so the
        // partner user is reached via `partnerRecord`, NOT the legacy
        // `partner` relation (which still points at users.id).
        if ($booking->tour?->partnerRecord?->user?->email) {
            try {
                Mail::to($booking->tour->partnerRecord->user->email)->send(new PartnerBookingCancelledMail($booking));
            } catch (\Throwable $e) {
                logger()->error('Failed to send cancellation emails: ' . $e->getMessage());
            }
        }
    }
}
