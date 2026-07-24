'use client';

import { useState, useEffect } from 'react';
import { PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { useTranslations } from 'next-intl';

interface StripePaymentFormProps {
  clientSecret: string;
  bookingReference: string;
  locale: string;
  onSuccess: () => void;
  onError: (message: string) => void;
}

export default function StripePaymentForm({
  clientSecret,
  bookingReference,
  locale,
  onSuccess,
  onError,
}: StripePaymentFormProps) {
  const stripe = useStripe();
  const elements = useElements();
  const t = useTranslations('booking');
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    if (!stripe) {
      return;
    }

    const clientSecretParam = new URLSearchParams(window.location.search).get(
      'payment_intent_client_secret'
    );

    if (!clientSecretParam) {
      return;
    }

    stripe.retrievePaymentIntent(clientSecretParam).then(({ paymentIntent }) => {
      if (!paymentIntent) return;

      switch (paymentIntent.status) {
        case 'succeeded':
        case 'requires_capture':
          onSuccess();
          break;
        case 'processing':
          // The payment is processing, we can either wait or call onSuccess
          // Let's rely on webhooks for async processing or just notify user
          break;
        case 'requires_payment_method':
          onError(t('errors.paymentFailed'));
          break;
        default:
          onError(t('errors.generic'));
          break;
      }
    });
  }, [stripe, onSuccess, onError, t]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;

    setProcessing(true);

    try {
      const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          // 3DS / redirect completion lands on the confirmation page for this
          // booking so the traveler sees the right state after the redirect.
          return_url: `${window.location.origin}/${locale}/booking/confirmation?ref=${bookingReference}`,
        },
        redirect: 'if_required',
      });

      if (error) {
        onError(error.message || t('errors.paymentFailed'));
      } else if (!paymentIntent || paymentIntent.status === 'succeeded' || paymentIntent.status === 'requires_capture') {
        onSuccess();
      }
    } catch {
      onError(t('errors.paymentFailed'));
    } finally {
      // finally guarantees the button un-sticks even when confirmPayment
      // rejects or onSuccess/onError throw — otherwise the traveler could be
      // left with a permanently disabled "Processing..." button.
      setProcessing(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4" data-client-secret-present={Boolean(clientSecret)}>
      <PaymentElement />
      <button
        type="submit"
        disabled={!stripe || processing}
        className="w-full rounded-xl bg-[#FFB800] py-3 text-base font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
      >
        {processing ? t('paying') : t('payButton')}
      </button>
    </form>
  );
}