<?php

namespace App\Domains\Payment\Services;

use LogicException;

/**
 * Fail-closed payment configuration gate for staging and production.
 *
 * Staging and production must never boot (or reach a Stripe call) with a
 * non-stripe adapter, a missing/empty Stripe credential, or a key from the
 * wrong mode (live keys on staging would move real money; test keys on
 * production would break live payments). Local and testing environments
 * return early so deterministic/fixture setups keep working.
 *
 * Exception messages describe WHAT is wrong, never the credential VALUES.
 */
class PaymentConfigurationGuard
{
    /**
     * @throws LogicException when staging/production payment configuration
     *                        is unsafe for real (or staging) money movement.
     */
    public function assertValid(?string $environment = null): void
    {
        $environment ??= app()->environment();

        if (! in_array($environment, ['staging', 'production'], true)) {
            return;
        }

        $gateway = config('services.payment.gateway');

        if ($gateway !== 'stripe') {
            throw new LogicException(
                "The [{$gateway}] payment gateway is not allowed in {$environment}; staging and production must use the stripe gateway."
            );
        }

        $key = config('services.stripe.key');
        $secret = config('services.stripe.secret');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException(
                "The [services.stripe.key] configuration value is missing or empty; {$environment} requires a real Stripe publishable key."
            );
        }

        if (! is_string($secret) || trim($secret) === '') {
            throw new LogicException(
                "The [services.stripe.secret] configuration value is missing or empty; {$environment} requires a real Stripe secret key."
            );
        }

        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            throw new LogicException(
                "The [services.stripe.webhook_secret] configuration value is missing or empty; {$environment} requires a real Stripe webhook signing secret."
            );
        }

        if (! str_starts_with($webhookSecret, 'whsec_')) {
            throw new LogicException(
                "The [services.stripe.webhook_secret] configuration value must start with whsec_; {$environment} requires a real Stripe webhook signing secret."
            );
        }

        $expectedKeyPrefix = $environment === 'production' ? 'pk_live_' : 'pk_test_';

        if (! str_starts_with($key, $expectedKeyPrefix)) {
            throw new LogicException(
                "The [services.stripe.key] configuration value is not a {$environment} publishable key; expected the {$expectedKeyPrefix} prefix."
            );
        }

        $expectedSecretPrefixes = $environment === 'production' ? ['sk_live_', 'rk_live_'] : ['sk_test_', 'rk_test_'];

        if (
            ! str_starts_with($secret, $expectedSecretPrefixes[0])
            && ! str_starts_with($secret, $expectedSecretPrefixes[1])
        ) {
            throw new LogicException(
                'The [services.stripe.secret] configuration value is not a ' . $environment . ' secret key; expected a '
                . $expectedSecretPrefixes[0] . ' or ' . $expectedSecretPrefixes[1] . ' prefix.'
            );
        }
    }
}
