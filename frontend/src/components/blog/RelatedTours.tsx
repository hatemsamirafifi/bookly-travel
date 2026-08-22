'use client';

import type { TourCard as TourCardType } from '@/lib/api/types';
import TourCard from '@/components/search/TourCard';
import { useTranslations } from 'next-intl';

interface RelatedToursProps {
  tours: TourCardType[];
  locale: string;
}

export default function RelatedTours({ tours, locale }: RelatedToursProps) {
  const t = useTranslations('blog');

  if (!tours || tours.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="related-tours-heading" className="mt-12 border-t border-gray-200 pt-10">
      <h2 id="related-tours-heading" className="mb-6 text-2xl font-bold text-[#0A2540]">
        {t('relatedTours')}
      </h2>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {tours.map((tour) => (
          <TourCard key={tour.id} tour={tour} locale={locale} />
        ))}
      </div>
    </section>
  );
}
