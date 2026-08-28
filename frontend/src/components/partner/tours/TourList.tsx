'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { getTours } from '@/lib/api/partner';
import type { Tour } from '@/types/partner';
import { TourCard } from './TourCard';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import Link from 'next/link';
import { PartnerTourListSkeleton } from '@/components/partner/layout/PartnerSkeleton';

export function TourList() {
  const t = useTranslations('partner.tours');
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadTours = () => {
    setLoading(true);
    setError(null);
    getTours()
      .then((res) => setTours(res.data))
      .catch((err) => setError(err.message ?? t('loadError')))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadTours();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [t]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[#0A2540]">{t('title')}</h1>
        <Link href="/partner/tours/create">
          <Button size="sm" className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">
            <Plus className="w-4 h-4 mr-1" />
            {t('createTour')}
          </Button>
        </Link>
      </div>

      {loading ? (
        <PartnerTourListSkeleton />
      ) : error ? (
        <div className="text-sm text-red-600">{error}</div>
      ) : tours.length === 0 ? (
        <div className="text-center py-16 bg-white rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold text-[#0A2540] mb-2">{t('noTours')}</h3>
          <p className="text-sm text-gray-500 mb-6">{t('createTour')}</p>
          <Link href="/partner/tours/create">
            <Button className="bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] font-semibold">
              <Plus className="w-4 h-4 mr-2" />
              {t('createTour')}
            </Button>
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {tours.map((tour) => (
            <TourCard key={tour.id} tour={tour} onArchived={loadTours} />
          ))}
        </div>
      )}
    </div>
  );
}
