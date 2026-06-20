<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspend an approved partner (Spec 013, FR-006).
 *
 * Removes the partner's published tours from public discovery (via
 * Partner::removeToursFromDiscovery()), deactivates the partner, and writes an
 * immutable `partner.suspend` governance audit entry.
 */
class SuspendPartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(User $actor, Partner $partner): Partner
    {
        abort_unless(
            $partner->canTransitionTo(PartnerStatus::Suspended),
            422,
            'Partner cannot be suspended from its current state.',
        );

        $before = ['onboarding_status' => $partner->onboarding_status, 'is_active' => $partner->is_active];

        return DB::transaction(function () use ($actor, $partner, $before) {
            $partner->update(['onboarding_status' => PartnerStatus::Suspended->value, 'is_active' => false]);
            $partner->refresh();
            $partner->removeToursFromDiscovery();

            $this->audit->log($actor, 'partner.suspend', $partner, $before, [
                'onboarding_status' => $partner->onboarding_status,
                'is_active' => $partner->is_active,
            ]);

            return $partner;
        });
    }
}