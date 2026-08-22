<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Mail\PartnerSuspendedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Suspend an approved partner (Spec 013/015, FR-006/FR-011).
 *
 * Removes the partner's published tours from public discovery (via
 * Partner::removeToursFromDiscovery()), deactivates the partner, sends
 * localized suspension notification mail with reason, and writes an
 * immutable `partner.suspend` governance audit entry.
 */
class SuspendPartnerAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * @param  array{reason?: string}|string|null  $reason
     */
    public function execute(User $actor, Partner $partner, array|string|null $reason = null): Partner
    {
        abort_unless($actor->can('suspend', $partner), 403, 'You are not authorized to suspend partners.');

        $reasonText = is_array($reason) ? (string) ($reason['reason'] ?? '') : (string) ($reason ?? '');
        abort_if(trim($reasonText) === '', 422, 'A suspension reason is required.');

        $partner = DB::transaction(function () use ($actor, $partner, $reasonText) {
            $locked = Partner::lockForUpdate()->find($partner->id) ?? $partner;

            abort_unless(
                $locked->canTransitionTo(PartnerStatus::Suspended),
                422,
                'Partner cannot be suspended from its current state.',
            );

            $before = ['onboarding_status' => $locked->onboarding_status, 'is_active' => $locked->is_active];

            $locked->update(['onboarding_status' => PartnerStatus::Suspended->value, 'is_active' => false]);
            $locked->refresh();
            $locked->removeToursFromDiscovery();

            $this->audit->log($actor, 'partner.suspend', $locked, $before, [
                'onboarding_status' => $locked->onboarding_status,
                'is_active' => $locked->is_active,
            ], [
                'reason' => $reasonText,
            ]);

            return $locked;
        });

        if ($partner->user?->email) {
            Mail::to($partner->user->email)->queue(new PartnerSuspendedMail($partner, $reasonText));
        }

        return $partner;
    }
}
