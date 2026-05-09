'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getBookingDetail, cancelBooking } from '@/lib/api/my-bookings';
import CancelBookingButton from '@/components/my-bookings/CancelBookingButton';
import type { BookingResponse } from '@/lib/api/bookings';

interface Props {
  reference: string;
  locale: string;
}

export default function BookingDetailClient({ reference, locale }: Props) {
  const router = useRouter();
  const [booking, setBooking] = useState<BookingResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getBookingDetail(reference)
      .then((res) => setBooking(res.data))
      .catch(() => setError('Booking not found.'))
      .finally(() => setLoading(false));
  }, [reference]);

  const handleCancel = async () => {
    await cancelBooking(reference);
    router.refresh();
    getBookingDetail(reference)
      .then((res) => setBooking(res.data))
      .catch(() => setError('Failed to refresh booking.'));
  };

  if (loading) {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 bg-gray-100 rounded w-48" />
        <div className="h-32 bg-gray-100 rounded-lg" />
      </div>
    );
  }

  if (error || !booking) {
    return <p className="text-gray-500">{error || 'Booking not found.'}</p>;
  }

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-gray-200 divide-y divide-gray-200">
        <div className="p-4">
          <p className="text-sm text-gray-500">Reference</p>
          <p className="font-mono font-semibold text-gray-900">{booking.reference}</p>
        </div>
        <div className="p-4">
          <p className="text-sm text-gray-500">Tour</p>
          <p className="font-semibold text-gray-900">{booking.tour.title}</p>
          <p className="text-sm text-gray-600">{booking.tour.location}</p>
        </div>
        <div className="p-4">
          <p className="text-sm text-gray-500">Date</p>
          <p className="font-semibold text-gray-900">{booking.tour_date}</p>
        </div>
        <div className="p-4">
          <p className="text-sm text-gray-500">Participants</p>
          <p className="font-semibold text-gray-900">{booking.participant_count}</p>
        </div>
        <div className="p-4">
          <p className="text-sm text-gray-500">Total</p>
          <p className="font-semibold text-gray-900">{booking.total_price.formatted}</p>
        </div>
        <div className="p-4">
          <p className="text-sm text-gray-500">Status</p>
          <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
            booking.status === 'confirmed' ? 'bg-green-100 text-green-800' :
            booking.status === 'completed' ? 'bg-blue-100 text-blue-800' :
            booking.status === 'cancelled' ? 'bg-red-100 text-red-800' :
            'bg-yellow-100 text-yellow-800'
          }`}>
            {booking.status}
          </span>
        </div>
      </div>

      <div className="rounded-lg bg-gray-50 p-4">
        <p className="text-sm text-gray-600">{booking.cancellation_policy}</p>
      </div>

      {booking.can_cancel && (
        <CancelBookingButton canCancel={booking.can_cancel} onCancel={handleCancel} />
      )}
    </div>
  );
}
