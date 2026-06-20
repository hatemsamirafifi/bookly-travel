'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import BookingCard from './BookingCard';
import { getMyBookings, getMyBookingsSummary } from '@/lib/api/my-bookings';
import type { TravelerBooking } from '@/types/traveler';

interface BookingListProps {
  locale: string;
}

const STATUS_FILTERS = ['', 'confirmed', 'completed', 'cancelled'] as const;

interface Summary {
  total: number;
  confirmed: number;
  completed: number;
  cancelled: number;
}

export default function BookingList({ locale }: BookingListProps) {
  const t = useTranslations('traveler.dashboard');
  const router = useRouter();
  const searchParams = useSearchParams();
  const [bookings, setBookings] = useState<TravelerBooking[]>([]);
  const [summary, setSummary] = useState<Summary>({ total: 0, confirmed: 0, completed: 0, cancelled: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const activeFilter = searchParams.get('status') || '';

  const loadBookings = useCallback(() => {
    setLoading(true);
    setError(null);
    Promise.all([
      getMyBookings(activeFilter || undefined, 1),
      getMyBookingsSummary(),
    ])
      .then(([filtered, summaryData]) => {
        setBookings(filtered.data);
        setSummary(summaryData.data);
      })
      .catch(() => setError(t('loadError')))
      .finally(() => setLoading(false));
  }, [activeFilter, t]);

  useEffect(() => {
    void Promise.resolve().then(loadBookings);
  }, [loadBookings]);

  const selectFilter = (status: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (status) {
      params.set('status', status);
    } else {
      params.delete('status');
    }
    router.push(`/${locale}/my-bookings${params.toString() ? `?${params}` : ''}`);
  };

  const recentActivity = [...bookings]
    .sort((a, b) => {
      const aDate = a.created_at || a.booking_date || a.tour_date;
      const bDate = b.created_at || b.booking_date || b.tour_date;
      return new Date(bDate).getTime() - new Date(aDate).getTime();
    })
    .slice(0, 3);

  if (loading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <div key={i} className="animate-pulse rounded-lg border border-gray-200 p-4">
            <div className="flex gap-4">
              <div className="h-20 w-20 rounded-lg bg-gray-100" />
              <div className="flex-1 space-y-2">
                <div className="h-5 w-48 rounded bg-gray-100" />
                <div className="h-4 w-32 rounded bg-gray-100" />
                <div className="h-4 w-24 rounded bg-gray-100" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div>
      <section className="mb-6 grid gap-3 sm:grid-cols-4" aria-label={t('summaryLabel')}>
        <SummaryCard label={t('summaryTotal')} value={summary.total} />
        <SummaryCard label={t('summaryUpcoming')} value={summary.confirmed} />
        <SummaryCard label={t('summaryCompleted')} value={summary.completed} />
        <SummaryCard label={t('summaryCancelled')} value={summary.cancelled} />
      </section>

      <section className="mb-6 grid gap-4 lg:grid-cols-[1fr_280px]">
        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">{t('recentActivity')}</h2>
          {recentActivity.length > 0 ? (
            <ol className="mt-3 space-y-3">
              {recentActivity.map((booking) => (
                <li key={booking.reference} className="flex items-center justify-between gap-3 text-sm">
                  <Link href={`/${locale}/my-bookings/${booking.reference}`} className="font-medium text-[#0A2540] hover:underline">
                    {booking.tour.name || booking.tour.title || t('fallbackTour')}
                  </Link>
                  <span className="text-gray-500">{t(`status.${booking.status}`)}</span>
                </li>
              ))}
            </ol>
          ) : (
            <p className="mt-3 text-sm text-gray-500">{t('noRecentActivity')}</p>
          )}
        </div>
        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">{t('quickActions')}</h2>
          <div className="mt-3 grid gap-2">
            <Link href={`/${locale}/search`} className="rounded-lg bg-[#FFB800] px-4 py-2 text-sm font-semibold text-[#0A2540]">
              {t('browseTours')}
            </Link>
            <Link href={`/${locale}/wishlist`} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[#0A2540]">
              {t('viewWishlist')}
            </Link>
            <Link href={`/${locale}/profile`} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-[#0A2540]">
              {t('editProfile')}
            </Link>
          </div>
        </div>
      </section>

      <div className="mb-4 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label={t('filterLabel')}>
        {STATUS_FILTERS.map((key) => (
          <button
            key={key}
            role="tab"
            aria-selected={activeFilter === key}
            onClick={() => selectFilter(key)}
            className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${
              activeFilter === key
                ? 'bg-[#0A2540] text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            {t(`filters.${key || 'all'}`)}
          </button>
        ))}
      </div>

      {error && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
          <p className="mb-3 text-sm text-red-700">{error}</p>
          <button onClick={loadBookings} className="text-sm font-semibold text-red-800 underline">
            {t('tryAgain')}
          </button>
        </div>
      )}

      {bookings.length === 0 ? (
        <div className="rounded-lg border border-dashed border-gray-300 bg-white py-12 text-center">
          <p className="text-gray-500">{t('empty')}</p>
          <Link
            href={`/${locale}/search`}
            className="mt-4 inline-flex rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540]"
          >
            {t('browseTours')}
          </Link>
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

function SummaryCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      <p className="text-sm text-gray-500">{label}</p>
      <p className="mt-1 text-2xl font-semibold text-[#0A2540]">{value}</p>
    </div>
  );
}
