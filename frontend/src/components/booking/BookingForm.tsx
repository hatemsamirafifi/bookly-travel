'use client';

import { useState, useEffect, useMemo, useRef } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
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
import { createBookingSchema } from '@/lib/validators/booking';
import { formatCurrency } from '@/lib/utils';
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
 *
 * F2: the Idempotency-Key is stable across retries of the same selection
 * (memoized on [tourSlug, date, participants]) so a double-submit or network
 * retry returns the same booking instead of creating a duplicate. A synchronous
 * `submittingRef` guard additionally prevents any double-click from issuing two
 * in-flight requests before the reactive `submitting` state updates.
 */
export default function BookingForm({ locale }: BookingFormProps) {
  const router = useRouter();
  const t = useTranslations('booking');
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

  // F2: stable across retries for the same selection, new on selection change.
  // Keep this key constant while the traveler confirms a specific tour/date/party
  // so the backend idempotency contract collapses retries into one booking. The
  // key itself is a fresh random UUID per selection; the deps drive regeneration
  // on selection change but are intentionally not read inside the factory (the
  // randomness is intrinsic), hence the exhaustive-deps suppression.
  const idempotencyKey = useMemo(
    () => generateIdempotencyKey(),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [tourSlug, date, participants],
  );

  // F2: synchronous guard against double-click / double-submit. Set before any
  // await so a second click in the same tick cannot start a second request.
  const submittingRef = useRef(false);

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
      .catch(() => setError(t('errors.loadFailed')))
      .finally(() => setLoading(false));
  }, [tourSlug, locale, searchParams, t]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // F2: synchronous double-submit guard — first line of defense, before await.
    if (submittingRef.current) return;
    submittingRef.current = true;

    const pageLoadPriceCents = tour?.pricing?.base_price?.amount;

    // Validate the booking payload at the boundary before the network call.
    const parsed = createBookingSchema.safeParse({
      tour_slug: tourSlug,
      tour_date: date,
      participant_count: participants,
      locale,
      page_load_price: pageLoadPriceCents,
    });
    if (!parsed.success) {
      setError(t('errors.invalidDetails'));
      submittingRef.current = false;
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const result = await createBooking(
        {
          tour_slug: tourSlug,
          tour_date: date,
          participant_count: participants,
          locale,
          page_load_price: pageLoadPriceCents,
        },
        idempotencyKey,
      );

      // FR-027: price drifted — surface the modal before forwarding to payment
      if (result.price_changed && result.data.pricing) {
        const confirmedPrice = result.data.pricing.price_per_person;
        const oldFormatted = pageLoadPriceCents
          ? formatCurrency(pageLoadPriceCents, confirmedPrice.currency, locale)
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
    } catch (err: unknown) {
      const status = typeof err === 'object' && err !== null && 'status' in err
        ? (err as { status?: number }).status
        : undefined;
      if (status === 409) {
        setError(t('errors.soldOut'));
      } else if (status === 422) {
        setError(t('errors.invalidDetails'));
      } else if (status === 429) {
        setError(t('errors.rateLimit'));
      } else {
        setError(t('errors.generic'));
      }
    } finally {
      setSubmitting(false);
      submittingRef.current = false;
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

  const totalFormatted = tour
    ? formatCurrency(tour.pricing.base_price.amount * participants, tour.pricing.base_price.currency, locale)
    : '';

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
        <p className="text-[#5A6B7B]">{t('selectPrompt')}</p>
      </div>
    );
  }

  if (paymentStep && stripePromise) {
    return (
      <div className="space-y-6">
        <h2 className="text-lg font-semibold text-[#0A2540]">{t('paymentHeading')}</h2>
        <Elements stripe={stripePromise} options={{ clientSecret: paymentStep.clientSecret, appearance: { theme: 'stripe', variables: { colorPrimary: '#0A2540', colorBackground: '#F7F9FB', colorText: '#0A2540', fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, sans-serif', borderRadius: '8px', }, }, }}>
          <StripePaymentForm
            clientSecret={paymentStep.clientSecret}
            bookingReference={paymentStep.bookingReference}
            locale={locale}
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
              total={totalFormatted}
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
          className="w-full rounded-xl bg-[#FFB800] py-3 text-base font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
        >
          {submitting ? t('confirming') : t('confirmButton')}
        </button>
      </form>
    </>
  );
}

/**
 * RFC 4122 v4 UUID for the Idempotency-Key header. `crypto.randomUUID()` is only
 * exposed in secure contexts (HTTPS or localhost); over plain HTTP it is
 * `undefined` and calling it throws. Fall back to `crypto.getRandomValues`,
 * then to Math.random.
 */
function generateIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 10
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}