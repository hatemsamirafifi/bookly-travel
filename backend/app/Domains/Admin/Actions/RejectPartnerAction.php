<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Mail\PartnerRejectedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Reject a partner application (Spec 013, FR-007).
 *
 * A reason is required (validated by RejectPartnerRequest) and recorded in the
 * audit metadata. The partner is notified by mail and an immutable
 * `partner.reject` audit entry is written.
 */
class RejectPartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    /**
     * @param  array{rejection_reason?: string}  $data
     */
    public function execute(User $actor, Partner $partner, array $data): Partner
    {
        abort_unless(
            $partner->canTransitionTo(PartnerStatus::Rejected),
            422,
            'Partner cannot be rejected from its current state.',
        );

        $before = ['onboarding_status' => $partner->onboarding_status, 'is_active' => $partner->is_active];
        $reason = (string) ($data['rejection_reason'] ?? '');

        return DB::transaction(function () use ($actor, $partner, $before, $reason) {
            $partner->update(['onboarding_status' => PartnerStatus::Rejected->value, 'is_active' => false]);
            $partner->profile?->update(['rejection_reason' => $reason]);
            $partner->refresh();

            if ($partner->user?->email) {
                Mail::to($partner->user->email)->send(new PartnerRejectedMail($partner, $reason));
            }

            $this->audit->log(
                $actor,
                'partner.reject',
                $partner,
                $before,
                ['onboarding_status' => $partner->onboarding_status, 'is_active' => $partner->is_active],
                ['reason' => $reason],
            );

            return $partner;
        });
    }
}