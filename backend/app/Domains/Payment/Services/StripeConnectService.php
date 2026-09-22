<?php

namespace App\Domains\Payment\Services;

use App\Domains\Partner\Models\Partner;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeConnectService
{
    private ?StripeClient $client = null;

    public function __construct(private readonly StripeAccountAuditService $accountAudit) {}

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret') ?? '');
    }

    /**
     * Create or retrieve a Stripe Express connected account for a partner.
     */
    public function createExpressAccount(Partner $partner, string $country = 'US', ?string $businessType = 'individual'): string
    {
        if (! empty($partner->stripe_account_id)) {
            return $partner->stripe_account_id;
        }

        $email = $partner->user?->email;
        $before = $this->accountAudit->snapshot($partner);

        $account = $this->client()->accounts->create([
            'type' => 'express',
            'country' => strtoupper($country),
            'email' => $email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_type' => $businessType ?? 'individual',
            'metadata' => [
                'partner_id' => (string) $partner->id,
                'origin' => 'bookly-travel',
            ],
        ]);

        $partner->update([
            'stripe_account_id' => $account->id,
            'stripe_charges_enabled' => $account->charges_enabled,
            'stripe_payouts_enabled' => $account->payouts_enabled,
            'stripe_details_submitted' => $account->details_submitted,
        ]);
        $partner->refresh();
        $this->accountAudit->recordPartnerChange($partner, 'partner.stripe_account.created', $before);

        Log::info('Created Stripe Express connected account for partner', [
            'partner_id' => $partner->id,
            'stripe_account_id' => $account->id,
        ]);

        return $account->id;
    }

    /**
     * Generate an onboarding Account Link for the partner to complete Stripe KYC.
     */
    public function createAccountLink(Partner $partner, string $returnUrl, string $refreshUrl): string
    {
        $accountId = $partner->stripe_account_id ?? $this->createExpressAccount($partner);

        $link = $this->client()->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    /**
     * Create a single-use login link to the Stripe Express Dashboard.
     */
    public function createLoginLink(Partner $partner): string
    {
        if (empty($partner->stripe_account_id)) {
            throw new \LogicException('Partner does not have an active Stripe connected account.');
        }

        $link = $this->client()->accounts->createLoginLink($partner->stripe_account_id);

        return $link->url;
    }

    /**
     * Sync and update account capabilities and status directly from Stripe API.
     */
    public function syncAccountStatus(Partner $partner): array
    {
        if (empty($partner->stripe_account_id)) {
            return [
                'connected' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'details_submitted' => false,
                'onboarding_completed' => false,
            ];
        }

        $account = $this->client()->accounts->retrieve($partner->stripe_account_id);
        $before = $this->accountAudit->snapshot($partner);

        $onboardingCompleted = $account->details_submitted && $account->payouts_enabled;

        $partner->update([
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
            'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
            'stripe_details_submitted' => (bool) $account->details_submitted,
            'stripe_onboarding_completed' => $onboardingCompleted,
        ]);
        $partner->refresh();
        $this->accountAudit->recordPartnerChange($partner, 'partner.stripe_account.synced', $before);

        return [
            'connected' => true,
            'stripe_account_id' => $account->id,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'onboarding_completed' => $onboardingCompleted,
        ];
    }
}
