'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslations, useLocale } from 'next-intl';
import { updateBookingStatus, requestBookingCancellation } from '@/lib/api/partner';
import { Button } from '@/components/ui/button';
import type { PartnerBooking, BookingStatus } from '@/types/partner';

interface BookingDetailProps {
  booking: PartnerBooking;
  onCancelRequest?: () => void;
}

export function BookingDetail({ booking }: BookingDetailProps) {
  const t = useTranslations('partner.bookings');
  const locale = useLocale();
  const queryClient = useQueryClient();

  const canMarkCompleted =
    booking.status === 'confirmed' &&
    new Date(booking.tour_date) <= new Date();

  const canRequestCancellation =
    booking.status === 'confirmed' ||
    booking.status === 'cancellation_requested';

  const markCompletedMutation = useMutation({
    mutationFn: () => updateBookingStatus(booking.reference, 'completed'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-bookings'] });
    },
  });

  const requestCancellationMutation = useMutation({
    mutationFn: (reason: string) =>
      requestBookingCancellation(booking.reference, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-bookings'] });
    },
  });

  const handleMarkCompleted = () => {
    markCompletedMutation.mutate();
  };

  const handleRequestCancellation = () => {
    const reason = window.prompt(t('detail.promptCancellationReason'));
    if (reason) {
      requestCancellationMutation.mutate(reason);
    }
  };

  const statusLabel = t(`status.${booking.status}` as `status.${BookingStatus}`) || booking.status.replace(/_/g, ' ');
  const statusStyle: Record<string, string> = {
    confirmed: 'bg-blue-50 text-blue-700 border-blue-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-red-50 text-red-700 border-red-200',
    cancellation_requested: 'bg-amber-50 text-amber-700 border-amber-200',
    pending_payment: 'bg-gray-50 text-gray-700 border-gray-200',
    expired: 'bg-gray-50 text-gray-500 border-gray-200',
    no_show: 'bg-gray-50 text-gray-500 border-gray-200',
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      {/* Header with status badge */}
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <h3 className="text-lg font-semibold text-[#0A2540]">{t('detail.title')}</h3>
          <span
            className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border ${
              statusStyle[booking.status] ?? 'bg-gray-50 text-gray-700 border-gray-200'
            }`}
          >
            {statusLabel}
          </span>
        </div>
        <span className="font-mono text-sm text-gray-500">{booking.reference}</span>
      </div>

      <div className="p-6 space-y-5">
        {/* Tour info */}
        <div className="space-y-1">
          <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.tour')}</p>
          <p className="text-sm font-semibold text-[#0A2540]">{booking.tour.title}</p>
        </div>

        {/* Date & time */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="space-y-1">
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.date')}</p>
            <p className="text-sm text-[#0A2540]">{new Date(booking.tour_date).toLocaleDateString(locale)}</p>
          </div>
          {booking.tour_time && (
            <div className="space-y-1">
              <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.time')}</p>
              <p className="text-sm text-[#0A2540]">{booking.tour_time}</p>
            </div>
          )}
        </div>

        {/* Traveler info */}
        <div className="space-y-1">
          <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.traveler')}</p>
          <p className="text-sm font-medium text-[#0A2540]">{booking.traveler.name}</p>
          <p className="text-sm text-gray-500">{booking.traveler.email}</p>
          {booking.traveler.phone && (
            <p className="text-sm text-gray-500">{booking.traveler.phone}</p>
          )}
        </div>

        {/* Participants */}
        <div className="space-y-1">
          <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">
            {t('detail.participants')} ({booking.total_participants} total)
          </p>
          {booking.participants && booking.participants.length > 0 ? (
            <div className="space-y-1">
              {booking.participants.map((p, idx) => (
                <div key={idx} className="flex items-center justify-between text-sm">
                  <span className="text-gray-700">{p.tier_name}</span>
                  <span className="text-gray-600">
                    {p.count} x {new Intl.NumberFormat(locale, { style: 'currency', currency: booking.currency ?? 'USD' }).format(p.price_per_person)}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500">{booking.total_participants} participants</p>
          )}
        </div>

        {/* Total amount */}
        <div className="flex items-center justify-between pt-3 border-t border-gray-100">
          <span className="text-sm font-medium text-gray-600">{t('detail.total')}</span>
          <span className="text-lg font-bold text-[#0A2540]">
            {new Intl.NumberFormat(locale, { style: 'currency', currency: booking.currency ?? 'USD' }).format(booking.total_amount)}
          </span>
        </div>

        {/* Special requests */}
        {booking.special_requests && (
          <div className="space-y-1 pt-3 border-t border-gray-100">
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.specialRequests')}</p>
            <p className="text-sm text-gray-700 bg-gray-50 rounded-lg p-3">{booking.special_requests}</p>
          </div>
        )}

        {/* Cancellation reason */}
        {booking.cancellation_reason && (
          <div className="space-y-1 pt-3 border-t border-gray-100">
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{t('detail.cancellationReason')}</p>
            <p className="text-sm text-red-600 bg-red-50 rounded-lg p-3">{booking.cancellation_reason}</p>
          </div>
        )}
      </div>

      {/* Action buttons */}
      {(canMarkCompleted || canRequestCancellation) && (
        <div className="px-6 py-4 border-t border-gray-100 flex items-center gap-3">
          {canMarkCompleted && (
            <Button
              onClick={handleMarkCompleted}
              disabled={markCompletedMutation.isPending}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
            >
              {markCompletedMutation.isPending ? t('detail.marking') : t('detail.markCompleted')}
            </Button>
          )}
          {canRequestCancellation && (
            <Button
              variant="outline"
              onClick={handleRequestCancellation}
              disabled={requestCancellationMutation.isPending}
              className="border-red-200 text-red-600 hover:bg-red-50"
            >
              {requestCancellationMutation.isPending ? t('detail.requesting') : t('detail.requestCancellation')}
            </Button>
          )}
        </div>
      )}
    </div>
  );
}