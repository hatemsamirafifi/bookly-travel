<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Booking\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin-initiated booking status transition (Spec 013, FR-008/FR-009).
 *
 * Records ONLY non-financial admin transitions (and the status side of
 * cancellation requests). Financial side-effects — refunds, ledger entries —
 * are NEVER executed here; for cancellation-requested/cancelled transitions
 * the refund is delegated to the payment domain (Spec 008). This action writes
 * a single immutable `booking.transition` governance audit entry.
 */
class TransitionBookingStatusAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(User $actor, Booking $booking, string $targetStatus): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $targetStatus) {
            // Re-read the committed status under a row lock so a concurrent admin
            // acting on a stale snapshot cannot force a contradictory double
            // transition (spec edge case: "no contradictory double transition").
            $locked = Booking::lockForUpdate()->find($booking->id) ?? $booking;

            abort_unless(
                $locked->canTransitionTo($targetStatus),
                422,
                'Booking cannot transition to that status from its current state.',
            );

            $before = ['status' => $locked->status];
            $financial = in_array($targetStatus, [
                Booking::STATUS_CANCELLED,
                Booking::STATUS_CANCELLATION_REQUESTED,
            ], true);

            $locked->update(['status' => $targetStatus]);

            if ($targetStatus === Booking::STATUS_CANCELLED && blank($locked->cancelled_at)) {
                $locked->update(['cancelled_at' => now()]);
            }

            $locked->refresh();

            // FR-009: financial side-effects (refund) are delegated to Spec 008
            // and executed there; this action only persists the status and the
            // governance audit trail. Spec 008 dispatch is wired separately.
            $this->audit->log(
                $actor,
                'booking.transition',
                $locked,
                $before,
                ['status' => $locked->status],
                ['to' => $targetStatus, 'financial' => $financial],
            );

            return $locked;
        });
    }
}