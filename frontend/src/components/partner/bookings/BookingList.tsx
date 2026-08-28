'use client';

import { useEffect, useState, useCallback } from 'react';
import { useTranslations } from 'next-intl';
import { getBookings } from '@/lib/api/partner';
import type { PartnerBooking } from '@/types/partner';
import { PartnerBookingListSkeleton } from '@/components/partner/layout/PartnerSkeleton';
import { BookingFilters, type BookingFilterValues } from './BookingFilters';

export function BookingList() {
  const t = useTranslations('partner.bookings');
  const tDetail = useTranslations('partner.bookings.detail');
  const tStatus = useTranslations('partner.bookings.status');
  const [bookings, setBookings] = useState<PartnerBooking[]>([]);
  const [filters, setFilters] = useState<BookingFilterValues>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchBookings = useCallback((currentFilters: BookingFilterValues) => {
    setLoading(true);
    setError(null);
    getBookings({
      status: currentFilters.status,
      date_from: currentFilters.date_from,
      date_to: currentFilters.date_to,
      tour_id: currentFilters.tour_id,
      search: currentFilters.search,
    })
      .then((res) => setBookings(res.data))
      .catch((err) => setError(err.message ?? t('loadError')))
      .finally(() => setLoading(false));
  }, [t]);

  useEffect(() => {
    fetchBookings(filters);
  }, [fetchBookings, filters]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[#0A2540]">{t('title')}</h1>
      </div>

      <BookingFilters filters={filters} onFiltersChange={setFilters} />

      {loading ? (
        <PartnerBookingListSkeleton />
      ) : error ? (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm" role="alert">
          {error}
        </div>
      ) : bookings.length === 0 ? (
        <div className="text-center py-16 bg-white rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold text-[#0A2540] mb-2">{t('noBookings')}</h3>
          <p className="text-sm text-gray-500">{t('noBookingsSubtitle') || 'Bookings for your tours will appear here.'}</p>
        </div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('reference')}</th>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('tour')}</th>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('date')}</th>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('participants')}</th>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('total')}</th>
                  <th scope="col" className="text-left px-4 py-3 font-medium text-gray-700">{tDetail('status')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {bookings.map((booking) => (
                  <tr key={booking.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-gray-600">{booking.reference}</td>
                    <td className="px-4 py-3">{booking.tour?.title ?? (booking.tour?.id ? `Tour #${booking.tour.id}` : '-')}</td>
                    <td className="px-4 py-3 text-gray-600">{booking.tour_date}</td>
                    <td className="px-4 py-3">{booking.total_participants ?? '-'}</td>
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
                        {tStatus(booking.status) ?? booking.status.replace('_', ' ')}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
