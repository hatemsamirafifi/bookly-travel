<?php

namespace App\Domains\Payment\Services;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;

class StripeAccountAuditService
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    /** @return array<string, bool|string|null> */
    public function snapshot(Partner $partner): array
    {
        return [
            'account_id' => $partner->stripe_account_id,
            'charges_enabled' => (bool) $partner->stripe_charges_enabled,
            'payouts_enabled' => (bool) $partner->stripe_payouts_enabled,
            'details_submitted' => (bool) $partner->stripe_details_submitted,
            'onboarding_completed' => (bool) $partner->stripe_onboarding_completed,
        ];
    }

    /** @param array<string, bool|string|null> $before */
    public function recordPartnerChange(Partner $partner, string $action, array $before): void
    {
        $after = $this->snapshot($partner);
        if ($before === $after) {
            return;
        }

        $partner->loadMissing('user');
        if ($partner->user) {
            $this->audit->logActor(
                actor: $partner,
                action: $action,
                target: $partner,
                before: $before,
                after: $after,
                metadata: [
                    'provider' => 'stripe',
                    'source' => 'partner_request',
                    'requesting_user_id' => $partner->user->id,
                ],
            );

            return;
        }

        $this->audit->logSystem(
            action: $action,
            target: $partner,
            before: $before,
            after: $after,
            metadata: ['provider' => 'stripe', 'source' => 'internal'],
        );
    }

    /** @param array<string, bool|string|null> $before */
    public function recordWebhookChange(Partner $partner, array $before, string $eventId): void
    {
        $after = $this->snapshot($partner);
        if ($before === $after) {
            return;
        }

        $this->audit->logSystem(
            action: 'partner.stripe_account.webhook_synced',
            target: $partner,
            before: $before,
            after: $after,
            metadata: [
                'provider' => 'stripe',
                'source' => 'stripe_webhook',
                'stripe_event_id' => $eventId,
            ],
        );
    }
}
