'use client';

import { useState, useEffect, useMemo, useRef } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Elements } from '@stripe/react-stripe-js';
import { getStripe } from '@/lib/stripe/stripe-client';
import { useAuth } from '@/lib/hooks/useAuth';
import { safeAuthReturnUrl } from '@/lib/auth/return-url';
import {
  BOOKING_SUPPORTED_CURRENCY,
  buildAuthContinuationUrl,
  buildCheckoutUrl,
  buildRegisterContinuationUrl,
  getBookingSelectionView,
  isRealCalendarDateString,
} from '@/lib/tours/booking-selection';
import { resolveBookingReasonMessage } from '@/lib/tours/booking-reasons';
import ParticipantSelector from './ParticipantSelector';
import DateConfirmation from './DateConfirmation';
import PriceBreakdown from './PriceBreakdown';
import PriceChangeModal from './PriceChangeModal';
import StripePaymentForm from './StripePaymentForm';
import DeterministicPaymentForm from './DeterministicPaymentForm';
import { createBooking } from '@/lib/api/bookings';
import { cancelBooking } from '@/lib/api/my-bookings';
import { getTourDetail } from '@/lib/api/tours';
import { createBookingSchema } from '@/lib/validators/booking';
import { formatCurrency } from '@/lib/utils';
import type { TourDetail } from '@/lib/api/types';

interface BookingFormProps {
  locale: string;
  /** Injected UTC day (YYYY-MM-DD) for deterministic tests. Live pages omit
   *  it and the current UTC day is used at render and re-read at submit. */
  todayUtc?: string;
}

/** Bounded checkout auth-recovery target (Spec 020 US2): preserves the full
 *  selected tour/date/count booking query through safe login and surfaces the
 *  session-expired notice on return. The booking path itself carries only the
 *  selection (tour/participants/date) — never price, currency or provider
 *  parameters. The sessionExpired flag lives on the login URL only. */
export function buildCheckoutAuthRecoveryUrl(
  locale: string,
  tourSlug: string,
  participantCount: number,
  tourDate: string,
): string {
  const path =
    buildCheckoutUrl(locale, tourSlug, participantCount, tourDate) ??
    `/${locale}/booking?tour=${encodeURIComponent(tourSlug)}` +
      `&participants=${encodeURIComponent(String(participantCount))}` +
      `&date=${encodeURIComponent(tourDate)}`;
  const safe = safeAuthReturnUrl(locale, path);
  return `/${locale}/auth/login?sessionExpired=1&returnUrl=${encodeURIComponent(safe)}`;
}

type SuppliedCount =
  | { kind: 'absent' }
  | { kind: 'value'; count: number }
  | { kind: 'invalid'; raw: string };

/** Strict supplied-count parsing: only a missing parameter defaults
 *  downstream. An explicitly supplied empty, fractional, non-numeric or unsafe
 *  value stays invalid — never silently replaced by the minimum. */
function parseSuppliedCount(raw: string | null): SuppliedCount {
  if (raw === null) return { kind: 'absent' };
  if (!/^\d+$/.test(raw)) return { kind: 'invalid', raw };
  const count = Number(raw);
  if (!Number.isSafeInteger(count)) return { kind: 'invalid', raw };
  return { kind: 'value', count };
}

/**
 * Booking form — composes ParticipantSelector, DateConfirmation, PriceBreakdown.
 *
 * FR-027: When the API signals price_changed: true (i.e., the tour price changed
 * between page load and booking confirmation), a PriceChangeModal intercepts the
 * flow and requires the traveler to explicitly re-confirm at the new price.
 * The booking already exists server-side as pending_payment. Accepting the
 * updated price opens payment for that same reference; confirmation follows
 * only after the payment succeeds.
 *
 * F2: the Idempotency-Key is stable across retries of the same selection
 * (memoized on [tourSlug, date, participants]) so a double-submit or network
 * retry returns the same booking instead of creating a duplicate. A synchronous
 * `submittingRef` guard additionally prevents any double-click from issuing two
 * in-flight requests before the reactive `submitting` state updates.
 *
 * Spec 020 US2: the accepted selection view guards the checkout at render and
 * at submit — today/past/unlisted/unavailable/invalid bounds, amount, currency
 * or overflow disable the submit with a precise explanation and a tour
 * reselection route before any POST. Server 409/422/429 and price drift stay
 * authoritative for an otherwise valid projection. A known signed-out checkout
 * uses the safe full-query auth continuation instead of posting into a 401;
 * an expired-session 401 clears the stale stored token (never logout, which
 * would push home and drop the selection) and hard-navigates to safe login so
 * restoration starts fresh. Nothing is ever submitted automatically after any
 * auth return; the traveler always confirms explicitly.
 */
