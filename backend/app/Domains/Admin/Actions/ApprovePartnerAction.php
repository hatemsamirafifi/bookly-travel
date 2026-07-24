<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Mail\PartnerApprovedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Approve a partner application (Spec 013, FR-006/FR-007).
 *
 * Asserts the lifecycle guard, activates the partner, notifies them by mail,
 * and writes an immutable `partner.approve` governance audit entry.
 */
class ApprovePartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Partner $partner): Partner
    {
        abort_unless($actor->can('approve', $partner), 403, 'You are not authorized to approve partners.');

        $partner = DB::transaction(function () use ($actor, $partner) {
            $locked = Partner::lockForUpdate()->find($partner->id) ?? $partner;

            abort_unless(
                $locked->canTransitionTo(PartnerStatus::Approved),
                422,
                'Partner cannot be approved from its current state.',
            );

            $before = ['onboarding_status' => $locked->onboarding_status, 'is_active' => $locked->is_active];

            $locked->update(['onboarding_status' => PartnerStatus::Approved->value, 'is_active' => true]);
            $locked->refresh();

            $this->audit->log($actor, 'partner.approve', $locked, $before, [
                'onboarding_status' => $locked->onboarding_status,
                'is_active' => $locked->is_active,
            ]);

            return $locked;
        });

        // Mail is sent after the transaction commits so a rollback never leaks it.
        if ($partner->user?->email) {
            Mail::to($partner->user->email)->queue(new PartnerApprovedMail($partner));
        }

        return $partner;
    }
}