'use client';

import { useEffect, useState } from 'react';
import { getBookings } from '@/lib/api/partner';
import type { Booking } from '@/types/partner';

export function BookingList() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getBookings()
      .then((res) => setBookings(res.data))
      .catch((err) => setError(err.message ?? 'Failed to load bookings'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="text-sm text-gray-500">Loading bookings...</div>;
  }

  if (error) {
    return <div className="text-sm text-red-600">{error}</div>;
  }

  if (bookings.length === 0) {
    return (
      <div className="text-center py-16 bg-white rounded-xl border border-gray-200">
        <h3 className="text-lg font-semibold text-[#0A2540] mb-2">No bookings yet</h3>
        <p className="text-sm text-gray-500">Bookings will appear here once travelers reserve your tours.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Reference</th>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Tour</th>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Date</th>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Participants</th>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Total</th>
              <th className="text-left px-4 py-3 font-medium text-gray-700">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {bookings.map((booking) => (
              <tr key={booking.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-gray-600">{booking.reference}</td>
                <td className="px-4 py-3">Tour #{booking.tour_id}</td>
                <td className="px-4 py-3 text-gray-600">{booking.tour_date}</td>
                <td className="px-4 py-3">{booking.participants?.reduce((sum, p) => sum + (p.count ?? 0), 0) ?? '-'}</td>
                <td className="px-4 py-3 font-medium text-[#0A2540]">
                  {booking.currency} {Number(booking.total_amount).toFixed(2)}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                      booking.status === 'confirmed'
                        ? 'bg-blue-50 text-blue-700'
                        : booking.status === 'completed'
                        ? 'bg-emerald-50 text-emerald-700'
                        : booking.status === 'cancelled'
                        ? 'bg-red-50 text-red-700'
                        : 'bg-amber-50 text-amber-700'
                    }`}
                  >
                    {booking.status.replace('_', ' ')}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}