export default function BookingForm({ locale, todayUtc }: BookingFormProps) {
  const router = useRouter();
  const t = useTranslations('booking');
  const tTour = useTranslations('tour');
  const tCommon = useTranslations('common');
  const tAuth = useTranslations('auth');
  const searchParams = useSearchParams();
  const { user, isLoading: authRestoring } = useAuth();

  const tourSlug = searchParams.get('tour') || '';
  const date = searchParams.get('date') || '';
  const participantsParam = searchParams.get('participants');

  const [tour, setTour] = useState<TourDetail | null>(null);
  const [participants, setParticipants] = useState<number | null>(null);
  const [suppliedCountInvalid, setSuppliedCountInvalid] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [bookingAttempt, setBookingAttempt] = useState(0);

  // Two-step payment flow state
  type PaymentStep = {
    clientSecret: string;
    bookingReference: string;
    stripe_publishable_key: string | null;
    gateway: 'stripe' | 'deterministic';
    serverTotal: string | null;
    serverPerPerson: string | null;
  };
  const [paymentStep, setPaymentStep] = useState<PaymentStep | null>(null);

  // FR-027: price-change modal state
  const [priceChangeModal, setPriceChangeModal] = useState<{
    visible: boolean;
    bookingReference: string;
    oldPriceFormatted: string;
    newPriceFormatted: string;
    pendingPayment: PaymentStep | null;
    cancelError: string | null;
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
    [tourSlug, date, participants, bookingAttempt],
  );

  // F2: synchronous guard against double-click / double-submit. Set before any
  // await so a second click in the same tick cannot start a second request.
  const submittingRef = useRef(false);

  // Current readable selection identity: a same-path query transition must
  // withhold the previous selection until the new fetch resolves. Auth
  // changes keep the same key, so a pending paymentStep/reference survives
  // auth restoration untouched.
  const queryKey = JSON.stringify([locale, tourSlug, date, participantsParam]);
  const [activeQueryKey, setActiveQueryKey] = useState(queryKey);
  if (activeQueryKey !== queryKey) {
    setActiveQueryKey(queryKey);
    setTour(null);
    setParticipants(null);
    setSuppliedCountInvalid(null);
    setLoading(true);
    setError(null);
    setPaymentStep(null);
    setPriceChangeModal(null);
  }

  useEffect(() => {
    if (!tourSlug) {
      setLoading(false);
      return;
    }

    // Only the current request may publish: cleanup on query change or
    // unmount discards stale resolutions, so an older out-of-order response
    // never overwrites the newer query's accepted facts.
    let active = true;
    getTourDetail(tourSlug, locale)
      .then((res) => {
        if (!active) return;
        setTour(res.data);
        const supplied = parseSuppliedCount(participantsParam);
        if (supplied.kind === 'value') {
          if (
            supplied.count >= res.data.group_size.min &&
            supplied.count <= res.data.group_size.max
          ) {
            setParticipants(supplied.count);
            setSuppliedCountInvalid(null);
          } else {
            setParticipants(null);
            setSuppliedCountInvalid(participantsParam);
          }
        } else if (supplied.kind === 'invalid') {
          setParticipants(null);
          setSuppliedCountInvalid(supplied.raw);
        } else {
          setParticipants(res.data.group_size.min);
          setSuppliedCountInvalid(null);
        }
      })
      .catch(() => { if (active) setError(t('errors.loadFailed')); })
      .finally(() => { if (active) setLoading(false); });
    return () => {
      active = false;
    };
  }, [tourSlug, locale, searchParams, participantsParam, t]);

  const effectiveToday = todayUtc ?? new Date().toISOString().slice(0, 10);
  const dateMissing = date === '';
  const dateValid = !dateMissing && isRealCalendarDateString(date);
  const countValid = participants !== null && suppliedCountInvalid === null;

  // Accepted selection view over the refetched tour: the single eligibility
  // owner for date bounds, availability, pricing and estimate guards.
  const selectionView =
    tour && !dateMissing
      ? getBookingSelectionView({
          slug: tourSlug,
          locale,
          pricing: tour.pricing,
          availability: tour.availability,
          groupMin: tour.group_size.min,
          groupMax: tour.group_size.max,
          selectedDate: date,
          participantCount: participants,
          todayUtc: effectiveToday,
        })
      : null;
  const viewReasons = selectionView && suppliedCountInvalid === null && dateValid
    ? selectionView.reasons
    : [];
  const showEstimate = selectionView !== null && selectionView.estimateCents !== null;

  // Validated exact per-person base derived once from cents through the
  // accepted formatter — only after the accepted view's guards hold
  // (non-null estimate cents), never the raw server string (which can
  // disagree at the safe-integer extremes) and never formatted from invalid
  // data. The estimated total comes straight from the accepted view's
  // display-only estimate; server-confirmed payment totals stay authoritative.
  const baseDisplay =
    tour !== null && selectionView !== null && selectionView.estimateCents !== null
      ? formatCurrency(tour.pricing.base_price.amount, BOOKING_SUPPORTED_CURRENCY, locale)
      : null;
  const estimatedTotal =
    selectionView !== null && showEstimate && selectionView.estimateDisplay !== null
      ? selectionView.estimateDisplay
      : null;

  const signedOut = !authRestoring && user === null;
  const bookingPath =
    tour && dateValid && countValid && participants !== null
      ? buildCheckoutUrl(locale, tourSlug, participants, date)
      : null;
  const canSubmit =
    !submitting &&
    !authRestoring &&
    user !== null &&
    tour !== null &&
    (selectionView?.canContinue ?? false);

  const changeParticipants = (count: number) => {
    setParticipants(count);
    setSuppliedCountInvalid(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // F2: synchronous double-submit guard — first line of defense, before await.
    if (submittingRef.current) return;
    const count = participants;
    if (!canSubmit || count === null) {
      setError(t('errors.invalidDetails'));
      return;
    }
    submittingRef.current = true;

    // Recheck eligibility at submit time against the current UTC day: a stale
    // projection never reaches the network.
    const submitDay = todayUtc ?? new Date().toISOString().slice(0, 10);
    const fresh =
      tour &&
      getBookingSelectionView({
        slug: tourSlug,
        locale,
        pricing: tour.pricing,
        availability: tour.availability,
        groupMin: tour.group_size.min,
        groupMax: tour.group_size.max,
        selectedDate: date,
        participantCount: count,
        todayUtc: submitDay,
      });
    if (!fresh || !fresh.canContinue) {
      setError(t('errors.invalidDetails'));
      submittingRef.current = false;
      return;
    }

    const pageLoadPriceCents = tour?.pricing?.base_price?.amount;

    // Validate the booking payload at the boundary before the network call.
    const parsed = createBookingSchema.safeParse({
      tour_slug: tourSlug,
      tour_date: date,
      participant_count: count,
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
          participant_count: count,
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
          pendingPayment: result.payment ? {
            clientSecret: result.payment.client_secret,
            bookingReference: result.data.reference,
            stripe_publishable_key: result.payment.stripe_publishable_key,
            gateway: result.payment.gateway,
            serverTotal: result.data.pricing?.total?.formatted ?? result.data.total_price?.formatted ?? null,
            serverPerPerson: result.data.pricing.price_per_person.formatted,
          } : null,
          cancelError: null,
        });
        return;
      }

      // Enter payment step with Stripe Elements
      if (result.payment) {
        setPaymentStep({
          clientSecret: result.payment.client_secret,
          bookingReference: result.data.reference,
          stripe_publishable_key: result.payment.stripe_publishable_key,
          gateway: result.payment.gateway,
          serverTotal: result.data.pricing?.total?.formatted ?? result.data.total_price?.formatted ?? null,
          serverPerPerson: result.data.pricing?.price_per_person?.formatted ?? null,
        });
      } else {
        router.push(`/${locale}/booking/confirmation?ref=${result.data.reference}`);
      }
    } catch (err: unknown) {
      const status = typeof err === 'object' && err !== null && 'status' in err
        ? (err as { status?: number }).status
        : undefined;
      if (status === 401) {
        // Expired session: clear the stale token (never logout — it would
        // push home and drop the selection) and hard-navigate to safe login
        // so auth restoration starts fresh. The traveler explicitly
        // resubmits after return; nothing posts automatically.
        localStorage.removeItem('auth_token');
        window.location.assign(
          buildCheckoutAuthRecoveryUrl(locale, tourSlug, count, date),
        );
        return;
      }
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

  const stripePromise = paymentStep?.gateway === 'stripe' && paymentStep.stripe_publishable_key
    ? getStripe(paymentStep.stripe_publishable_key)
    : null;

  // FR-027: traveler explicitly accepts the new price — dismiss modal (payment step already set)
  const handlePriceChangeConfirm = () => {
    if (!priceChangeModal || submitting) return;
    if (priceChangeModal.pendingPayment) {
      setPaymentStep(priceChangeModal.pendingPayment);
    } else {
      router.push(`/${locale}/booking/confirmation?ref=${priceChangeModal.bookingReference}`);
    }
    setPriceChangeModal(null);
  };

  // FR-027: traveler declines the new price — dismiss modal, clear payment step, and cancel booking on server
  const handlePriceChangeCancel = async () => {
    if (!priceChangeModal?.bookingReference || submitting) return;
    setSubmitting(true);
    try {
      await cancelBooking(priceChangeModal.bookingReference);
      setPriceChangeModal(null);
      setPaymentStep(null);
      setBookingAttempt((attempt) => attempt + 1);
    } catch {
      setPriceChangeModal((current) => current ? { ...current, cancelError: t('errors.cancelFailed') } : null);
    } finally {
      setSubmitting(false);
    }
  };

  const paymentSummary = paymentStep && tour && (
    <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
      <h2 className="text-lg font-semibold text-bookly-navy">{tour.title}</h2>
      <p className="mt-1 text-sm text-text-muted">{tour.location}</p>
      <dl className="mt-5 space-y-3 text-sm">
        <div className="flex justify-between gap-4"><dt className="text-text-muted">{t('date')}</dt><dd className="font-medium text-bookly-navy">{new Date(`${date}T00:00:00`).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' })}</dd></div>
        <div className="flex justify-between gap-4"><dt className="text-text-muted">{t('participants')}</dt><dd className="font-medium text-bookly-navy">{participants}</dd></div>
        {paymentStep.serverPerPerson && <div className="flex justify-between gap-4"><dt className="text-text-muted">{t('perPerson')}</dt><dd className="font-medium text-bookly-navy">{paymentStep.serverPerPerson}</dd></div>}
        {paymentStep.serverTotal && <div className="flex justify-between gap-4 border-t border-border pt-3"><dt className="font-semibold text-bookly-navy">{t('total')}</dt><dd className="font-bold text-bookly-navy">{paymentStep.serverTotal}</dd></div>}
      </dl>
    </div>
  );

  if (loading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-16 bg-gray-100 rounded-lg" />
        <div className="h-32 bg-gray-100 rounded-lg" />
        <div className="h-24 bg-gray-100 rounded-lg" />
      </div>
    );
  }

  if (!tourSlug || dateMissing) {
    return (
      <div className="text-center py-8">
        <p className="text-[#5A6B7B]">{t('selectPrompt')}</p>
      </div>
    );
  }

  if (paymentStep?.gateway === 'deterministic') {
    return (
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)] lg:items-start">
        {paymentSummary}
        <div className="space-y-6 rounded-xl border border-border bg-surface p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-bookly-navy">{t('paymentHeading')}</h2>
        <DeterministicPaymentForm
          bookingReference={paymentStep.bookingReference}
          clientSecret={paymentStep.clientSecret}
          onSuccess={handlePaymentSuccess}
          onError={handlePaymentError}
        />
        {error && (
          <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
            {error}
          </div>
        )}
        </div>
      </div>
    );
  }

  if (paymentStep && stripePromise) {
    return (
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)] lg:items-start">
        {paymentSummary}
        <div className="space-y-6 rounded-xl border border-border bg-surface p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-bookly-navy">{t('paymentHeading')}</h2>
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
      </div>
    );
  }

  const loginUrl = bookingPath ? buildAuthContinuationUrl(locale, bookingPath) : null;
  const registerUrl = bookingPath ? buildRegisterContinuationUrl(locale, bookingPath) : null;

  return (
    <>
      {/* FR-027: price-change re-confirmation modal */}
      {priceChangeModal?.visible && (
        <PriceChangeModal
          oldPrice={priceChangeModal.oldPriceFormatted}
          newPrice={priceChangeModal.newPriceFormatted}
          onConfirm={handlePriceChangeConfirm}
          onCancel={handlePriceChangeCancel}
          busy={submitting}
          error={priceChangeModal.cancelError}
        />
      )}

      <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)] lg:items-start">
        <div className="space-y-6 rounded-xl border border-border bg-surface p-5 shadow-sm">
          {tour && <div><h2 className="text-xl font-semibold text-bookly-navy">{tour.title}</h2><p className="mt-1 text-sm text-text-muted">{tour.location}</p></div>}
          {dateValid ? (
            <DateConfirmation date={date} tourSlug={tourSlug} locale={locale} />
          ) : (
            <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
              {t('errors.invalidDetails')}{' '}
              <Link
                href={`/${locale}/tours/${tourSlug}`}
                className="font-medium underline underline-offset-2"
              >
                {t('changeDate')}
              </Link>
            </div>
          )}
          {tour && participants !== null && (
            <ParticipantSelector
              value={participants}
              onChange={changeParticipants}
              min={tour.group_size.min}
              max={tour.group_size.max}
              pricePerPerson={baseDisplay ?? undefined}
            />
          )}
          {suppliedCountInvalid !== null && (
            <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
              {t('errors.invalidDetails')}{' '}
              <Link
                href={`/${locale}/tours/${tourSlug}`}
                className="font-medium underline underline-offset-2"
              >
                {t('changeDate')}
              </Link>
            </div>
          )}
          {viewReasons.length > 0 && tour && (
            <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
              <ul className="space-y-1">
                {viewReasons.map((reason) => {
                  const message = resolveBookingReasonMessage(reason, {
                    groupMin: tour.group_size.min,
                    groupMax: tour.group_size.max,
                  });
                  const text =
                    message.scope === 'common'
                      ? tCommon(message.key, message.values)
                      : tTour(message.key, message.values);
                  return <li key={reason}>{text}</li>;
                })}
              </ul>
              <Link
                href={`/${locale}/tours/${tourSlug}`}
                className="mt-2 inline-block font-medium underline underline-offset-2"
              >
                {t('changeDate')}
              </Link>
            </div>
          )}
        </div>
        <div className="space-y-4">
          {tour && participants !== null && showEstimate && baseDisplay !== null && estimatedTotal !== null && (
            <PriceBreakdown
              pricePerPerson={baseDisplay}
              participantCount={participants}
              total={estimatedTotal}
            />
          )}

        {error && (
          <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
            {error}
          </div>
        )}

        {signedOut && bookingPath && (
          <div className="rounded-xl border border-border bg-surface-alt p-4 text-sm">
            <p className="text-text-muted">{tTour('authRecovery.continueNotice')}</p>
            <div className="mt-3 flex gap-2">
              {loginUrl && (
                <Link
                  href={loginUrl}
                  className="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-bookly-gold px-4 py-2.5 text-sm font-semibold text-bookly-navy hover:bg-accent-dark"
                >
                  {tAuth('signin.submitButton')}
                </Link>
              )}
              {registerUrl && (
                <Link
                  href={registerUrl}
                  className="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-bookly-navy hover:bg-surface-alt"
                >
                  {tAuth('register.submitButton')}
                </Link>
              )}
            </div>
          </div>
        )}

        <button
          type="submit"
          disabled={!canSubmit}
          className="w-full rounded-xl bg-[#FFB800] py-3 text-base font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
        >
          {submitting ? t('confirming') : t('confirmButton')}
        </button>
        </div>
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
