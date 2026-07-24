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
 * A reason is required (validated inline by the Filament form) and recorded in
 * the audit metadata. The partner is notified by mail and an immutable
 * `partner.reject` audit entry is written.
 */
class RejectPartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * @param  array{rejection_reason?: string}  $data
     */
    public function execute(User $actor, Partner $partner, array $data): Partner
    {
        abort_unless($actor->can('reject', $partner), 403, 'You are not authorized to reject partners.');
        $reason = (string) ($data['rejection_reason'] ?? '');
        abort_if(trim($reason) === '', 422, 'A rejection reason is required.');

        $partner = DB::transaction(function () use ($actor, $partner, $reason) {
            $locked = Partner::lockForUpdate()->find($partner->id) ?? $partner;

            abort_unless(
                $locked->canTransitionTo(PartnerStatus::Rejected),
                422,
                'Partner cannot be rejected from its current state.',
            );

            $before = ['onboarding_status' => $locked->onboarding_status, 'is_active' => $locked->is_active];

            $locked->update(['onboarding_status' => PartnerStatus::Rejected->value, 'is_active' => false]);
            $locked->profile?->update(['rejection_reason' => $reason]);
            $locked->refresh();

            $this->audit->log(
                $actor,
                'partner.reject',
                $locked,
                $before,
                ['onboarding_status' => $locked->onboarding_status, 'is_active' => $locked->is_active],
                ['reason' => $reason],
            );

            return $locked;
        });

        // Mail is sent after the transaction commits so a rollback never leaks it.
        if ($partner->user?->email) {
            Mail::to($partner->user->email)->send(new PartnerRejectedMail($partner, $reason));
        }

        return $partner;
    }
}