<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Mail\PartnerReinstatedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Reinstate a suspended partner (Spec 013/015, FR-006/FR-011).
 *
 * Reactivates the partner (tours are NOT auto-republished — the partner must
 * resubmit them for the governed publishing flow, FR-005) and writes an
 * immutable `partner.reinstate` governance audit entry.
 */
class ReinstatePartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Partner $partner): Partner
    {
        abort_unless($actor->can('unsuspend', $partner), 403, 'You are not authorized to reinstate partners.');

        $partner = DB::transaction(function () use ($actor, $partner) {
            $locked = Partner::lockForUpdate()->find($partner->id) ?? $partner;

            abort_unless(
                $locked->canTransitionTo(PartnerStatus::Approved),
                422,
                'Partner cannot be reinstated from its current state.',
            );

            $before = ['onboarding_status' => $locked->onboarding_status, 'is_active' => $locked->is_active];

            $locked->update(['onboarding_status' => PartnerStatus::Approved->value, 'is_active' => true]);
            $locked->refresh();
            $locked->restoreToursToDiscovery();

            $this->audit->log($actor, 'partner.reinstate', $locked, $before, [
                'onboarding_status' => $locked->onboarding_status,
                'is_active' => $locked->is_active,
            ]);

            return $locked;
        });

        if ($partner->user?->email) {
            Mail::to($partner->user->email)->queue(new PartnerReinstatedMail($partner));
        }

        return $partner;
    }
}
