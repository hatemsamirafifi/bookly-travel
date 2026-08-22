<?php

namespace App\Domains\Partner\Services;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use Carbon\Carbon;

class PartnerOnboardingService
{
    /**
     * Get the structured onboarding status for a partner.
     */
    public function getStatus(Partner $partner): array
    {
        $status = $partner->onboarding_status;
        if ($status === 'incomplete') {
            $status = 'pending';
        }

        $canCreateTours = $this->canCreateTours($partner);
        $rejectionReason = $status === 'rejected' ? $this->getRejectionReason($partner) : null;
        $suspensionReason = $status === 'suspended' ? $this->getSuspensionReason($partner) : null;

        $submittedAt = $partner->created_at?->toIso8601String();
        $approvedAt = $this->getLifecycleTimestamp($partner, 'approve')?->toIso8601String();
        $rejectedAt = $this->getLifecycleTimestamp($partner, 'reject')?->toIso8601String();
        $suspendedAt = $this->getLifecycleTimestamp($partner, 'suspend')?->toIso8601String();

        $message = match ($status) {
            'pending' => 'Your application is under review by our team.',
            'approved' => 'Your account is approved. You can now create and manage tours.',
            'rejected' => 'Your application was not approved. Please review feedback and resubmit.',
            'suspended' => 'Your account has been suspended. Please contact support for more information.',
            default => 'Unknown onboarding status.',
        };

        $data = [
            'onboarding_status' => $status,
            'can_create_tours' => $canCreateTours,
            'rejection_reason' => $rejectionReason,
            'submitted_at' => $submittedAt,
            'message' => $message,
        ];

        if ($status === 'approved') {
            $data['approved_at'] = $approvedAt;
        } elseif ($status === 'rejected') {
            $data['rejected_at'] = $rejectedAt;
        } elseif ($status === 'suspended') {
            $data['suspended_at'] = $suspendedAt;
            $data['suspension_reason'] = $suspensionReason;
        }

        return $data;
    }

    /**
     * Check if the partner is permitted to create and publish tours.
     */
    public function canCreateTours(Partner $partner): bool
    {
        return $partner->onboarding_status === 'approved' && (bool) $partner->is_active;
    }

    /**
     * Get the rejection reason from the partner's profile.
     */
    public function getRejectionReason(Partner $partner): ?string
    {
        if (! $partner->relationLoaded('profile')) {
            $partner->load('profile');
        }

        return $partner->profile?->rejection_reason;
    }

    /**
     * Get the suspension reason from the latest governance audit log.
     */
    public function getSuspensionReason(Partner $partner): ?string
    {
        $targetTypes = array_unique(array_filter([
            $partner->getMorphClass(),
            'partner',
            Partner::class,
        ]));

        $log = GovernanceAuditLog::query()
            ->whereIn('target_type', $targetTypes)
            ->where('target_id', $partner->id)
            ->where('action', 'partner.suspend')
            ->latest('id')
            ->first();

        return $log?->metadata['reason'] ?? null;
    }

    /**
     * Query the immutable GovernanceAuditLog for the latest lifecycle transition timestamp.
     */
    public function getLifecycleTimestamp(Partner $partner, string $action): ?Carbon
    {
        $actionName = str_starts_with($action, 'partner.') ? $action : 'partner.' . $action;

        $targetTypes = array_unique(array_filter([
            $partner->getMorphClass(),
            'partner',
            Partner::class,
        ]));

        $createdAt = GovernanceAuditLog::query()
            ->whereIn('target_type', $targetTypes)
            ->where('target_id', $partner->id)
            ->where('action', $actionName)
            ->latest('id')
            ->value('created_at');

        return $createdAt ? Carbon::parse($createdAt) : null;
    }
}
