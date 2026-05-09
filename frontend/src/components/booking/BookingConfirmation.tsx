'use client';

import { useEffect, useState } from 'react';
import { getBookingDetail } from '@/lib/api/my-bookings';
import type { BookingResponse } from '@/lib/api/bookings';

interface BookingConfirmationProps {
  reference: string;
  locale: string;
}

export default function BookingConfirmation({ reference, locale }: BookingConfirmationProps) {
  const [booking, setBooking] = useState<BookingResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getBookingDetail(reference)
      .then((res) => setBooking(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [reference]);

  if (loading) {
    return <div className="animate-pulse space-y-4">
      <div className="h-8 bg-gray-100 rounded w-48" />
      <div className="h-32 bg-gray-100 rounded-lg" />
    </div>;
  }

  if (!booking) {
    return <p className="text-gray-500">Booking not found.</p>;
  }

  return (
    <div className="space-y-6">
      <div className="text-center">
        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
          <svg className="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-gray-900">Booking Confirmed!</h2>
        <p className="mt-1 text-gray-500">Your booking reference is</p>
        <p className="mt-1 text-2xl font-mono font-bold text-blue-600">{booking.reference}</p>
      </div>

      <div className="rounded-lg border border-gray-200 divide-y divide-gray-200">
        <div className="p-4 flex justify-between">
          <span className="text-sm text-gray-500">Tour</span>
          <span className="text-sm font-medium text-gray-900">{booking.tour.title}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-gray-500">Date</span>
          <span className="text-sm font-medium text-gray-900">{booking.tour_date}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-gray-500">Participants</span>
          <span className="text-sm font-medium text-gray-900">{booking.participant_count}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-gray-500">Total</span>
          <span className="text-sm font-semibold text-gray-900">{booking.total_price.formatted}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-gray-500">Status</span>
          <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
            {booking.status}
          </span>
        </div>
      </div>

      <div className="rounded-lg bg-gray-50 p-4">
        <p className="text-sm text-gray-600">{booking.cancellation_policy}</p>
      </div>
    </div>
  );
}
