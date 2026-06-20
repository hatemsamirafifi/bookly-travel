'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useBookings } from '@/hooks/useBookings';
import BookingCard from './BookingCard';
import BookingFilters from '@/components/bookings/BookingFilters';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';

interface BookingListProps {
  locale: string;
}

interface Summary {
  total: number;
  confirmed: number;
  completed: number;
  cancelled: number;
}

export default function BookingList({ locale }: BookingListProps) {
  const t = useTranslations('traveler.dashboard');
  const searchParams = useSearchParams();
  const activeFilter = searchParams.get('status') || '';
  const { data, isLoading, error, refetch } = useBookings(activeFilter, locale);
  const bookings = data?.bookings ?? [];
  const summary: Summary = data?.summary ?? { total: 0, confirmed: 0, completed: 0, cancelled: 0 };

  const recentActivity = [...bookings]
    .sort((a, b) => {
      const aDate = a.created_at || a.booking_date || a.tour_date;
      const bDate = b.created_at || b.booking_date || b.tour_date;
      return new Date(bDate).getTime() - new Date(aDate).getTime();
    })
    .slice(0, 3);

  if (isLoading) {
    return <LoadingSkeleton variant="card" count={3} />;
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

      <BookingFilters locale={locale} />

      {error && (
        <div className="mb-4">
          <ErrorState message={t('loadError')} onRetry={() => refetch()} />
        </div>
      )}

      {bookings.length === 0 ? (
        <EmptyState
          title={t('empty')}
          cta={{ label: t('browseTours'), href: `/${locale}/search` }}
        />
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
