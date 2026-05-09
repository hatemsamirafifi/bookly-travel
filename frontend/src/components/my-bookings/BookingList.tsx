'use client';

import { useEffect, useState } from 'react';
import BookingCard from './BookingCard';
import { getMyBookings } from '@/lib/api/my-bookings';
import type { BookingResponse } from '@/lib/api/bookings';

interface BookingListProps {
  locale: string;
}

const STATUS_FILTERS = [
  { key: '', label: 'All' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
];

export default function BookingList({ locale }: BookingListProps) {
  const [bookings, setBookings] = useState<BookingResponse[]>([]);
  const [activeFilter, setActiveFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    getMyBookings(1, activeFilter || undefined)
      .then((res) => setBookings(res.data))
      .catch(() => setError('Failed to load bookings.'))
      .finally(() => setLoading(false));
  }, [activeFilter]);

  if (loading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <div key={i} className="animate-pulse rounded-lg border border-gray-200 p-4">
            <div className="flex gap-4">
              <div className="h-20 w-20 bg-gray-100 rounded-lg" />
              <div className="flex-1 space-y-2">
                <div className="h-5 bg-gray-100 rounded w-48" />
                <div className="h-4 bg-gray-100 rounded w-32" />
                <div className="h-4 bg-gray-100 rounded w-24" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div>
      <div className="flex gap-2 mb-4" role="tablist" aria-label="Filter by status">
        {STATUS_FILTERS.map(({ key, label }) => (
          <button
            key={key}
            role="tab"
            aria-selected={activeFilter === key}
            onClick={() => setActiveFilter(key)}
            className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${
              activeFilter === key
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {error && (
        <p className="text-red-600 text-sm mb-4" role="alert">{error}</p>
      )}

      {bookings.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-gray-500">No bookings yet — find a tour to start your adventure</p>
        </div>
      ) : (
        <div className="space-y-3">
          {bookings.map((booking) => (
            <BookingCard key={booking.reference} booking={booking} locale={locale} />
          ))}
        </div>
      )}
    </div>
  );
}
