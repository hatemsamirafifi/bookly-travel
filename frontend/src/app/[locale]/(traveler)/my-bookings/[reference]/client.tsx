'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { cancelBooking, getBookingDetail } from '@/lib/api/my-bookings';
import CancelBookingButton from '@/components/my-bookings/CancelBookingButton';
import ReviewForm from '@/components/reviews/ReviewForm';
import { submitReview } from '@/lib/reviews/review-api';
import type { TravelerBooking } from '@/types/traveler';

interface Props {
  reference: string;
  locale: string;
}

export default function BookingDetailClient({ reference, locale }: Props) {
  const router = useRouter();
  const [booking, setBooking] = useState<TravelerBooking | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const t = useTranslations();
  const detailT = useTranslations('traveler.bookingDetail');

  const loadBooking = useCallback(() => {
    setLoading(true);
    setError(null);
    getBookingDetail(reference)
      .then((res) => setBooking(res.data))
      .catch(() => setError(detailT('notFound')))
      .finally(() => setLoading(false));
  }, [detailT, reference]);

  useEffect(() => {
    void Promise.resolve().then(loadBooking);
  }, [loadBooking]);

  const timeline = useMemo(() => {
    if (!booking) return [];
    const items = [
      { label: detailT('bookingCreated'), timestamp: booking.created_at || booking.booking_date },
      { label: booking.payment ? detailT('paymentConfirmed') : undefined, timestamp: booking.payment?.transaction_date },
      { label: booking.status === 'cancelled' ? detailT('cancelled') : detailT('bookingConfirmed'), timestamp: booking.cancellation_date || booking.created_at },
    ];
    return items
      .filter((item) => Boolean(item.label))
        .map((item) => ({ label: item.label || '', timestamp: item.timestamp }));
  }, [booking, detailT]);

  const handleCancel = async () => {
    await cancelBooking(reference);
    router.refresh();
    loadBooking();
  };

  if (loading) {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 w-48 rounded bg-gray-100" />
        <div className="h-40 rounded-lg bg-gray-100" />
        <div className="h-40 rounded-lg bg-gray-100" />
      </div>
    );
  }

  if (error || !booking) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-6">
        <p className="text-gray-600">{error || detailT('notFound')}</p>
        <button onClick={loadBooking} className="mt-4 text-sm font-semibold text-[#0A2540] underline">
          {detailT('tryAgain')}
        </button>
      </div>
    );
  }

  const tourName = booking.tour.name || booking.tour.title || detailT('fallbackTour');
  const participants = booking.participants ?? booking.participant_count ?? 1;
  const total = formatMoneyValue(booking.total_amount ?? booking.total_price);
  const pricePerPerson = formatMoneyValue(booking.price_per_person);

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
      <section className="space-y-6">
        <div className="rounded-lg border border-gray-200 bg-white p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-sm text-gray-500">{detailT('reference')}</p>
              <p className="font-mono text-lg font-semibold text-gray-900">{booking.reference}</p>
            </div>
            <StatusBadge status={booking.status} />
          </div>
          <div className="mt-5 grid gap-4 sm:grid-cols-2">
            <Field label={detailT('tour')}>
              <Link href={`/${locale}/tours/${booking.tour.slug}`} className="font-semibold text-[#0A2540] underline">
                {tourName}
              </Link>
              <p className="text-sm text-gray-600">{booking.tour.location}</p>
            </Field>
            <Field label={detailT('dateTime')}>
              <p>{booking.tour_date}{booking.tour_time ? ` at ${booking.tour_time}` : ''}</p>
            </Field>
            <Field label={detailT('participants')}>
              <p>{participants}</p>
            </Field>
            <Field label={detailT('price')}>
              <p>{detailT('perPerson', { price: pricePerPerson })}</p>
              <p className="font-semibold">{detailT('total', { total })}</p>
            </Field>
          </div>
          {booking.special_requests && (
            <Field label={detailT('specialRequests')}>
              <p>{booking.special_requests}</p>
            </Field>
          )}
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-5">
          <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('tripDetails')}</h2>
          <div className="space-y-4">
            <Field label={detailT('meetingPoint')}>
              <p>{booking.tour.meeting_point || detailT('meetingPointFallback')}</p>
            </Field>
            {booking.tour.inclusions && booking.tour.inclusions.length > 0 && (
              <Field label={detailT('included')}>
                <ul className="list-inside list-disc text-sm text-gray-700">
                  {booking.tour.inclusions.map((item) => <li key={item}>{item}</li>)}
                </ul>
              </Field>
            )}
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-5">
          <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('statusTimeline')}</h2>
          <ol className="space-y-3">
            {timeline.map((item) => (
              <li key={`${item.label}-${item.timestamp}`} className="flex gap-3">
                <span className="mt-1 h-2.5 w-2.5 rounded-full bg-[#FFB800]" />
                <div>
                  <p className="text-sm font-medium text-gray-900">{item.label}</p>
                  {item.timestamp && <p className="text-xs text-gray-500">{new Date(item.timestamp).toLocaleString()}</p>}
                </div>
              </li>
            ))}
          </ol>
        </div>

        {booking.status === 'completed' && (
          <div className="rounded-lg border border-gray-200 bg-white p-5">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('reviews.leave_review')}</h2>
            <ReviewForm
              bookingReference={booking.reference}
              locale={locale}
              onSubmit={async (data) => {
                await submitReview({
                  booking_reference: booking.reference,
                  rating: data.rating,
                  comment: data.comment || undefined,
                  locale,
                });
              }}
            />
          </div>
        )}
      </section>

      <aside className="space-y-6">
        <div className="rounded-lg border border-gray-200 bg-white p-5">
          <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('paymentReceipt')}</h2>
          {booking.payment ? (
            <div className="space-y-3 text-sm">
              <Field label={detailT('status')}><p>{booking.payment.status}</p></Field>
              <Field label={detailT('amount')}><p>{formatMoneyValue(booking.payment.amount)}</p></Field>
              <Field label={detailT('method')}>
                <p>
                  {booking.payment.method?.brand || booking.payment.method?.type || detailT('card')}
                  {booking.payment.method?.last4 ? ` ${detailT('ending')} ${booking.payment.method.last4}` : ''}
                </p>
              </Field>
              {booking.payment.transaction_date && (
                <Field label={detailT('transactionDate')}><p>{new Date(booking.payment.transaction_date).toLocaleString()}</p></Field>
              )}
            </div>
          ) : (
            <p className="text-sm text-gray-600">{detailT('receiptUnavailable')}</p>
          )}
        </div>

        <div className="rounded-lg bg-gray-50 p-4">
          <p className="mb-4 text-sm text-gray-600">
            {booking.cancellation_policy || detailT('cancellationFallback')}
          </p>
          {booking.status !== 'cancelled' && (
            <CancelBookingButton
              canCancel={Boolean(booking.can_cancel ?? booking.status === 'confirmed')}
              bookingReference={booking.reference}
              tourName={tourName}
              tourDate={booking.tour_date}
              onCancel={handleCancel}
            />
          )}
        </div>
      </aside>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="text-sm text-gray-500">{label}</p>
      <div className="text-sm text-gray-900">{children}</div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    confirmed: 'bg-green-100 text-green-800',
    completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-red-100 text-red-800',
    pending_payment: 'bg-amber-100 text-amber-800',
  };
  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
      {status}
    </span>
  );
}

function formatMoneyValue(value?: number | { amount?: number; formatted?: string }) {
  if (typeof value === 'object' && value?.formatted) return value.formatted;
  const amount = typeof value === 'object' ? value.amount : value;
  if (typeof amount !== 'number') return '';
  return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(amount / 100);
}
