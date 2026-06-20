'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useBookingDetail } from '@/hooks/useBookingDetail';
import { useCancelBooking } from '@/hooks/useCancelBooking';
import BookingDetailView from '@/components/bookings/BookingDetailView';
import PaymentReceipt from '@/components/bookings/PaymentReceipt';
import BookingStatusTimeline from '@/components/bookings/BookingStatusTimeline';
import CancelBookingModal from '@/components/bookings/CancelBookingModal';
import ReviewForm from '@/components/reviews/ReviewForm';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import { submitReview } from '@/lib/reviews/review-api';
import { downloadTravelerBookingVoucher } from '@/lib/api/traveler';

interface Props {
  reference: string;
  locale: string;
}

export default function BookingDetailClient({ reference, locale }: Props) {
  const t = useTranslations();
  const detailT = useTranslations('traveler.bookingDetail');
  const { data: booking, isLoading, error, refetch } = useBookingDetail(reference);
  const cancelMutation = useCancelBooking(reference, locale);
  const [isDownloading, setIsDownloading] = useState(false);

  const handleDownloadVoucher = async () => {
    setIsDownloading(true);
    try {
      await downloadTravelerBookingVoucher(reference);
    } catch (err) {
      console.error(err);
      alert('Failed to download voucher. Please try again.');
    } finally {
      setIsDownloading(false);
    }
  };

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
    await cancelMutation.mutateAsync();
  };

  if (isLoading) {
    return <LoadingSkeleton variant="detail" />;
  }

  if (error || !booking) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-6">
        <ErrorState message={detailT('notFound')} onRetry={() => refetch()} />
      </div>
    );
  }

  const tourName = booking.tour.name || booking.tour.title || detailT('fallbackTour');

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
      <section className="space-y-6">
        <BookingDetailView booking={booking} locale={locale} />

        <div className="rounded-lg border border-gray-200 bg-white p-5">
          <h2 className="mb-4 text-lg font-semibold text-gray-900">{detailT('tripDetails')}</h2>
          <div className="space-y-4">
            <div>
              <p className="text-sm text-gray-500">{detailT('meetingPoint')}</p>
              <p className="text-sm text-gray-900">{booking.tour.meeting_point || detailT('meetingPointFallback')}</p>
            </div>
            {booking.tour.inclusions && booking.tour.inclusions.length > 0 && (
              <div>
                <p className="text-sm text-gray-500">{detailT('included')}</p>
                <ul className="list-inside list-disc text-sm text-gray-700">
                  {booking.tour.inclusions.map((item) => <li key={item}>{item}</li>)}
                </ul>
              </div>
            )}
          </div>
        </div>

        <BookingStatusTimeline items={timeline} />

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
        <PaymentReceipt payment={booking.payment} />

        {booking.status === 'confirmed' && (
          <div className="rounded-lg border border-blue-100 bg-blue-50 p-4">
            <h3 className="mb-2 font-semibold text-blue-900">Your Ticket & Voucher</h3>
            <p className="mb-4 text-sm text-blue-700">
              Download your PDF voucher and ticket details here.
            </p>
            <button
              onClick={() => handleDownloadVoucher()}
              disabled={isDownloading}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-[#0A2540] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#FFB800] hover:text-[#0A2540] transition-colors disabled:opacity-50"
            >
              {isDownloading ? 'Downloading...' : 'Download PDF Voucher'}
            </button>
          </div>
        )}

        <div className="rounded-lg bg-gray-50 p-4">
          <p className="mb-4 text-sm text-gray-600">
            {booking.cancellation_policy || detailT('cancellationFallback')}
          </p>
          {booking.status !== 'cancelled' && (
            <CancelBookingAction
              canCancel={Boolean(booking.can_cancel ?? booking.status === 'confirmed')}
              bookingReference={booking.reference}
              tourName={tourName}
              tourDate={booking.tour_date}
              onCancel={handleCancel}
              isCancelling={cancelMutation.isPending}
              cancelError={cancelMutation.error ? (cancelMutation.error as Error).message : null}
            />
          )}
        </div>
      </aside>
    </div>
  );
}

interface CancelBookingActionProps {
  canCancel: boolean;
  bookingReference?: string;
  tourName?: string;
  tourDate?: string;
  onCancel: () => Promise<void>;
  isCancelling?: boolean;
  cancelError?: string | null;
}

function CancelBookingAction({
  canCancel,
  bookingReference,
  tourName,
  tourDate,
  onCancel,
  isCancelling = false,
  cancelError = null,
}: CancelBookingActionProps) {
  const detailT = useTranslations('traveler.cancelBooking');
  const [showConfirm, setShowConfirm] = useState(false);

  const handleConfirm = async () => {
    await onCancel();
    setShowConfirm(false);
  };

  return (
    <>
      <button
        onClick={() => setShowConfirm(true)}
        disabled={!canCancel}
        title={!canCancel ? detailT('disabledTitle') : undefined}
        className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:opacity-50"
      >
        {detailT('button')}
      </button>

      <CancelBookingModal
        open={showConfirm}
        bookingReference={bookingReference}
        tourName={tourName}
        tourDate={tourDate}
        loading={isCancelling}
        error={cancelError}
        onConfirm={handleConfirm}
        onCancel={() => setShowConfirm(false)}
      />
    </>
  );
}
