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
      <div className="h-8 bg-[#F7F9FB] rounded w-48" />
      <div className="h-32 bg-[#F7F9FB] rounded-lg" />
    </div>;
  }

  if (!booking) {
    return <p className="text-[#5A6B7B]">Booking not found.</p>;
  }

  return (
    <div className="space-y-6">
      <div className="text-center">
        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#F7F9FB]">
          <svg className="h-7 w-7 text-[#0A2540]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-[#0A2540]">Booking Confirmed!</h2>
        <p className="mt-1 text-[#5A6B7B]">Your booking reference is</p>
        <p className="mt-1 text-2xl font-mono font-bold text-[#0A2540]">{booking.reference}</p>
      </div>

      <div className="rounded-lg border border-gray-200 divide-y divide-gray-200">
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">Tour</span>
          <span className="text-sm font-medium text-[#0A2540]">{booking.tour.title}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">Date</span>
          <span className="text-sm font-medium text-[#0A2540]">{booking.tour_date}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">Participants</span>
          <span className="text-sm font-medium text-[#0A2540]">{booking.participant_count}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">Total</span>
          <span className="text-sm font-semibold text-[#0A2540]">{booking.total_price.formatted}</span>
        </div>
        <div className="p-4 flex justify-between">
          <span className="text-sm text-[#5A6B7B]">Status</span>
          <span className="inline-flex items-center rounded-full bg-[#F7F9FB] px-2.5 py-0.5 text-xs font-medium text-[#0A2540]">
            {booking.status}
          </span>
        </div>
      </div>

      <div className="rounded-lg bg-[#F7F9FB] p-4">
        <p className="text-sm text-[#5A6B7B]">{booking.cancellation_policy}</p>
      </div>
    </div>
  );
}
