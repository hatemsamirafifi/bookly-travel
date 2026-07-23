'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { getBookingDetail } from '@/lib/api/my-bookings';
import { formatCurrency } from '@/lib/utils';
import type { TravelerBooking } from '@/types/traveler';

interface BookingConfirmationProps {
  reference: string;
  locale: string;
}

type FetchState =
  | { kind: 'loading' }
  | { kind: 'loaded'; booking: TravelerBooking }
  | { kind: 'notFound' }
  | { kind: 'error'; message: string; retryable: boolean };

type TranslationFn = ReturnType<typeof useTranslations>;

const CONFIRMED_STATUSES = ['confirmed', 'completed'];
const CANCELLED_STATUSES = ['cancelled', 'cancellation_requested', 'no_show'];

/**
 * F17: the confirmation header must reflect the booking's actual status. Only
 * confirmed/completed bookings get the success checkmark + title; a pending
 * payment shows a neutral header so the traveler is not misled into thinking
 * the booking is already paid, and expired/cancelled states surface the right
 * title with a muted icon.
 */
function renderHeader(status: string, t: TranslationFn) {
  const circleClass = 'mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full';

  if (CONFIRMED_STATUSES.includes(status)) {
    return (
      <>
        <div className={`${circleClass} bg-[#F7F9FB]`}>
          <svg className="h-7 w-7 text-[#0A2540]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-[#0A2540]">{t('confirmationTitle')}</h2>
        <p className="mt-1 text-[#5A6B7B]">{t('confirmationSubheading')}</p>
      </>
    );
  }

  if (status === 'pending_payment') {
    return (
      <>
        <div className={`${circleClass} bg-[#F7F9FB]`}>
          <svg className="h-7 w-7 text-[#5A6B7B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-[#0A2540]">{t('statusPendingPayment')}</h2>
        <p className="mt-1 text-[#5A6B7B]">{t('pendingPaymentSubheading')}</p>
      </>
    );
  }

  if (status === 'expired') {
    return (
      <>
        <div className={`${circleClass} bg-[#F7F9FB]`}>
          <svg className="h-7 w-7 text-[#5A6B7B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-[#0A2540]">{t('expiredTitle')}</h2>
      </>
    );
  }

  if (CANCELLED_STATUSES.includes(status)) {
    return (
      <>
        <div className={`${circleClass} bg-[#F7F9FB]`}>
          <svg className="h-7 w-7 text-[#5A6B7B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-[#0A2540]">{t('cancelledTitle')}</h2>
      </>
    );
  }

  return (
    <>
      <div className={`${circleClass} bg-[#F7F9FB]`}>
        <svg className="h-7 w-7 text-[#5A6B7B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h2 className="text-xl font-bold text-[#0A2540]">{t('confirmationTitle')}</h2>
    </>
  );
}

export default function BookingConfirmation({ reference, locale }: BookingConfirmationProps) {
  const router = useRouter();
  const t = useTranslations('booking');
  const [state, setState] = useState<FetchState>({ kind: 'loading' });
  // Bumping retryCount re-runs the effect so the "Retry" button can refetch
  // without storing a function in state (which would trip the lint rule).
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    let cancelled = false;
    getBookingDetail(reference)
      .then((res) => {
        if (!cancelled) setState({ kind: 'loaded', booking: res.data });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        // F7: surface real errors instead of silently swallowing them.
        const status = typeof err === 'object' && err !== null && 'status' in err
          ? (err as { status?: number }).status
          : undefined;
        if (status === 401) {
          // Not authenticated — send the traveler to log in and back here.
          router.push(`/${locale}/login?redirect=/${locale}/booking/confirmation?ref=${encodeURIComponent(reference)}`);
          return;
        }
        if (status === 404) {
          setState({ kind: 'notFound' });
          return;
        }
        setState({ kind: 'error', message: t('errors.generic'), retryable: true });
      });
    return () => {
      cancelled = true;
    };
  }, [reference, locale, router, t, retryCount]);

  const handleRetry = () => {
    setState({ kind: 'loading' });
    setRetryCount((c) => c + 1);
  };

  if (state.kind === 'loading') {
    return <div className="animate-pulse space-y-4">
      <div className="h-8 bg-[#F7F9FB] rounded w-48" />
      <div className="h-32 bg-[#F7F9FB] rounded-lg" />
    </div>;
  }

  if (state.kind === 'notFound') {
    return <p className="text-[#5A6B7B]">{t('notFound')}</p>;
  }

  if (state.kind === 'error') {
    return (
      <div className="space-y-4">
        <p className="text-[#5A6B7B]" role="alert">{state.message}</p>
        {state.retryable && (
          <button
            type="button"
            onClick={handleRetry}
            className="rounded-xl bg-[#FFB800] px-4 py-2 text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600]"
          >
            {t('retry')}
          </button>
        )}
      </div>
    );
  }

  const booking = state.booking;
  const tourName = booking.tour.name || booking.tour.title || 'Tour';
  const participants = booking.participants ?? booking.participant_count ?? 1;
  // L6: shared currency formatter. Prefer the server-formatted string when the
  // API provides one; otherwise format the cents value with the booking currency.
  // L3: `pricing.total` is the canonical field; `total_amount`/`total_price` are
  // retained as back-compat fallbacks for older response shapes.
  const totalAmount = booking.pricing?.total ?? booking.total_amount ?? booking.total_price;
  const total = typeof totalAmount === 'object' && totalAmount?.formatted
    ? totalAmount.formatted
    : formatCurrency(
        typeof totalAmount === 'object' ? (totalAmount?.amount ?? 0) : (totalAmount ?? 0),
        (typeof totalAmount === 'object' ? totalAmount?.currency : undefined) || 'EUR',
        locale,
      );

  return (
    <div className="space-y-6">
      <div className="text-center">
        {renderHeader(booking.status, t)}
        <p className="mt-1 text-2xl font-mono font-bold text-[#0A2540]">{booking.reference}</p>
      </div>

      <div className="rounded-lg border border-gray-200 divide-y divide-gray-200">
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">{t('tourLabel')}</span>
          <span className="text-sm font-medium text-[#0A2540]">{tourName}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">{t('date')}</span>
          <span className="text-sm font-medium text-[#0A2540]">{booking.tour_date}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">{t('participants')}</span>
          <span className="text-sm font-medium text-[#0A2540]">{participants}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">{t('total')}</span>
          <span className="text-sm font-semibold text-[#0A2540]">{total}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">{t('status')}</span>
          <span className="inline-flex items-center rounded-full bg-[#F7F9FB] px-2.5 py-0.5 text-xs font-medium text-[#0A2540]">
            {booking.status}
          </span>
        </div>
      </div>

      <div className="rounded-lg bg-[#F7F9FB] p-4">
        <p className="text-sm text-[#5A6B7B]">{booking.cancellation_policy || t('keepReference')}</p>
      </div>
    </div>
  );
}