<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reinstate a suspended partner (Spec 013, FR-006).
 *
 * Reactivates the partner (tours are NOT auto-republished — the partner must
 * resubmit them for the governed publishing flow, FR-005) and writes an
 * immutable `partner.reinstate` governance audit entry.
 */
class ReinstatePartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(User $actor, Partner $partner): Partner
    {
        abort_unless(
            $partner->canTransitionTo(PartnerStatus::Approved),
            422,
            'Partner cannot be reinstated from its current state.',
        );

        $before = ['onboarding_status' => $partner->onboarding_status, 'is_active' => $partner->is_active];

        return DB::transaction(function () use ($actor, $partner, $before) {
            $partner->update(['onboarding_status' => PartnerStatus::Approved->value, 'is_active' => true]);
            $partner->refresh();
            $partner->restoreToursToDiscovery();

            $this->audit->log($actor, 'partner.reinstate', $partner, $before, [
                'onboarding_status' => $partner->onboarding_status,
                'is_active' => $partner->is_active,
            ]);

            return $partner;
        });
    }
}