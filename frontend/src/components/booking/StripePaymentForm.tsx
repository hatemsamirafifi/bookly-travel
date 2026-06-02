'use client';

import { useState, useEffect } from 'react';
import { PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';

interface StripePaymentFormProps {
  clientSecret: string;
  onSuccess: () => void;
  onError: (message: string) => void;
}

export default function StripePaymentForm({ clientSecret, onSuccess, onError }: StripePaymentFormProps) {
  const stripe = useStripe();
  const elements = useElements();
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
          onError('Your payment was not successful, please try again.');
          break;
        default:
          onError('Something went wrong. Please try again.');
          break;
      }
    });
  }, [stripe, onSuccess, onError]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;

    setProcessing(true);

    const { error, paymentIntent } = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: window.location.href,
      },
      redirect: 'if_required',
    });

    if (error) {
      onError(error.message || 'Payment failed. Please try again.');
    } else if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'requires_capture')) {
      onSuccess();
    }

    setProcessing(false);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <PaymentElement />
      <button
        type="submit"
        disabled={!stripe || processing}
        className="w-full rounded-xl bg-[#FFB800] py-3 text-base font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
      >
        {processing ? 'Processing...' : 'Pay Now'}
      </button>
    </form>
  );
}
