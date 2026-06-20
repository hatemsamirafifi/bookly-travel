'use client';

import { useSearchParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';

const STATUS_FILTERS = ['', 'confirmed', 'completed', 'cancelled'] as const;

interface BookingFiltersProps {
  locale: string;
}

export default function BookingFilters({ locale }: BookingFiltersProps) {
  const t = useTranslations('traveler.dashboard');
  const router = useRouter();
  const searchParams = useSearchParams();
  const activeFilter = searchParams.get('status') || '';

  const selectFilter = (status: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (status) {
      params.set('status', status);
    } else {
      params.delete('status');
    }
    router.push(`/${locale}/my-bookings${params.toString() ? `?${params}` : ''}`);
  };

  return (
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
  );
}
