<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Services\StripeAccountAuditService;
use Illuminate\Support\Facades\Log;

class HandleAccountUpdated
{
    public function __construct(
        private readonly StripeAccountAuditService $accountAudit,
    ) {}

    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $account = data_get($event, 'data.object');
        $accountId = $account->id;

        $partner = Partner::where('stripe_account_id', $accountId)->first();
        if (! $partner) {
            Log::info('Partner not found for connected account update', ['stripe_account_id' => $accountId]);
            $this->markProcessed($eventId);
            return [];
        }

        $onboardingCompleted = (bool) ($account->details_submitted && $account->payouts_enabled);
        $before = $this->accountAudit->snapshot($partner);

        $partner->update([
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
            'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
            'stripe_details_submitted' => (bool) $account->details_submitted,
            'stripe_onboarding_completed' => $onboardingCompleted,
        ]);
        $partner->refresh();
        $this->accountAudit->recordWebhookChange($partner, $before, $eventId);

        Log::info('Updated partner Stripe account status via webhook', [
            'partner_id' => $partner->id,
            'stripe_account_id' => $accountId,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
        ]);

        $this->markProcessed($eventId);

        return [];
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
