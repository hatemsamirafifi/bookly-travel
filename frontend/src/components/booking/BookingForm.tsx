'use client';

import { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Elements } from '@stripe/react-stripe-js';
import { getStripe } from '@/lib/stripe/stripe-client';
import ParticipantSelector from './ParticipantSelector';
import DateConfirmation from './DateConfirmation';
import PriceBreakdown from './PriceBreakdown';
import PriceChangeModal from './PriceChangeModal';
import StripePaymentForm from './StripePaymentForm';
import { createBooking } from '@/lib/api/bookings';
import { cancelBooking } from '@/lib/api/my-bookings';
import { getTourDetail } from '@/lib/api/tours';
import type { TourDetail } from '@/lib/api/types';

interface BookingFormProps {
  locale: string;
}

/**
 * Booking form — composes ParticipantSelector, DateConfirmation, PriceBreakdown.
 *
 * FR-027: When the API signals price_changed: true (i.e., the tour price changed
 * between page load and booking confirmation), a PriceChangeModal intercepts the
 * flow and requires the traveler to explicitly re-confirm at the new price.
 * The original booking IS already created server-side at this point (the booking
 * is confirmed), so re-confirmation here means the traveler accepts the price
 * and is redirected to the confirmation page.
 */
export default function BookingForm({ locale }: BookingFormProps) {
  const router = useRouter();
  const searchParams = useSearchParams();

  const tourSlug = searchParams.get('tour') || '';
  const date = searchParams.get('date') || '';

  const [tour, setTour] = useState<TourDetail | null>(null);
  const [participants, setParticipants] = useState(1);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Two-step payment flow state
  const [paymentStep, setPaymentStep] = useState<{
    clientSecret: string;
    bookingReference: string;
    stripe_publishable_key: string;
  } | null>(null);

  // FR-027: price-change modal state
  const [priceChangeModal, setPriceChangeModal] = useState<{
    visible: boolean;
    bookingReference: string;
    oldPriceFormatted: string;
    newPriceFormatted: string;
  } | null>(null);

  useEffect(() => {
    if (!tourSlug) {
      setLoading(false);
      return;
    }

    getTourDetail(tourSlug, locale)
      .then((res) => {
        setTour(res.data);
        const initialParticipants = parseInt(searchParams.get('participants') || '', 10);
        if (initialParticipants >= res.data.group_size.min && initialParticipants <= res.data.group_size.max) {
          setParticipants(initialParticipants);
        } else {
          setParticipants(res.data.group_size.min);
        }
      })
      .catch(() => setError('Failed to load tour details.'))
      .finally(() => setLoading(false));
  }, [tourSlug, locale, searchParams]);

  const formatPrice = (amountCents: number, currency: string): string => {
    const symbol = currency === 'EUR' ? '€' : currency === 'USD' ? '$' : '';
    return `${symbol}${(amountCents / 100).toFixed(2)}`;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!tourSlug || !date) return;

    setSubmitting(true);
    setError(null);

    try {
      const pageLoadPriceCents = tour?.pricing?.base_price?.amount;

      const result = await createBooking({
        tour_slug: tourSlug,
        tour_date: date,
        participant_count: participants,
        locale,
        page_load_price: pageLoadPriceCents,
      });

      // FR-027: price drifted — surface the modal before forwarding to payment
      if (result.price_changed && result.data.pricing) {
        const confirmedPrice = result.data.pricing.price_per_person;
        const oldFormatted = pageLoadPriceCents
          ? formatPrice(pageLoadPriceCents, confirmedPrice.currency)
          : confirmedPrice.formatted;

        setPriceChangeModal({
          visible: true,
          bookingReference: result.data.reference,
          oldPriceFormatted: oldFormatted,
          newPriceFormatted: confirmedPrice.formatted,
        });
        // Store payment info for after modal confirmation
        if (result.payment) {
          setPaymentStep({
            clientSecret: result.payment.client_secret,
            bookingReference: result.data.reference,
            stripe_publishable_key: result.payment.stripe_publishable_key,
          });
        }
        return;
      }

      // Enter payment step with Stripe Elements
      if (result.payment) {
        setPaymentStep({
          clientSecret: result.payment.client_secret,
          bookingReference: result.data.reference,
          stripe_publishable_key: result.payment.stripe_publishable_key,
        });
      } else {
        router.push(`/${locale}/booking/confirmation?ref=${result.data.reference}`);
      }
    } catch (err: any) {
      if (err.status === 409) {
        setError('This tour date is sold out. Please select a different date.');
      } else if (err.status === 422) {
        setError('Invalid booking details. Please check your selection.');
      } else if (err.status === 429) {
        setError('Too many booking attempts. Please wait a moment and try again.');
      } else {
        setError('Something went wrong. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handlePaymentSuccess = () => {
    if (paymentStep) {
      router.push(`/${locale}/booking/confirmation?ref=${paymentStep.bookingReference}`);
    }
  };

  const handlePaymentError = (message: string) => {
    setError(message);
  };

  const stripePromise = paymentStep
    ? getStripe(paymentStep.stripe_publishable_key)
    : null;

  // FR-027: traveler explicitly accepts the new price — dismiss modal (payment step already set)
  const handlePriceChangeConfirm = () => {
    setPriceChangeModal(null);
  };

  // FR-027: traveler declines the new price — dismiss modal, clear payment step, and cancel booking on server
  const handlePriceChangeCancel = async () => {
    if (priceChangeModal?.bookingReference) {
      setSubmitting(true);
      try {
        await cancelBooking(priceChangeModal.bookingReference);
      } catch (err) {
        console.error('Failed to cancel rejected booking', err);
      } finally {
        setSubmitting(false);
      }
    }
    setPriceChangeModal(null);
    setPaymentStep(null);
  };

  if (loading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-16 bg-gray-100 rounded-lg" />
        <div className="h-32 bg-gray-100 rounded-lg" />
        <div className="h-24 bg-gray-100 rounded-lg" />
      </div>
    );
  }

  if (!tourSlug || !date) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-500">Please select a tour and date to continue.</p>
      </div>
    );
  }

  if (paymentStep && stripePromise) {
    return (
      <div className="space-y-6">
        <h2 className="text-lg font-semibold text-gray-800">Payment</h2>
        <Elements stripe={stripePromise} options={{ clientSecret: paymentStep.clientSecret }}>
          <StripePaymentForm
            clientSecret={paymentStep.clientSecret}
            onSuccess={handlePaymentSuccess}
            onError={handlePaymentError}
          />
        </Elements>
        {error && (
          <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
            {error}
          </div>
        )}
      </div>
    );
  }

  return (
    <>
      {/* FR-027: price-change re-confirmation modal */}
      {priceChangeModal?.visible && (
        <PriceChangeModal
          oldPrice={priceChangeModal.oldPriceFormatted}
          newPrice={priceChangeModal.newPriceFormatted}
          onConfirm={handlePriceChangeConfirm}
          onCancel={handlePriceChangeCancel}
        />
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <DateConfirmation date={date} tourSlug={tourSlug} locale={locale} />

        {tour && (
          <>
            <ParticipantSelector
              value={participants}
              onChange={setParticipants}
              min={tour.group_size.min}
              max={tour.group_size.max}
              pricePerPerson={tour.pricing.base_price.formatted}
            />

            <PriceBreakdown
              pricePerPerson={tour.pricing.base_price.formatted}
              participantCount={participants}
              total={formatPrice(tour.pricing.base_price.amount * participants, tour.pricing.base_price.currency)}
            />
          </>
        )}

        {error && (
          <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
            {error}
          </div>
        )}

        <button
          type="submit"
          disabled={submitting || !tourSlug || !date}
          className="w-full rounded-lg bg-blue-600 py-3 text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
        >
          {submitting ? 'Reserving...' : 'Confirm & Pay'}
        </button>
      </form>
    </>
  );
}
