<?php

namespace App\Domains\Partner\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitPartnerApplicationAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Partner $partner, array $data): Partner
    {
        abort_unless(
            $partner->onboarding_status === PartnerStatus::Rejected->value,
            422,
            'Only rejected partners can resubmit.'
        );

        return DB::transaction(function () use ($actor, $partner, $data) {
            $locked = Partner::lockForUpdate()->find($partner->id) ?? $partner;

            abort_unless(
                $locked->canTransitionTo(PartnerStatus::Pending),
                422,
                'Partner cannot be resubmitted from its current state.'
            );

            $before = [
                'onboarding_status' => $locked->onboarding_status,
                'is_active' => $locked->is_active,
                'rejection_reason' => $locked->profile?->rejection_reason,
            ];

            if ($locked->profile) {
                $locked->profile->update([
                    'company_name' => $data['company_name'] ?? $locked->profile->company_name,
                    'contact_email' => $data['contact_email'] ?? $locked->profile->contact_email,
                    'contact_phone' => $data['contact_phone'] ?? $locked->profile->contact_phone,
                    'business_description' => $data['business_description'] ?? $locked->profile->business_description,
                    'business_address' => $data['business_address'] ?? $locked->profile->business_address,
                    'tax_id' => $data['tax_id'] ?? $locked->profile->tax_id,
                    'payout_country' => $data['payout_country'] ?? $locked->profile->payout_country,
                    'website' => $data['website'] ?? $locked->profile->website,
                    'rejection_reason' => null,
                ]);
            }

            $locked->update([
                'onboarding_status' => PartnerStatus::Pending->value,
                'is_active' => false,
            ]);
            $locked->refresh();

            $this->audit->log($actor, 'partner.resubmit', $locked, $before, [
                'onboarding_status' => $locked->onboarding_status,
                'is_active' => $locked->is_active,
                'rejection_reason' => $locked->profile?->rejection_reason,
            ]);

            return $locked;
        });
    }
}
